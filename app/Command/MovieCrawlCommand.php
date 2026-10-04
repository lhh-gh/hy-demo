<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Command;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Context\Context;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Coroutine\Exception\ParallelExecutionException;
use Hyperf\Coroutine\Parallel;
use Hyperf\Guzzle\ClientFactory;
use RuntimeException;
use Swoole\Coroutine\Channel;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

#[Command]
class MovieCrawlCommand extends HyperfCommand
{
    /**
     * 仅用于 race 模式演示错误。
     * 正常采集流程不使用这个静态属性。
     */
    private static string $currentPage = '';

    public function __construct(
        private readonly ClientFactory $clientFactory,
    ) {
        parent::__construct('movie:crawl');
    }

    public function configure(): void
    {
        parent::configure();

        $this->setDescription('豆瓣电影采集');

        $this->addOption(
            'mode',
            null,
            InputOption::VALUE_REQUIRED,
            'serial、parallel、compare、queue、race、context',
            'parallel'
        );
    }

    public function handle(): void
    {
        $this->exitCode = 0;

        $mode = (string) $this->input->getOption('mode');

        $allowedModes = [
            'serial',
            'parallel',
            'compare',
            'queue',
            'race',
            'context',
        ];

        if (! in_array($mode, $allowedModes, true)) {
            $this->error('不支持的模式：' . $mode);
            $this->exitCode = 1;

            return;
        }

        // 这两个实验只模拟协程切换，不访问豆瓣。
        if ($mode === 'race' || $mode === 'context') {
            $this->demonstrateIsolation($mode === 'context');

            return;
        }

        if (! extension_loaded('dom')) {
            throw new RuntimeException('解析 HTML 需要 PHP DOM 扩展');
        }

        // 前两页，通常每页 25 部电影。
        $starts = [0, 25];

        if ($mode === 'compare') {
            $this->compare($starts);

            return;
        }

        $report = $this->executeMode($mode, $starts);

        $this->printReport($mode, $report, true);
    }

    /**
     * 执行采集并统一计时。
     *
     * 耗时包括请求、解析和任务调度；queue 模式还包括逐任务日志输出。
     */
    private function executeMode(string $mode, array $starts): array
    {
        $startedAt = hrtime(true);

        $report = match ($mode) {
            'serial' => $this->runSerial($starts),
            'parallel' => $this->runParallel($starts),
            'queue' => $this->runQueue($starts),
            default => throw new RuntimeException('未知采集模式'),
        };

        $report['seconds'] = (hrtime(true) - $startedAt) / 1e9;
        $report['requested_pages'] = count($starts);

        return $report;
    }

    /**
     * 实验一：串行请求。
     *
     * 当前页面请求、解析完成后，才处理下一页。
     */
    private function runSerial(array $starts): array
    {
        $pages = [];
        $errors = [];

        foreach ($starts as $start) {
            try {
                $pages[$start] = $this->crawlPage($start);
            } catch (Throwable $exception) {
                $errors[$start] = $exception->getMessage();
            }
        }

        return [
            'pages' => $pages,
            'errors' => $errors,
        ];
    }

    /**
     * 实验二、五：Parallel 并发请求与结果聚合。
     */
    private function runParallel(array $starts): array
    {
        $parallel = new Parallel(2);

        foreach ($starts as $start) {
            $parallel->add(
                fn (): array => $this->crawlPage($start),
                $start
            );
        }

        $errors = [];

        try {
            $pages = $parallel->wait();
        } catch (ParallelExecutionException $exception) {
            // 保留成功任务的结果。
            $pages = $exception->getResults();

            foreach ($exception->getThrowables() as $start => $throwable) {
                $errors[$start] = $throwable->getMessage();
            }
        }

        return [
            'pages' => $pages,
            'errors' => $errors,
        ];
    }

    /**
     * 串行与并发耗时对比。
     */
    private function compare(array $starts): void
    {
        $serial = $this->executeMode('serial', $starts);
        $this->printReport('serial', $serial, false);

        // 两轮真实请求之间留出间隔。
        Coroutine::sleep(1.0);

        $parallel = $this->executeMode('parallel', $starts);
        $this->printReport('parallel', $parallel, false);

        if ($serial['errors'] !== [] || $parallel['errors'] !== []) {
            $this->error('存在失败页面，本轮不计算有效加速比');

            return;
        }

        // 确保两轮采集的是同一组电影。
        $serialIds = array_column(
            $this->mergeMovies($serial['pages']),
            'douban_id'
        );

        $parallelIds = array_column(
            $this->mergeMovies($parallel['pages']),
            'douban_id'
        );

        sort($serialIds);
        sort($parallelIds);

        if ($serialIds !== $parallelIds) {
            $this->error('两轮电影 ID 不一致，本轮不计算有效加速比');
            $this->exitCode = 1;

            return;
        }

        $speedup = $serial['seconds']
            / max($parallel['seconds'], 0.000001);

        $this->line(sprintf(
            '加速比：%.2f 倍；本次并发上限为 2',
            $speedup
        ));
    }

    /**
     * 实验三：Channel 队列。
     *
     * 当前命令协程作为生产者，两个消费者协程处理页面。
     * 每个任务无论成功还是失败，都必须返回一个结果。
     */
    private function runQueue(array $starts): array
    {
        $workerCount = 2;
        $taskCount = count($starts);

        $tasks = new Channel(2);

        // 容量覆盖全部结果，避免生产者投递期间消费者被阻塞。
        $results = new Channel(max(1, $taskCount));

        $exits = new Channel($workerCount);

        for ($workerId = 1; $workerId <= $workerCount; ++$workerId) {
            Coroutine::create(function () use (
                $workerId,
                $tasks,
                $results,
                $exits
            ): void {
                try {
                    while (true) {
                        $task = $tasks->pop();

                        if ($task === false) {
                            if ($tasks->errCode === SWOOLE_CHANNEL_CLOSED) {
                                break;
                            }

                            throw new RuntimeException('读取任务失败');
                        }

                        try {
                            $movies = $this->crawlPage($task['start']);

                            $result = [
                                'task_id' => $task['task_id'],
                                'start' => $task['start'],
                                'worker_id' => $workerId,
                                'movies' => $movies,
                                'error' => null,
                            ];
                        } catch (Throwable $exception) {
                            $result = [
                                'task_id' => $task['task_id'],
                                'start' => $task['start'],
                                'worker_id' => $workerId,
                                'movies' => [],
                                'error' => $exception->getMessage(),
                            ];
                        }

                        $results->push($result);
                    }
                } finally {
                    $exits->push($workerId);
                }
            });
        }

        $pages = [];
        $errors = [];
        $seen = [];
        $expectedTaskIds = [];

        try {
            // 唯一生产者：投递页面任务。
            foreach ($starts as $index => $start) {
                $taskId = 'task-' . $index;
                $expectedTaskIds[] = $taskId;

                if (! $tasks->push([
                    'task_id' => $taskId,
                    'start' => $start,
                ], 30.0)) {
                    throw new RuntimeException("投递任务超时：{$taskId}");
                }
            }

            // 收集结果，也相当于等待所有任务处理完成。
            for ($index = 0; $index < $taskCount; ++$index) {
                $result = $results->pop(30.0);

                if ($result === false) {
                    throw new RuntimeException('等待任务结果超时');
                }

                $taskId = $result['task_id'];

                $seen[$taskId] = ($seen[$taskId] ?? 0) + 1;

                if ($result['error'] !== null) {
                    $errors[$result['start']] = $result['error'];
                } else {
                    $pages[$result['start']] = $result['movies'];
                }

                $this->line(sprintf(
                    'worker=%d task=%s start=%d status=%s',
                    $result['worker_id'],
                    $taskId,
                    $result['start'],
                    $result['error'] === null ? '成功' : '失败'
                ));
            }
        } finally {
            // 正常情况下，此时所有任务已经返回结果。
            // 关闭后唤醒等待 pop() 的消费者。
            $tasks->close();

            $exitedWorkers = [];

            for ($index = 0; $index < $workerCount; ++$index) {
                $workerId = $exits->pop(30.0);

                if ($workerId === false) {
                    throw new RuntimeException('等待消费者退出超时');
                }

                $exitedWorkers[] = $workerId;
            }
        }

        foreach ($expectedTaskIds as $taskId) {
            if (($seen[$taskId] ?? 0) !== 1) {
                throw new RuntimeException(
                    "任务丢失或重复：{$taskId}"
                );
            }
        }

        if (
            count($seen) !== $taskCount
            || count(array_unique($exitedWorkers)) !== $workerCount
        ) {
            throw new RuntimeException('队列数量或消费者退出检查失败');
        }

        $this->line(sprintf(
            '队列验证：返回结果 %d 个，唯一任务 %d 个，退出消费者 %d 个',
            array_sum($seen),
            count($seen),
            count($exitedWorkers)
        ));

        return [
            'pages' => $pages,
            'errors' => $errors,
        ];
    }

    /**
     * 实验四：静态变量串协程，以及 Context 修复。
     *
     * 两个任务只等待本地定时器，不发送 HTTP 请求。
     */
    private function demonstrateIsolation(bool $useContext): void
    {
        $parallel = new Parallel(2);

        foreach ([0, 25] as $start) {
            $parallel->add(function () use ($start, $useContext): array {
                $expected = $this->pageUrl($start);

                if ($useContext) {
                    // 必须在任务自己的协程中设置 Context。
                    Context::set('crawler.current_page', $expected);
                } else {
                    self::$currentPage = $expected;
                }

                try {
                    // 制造协程切换窗口。
                    Coroutine::sleep(0.05);

                    $actual = $useContext
                        ? Context::get('crawler.current_page')
                        : self::$currentPage;

                    return [
                        'expected' => $expected,
                        'actual' => $actual,
                        'matched' => $expected === $actual,
                    ];
                } finally {
                    if ($useContext) {
                        Context::destroy('crawler.current_page');
                    }
                }
            }, $start);
        }

        $results = $parallel->wait();
        $mismatches = 0;

        foreach ($results as $result) {
            if (! $result['matched']) {
                ++$mismatches;
            }

            $this->line(json_encode(
                $result,
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ));
        }

        // race 模式期望复现错误；context 模式期望没有错误。
        $passed = $useContext
            ? $mismatches === 0
            : $mismatches > 0;

        $this->line(sprintf(
            '模式：%s，串数据数量：%d，实验结果：%s',
            $useContext ? 'Context' : '静态变量',
            $mismatches,
            $passed ? '符合预期' : '不符合预期'
        ));

        if (! $passed) {
            $this->exitCode = 1;
        }
    }

    /**
     * 请求并解析一个页面。
     */
    private function crawlPage(int $start): array
    {
        // 在执行任务的协程内设置；同一消费者会复用协程处理多个任务。
        Context::set('crawler.current_page', $this->pageUrl($start));

        try {
            return $this->parsePage($this->fetchPage($start));
        } catch (Throwable $exception) {
            throw new RuntimeException(
                Context::get('crawler.current_page') . '：' . $exception->getMessage(),
                0,
                $exception
            );
        } finally {
            Context::destroy('crawler.current_page');
        }
    }

    private function pageUrl(int $start): string
    {
        return 'https://movie.douban.com/top250?start=' . $start;
    }

    private function fetchPage(int $start): string
    {
        $client = $this->clientFactory->create([
            'base_uri' => 'https://movie.douban.com/',
            'connect_timeout' => 5.0,
            'timeout' => 15.0,
            'http_errors' => false,
            'allow_redirects' => false,
            'headers' => [
                'User-Agent' => 'HyperfMovieLearningBot/1.0',
                'Accept' => 'text/html',
            ],
        ]);

        $response = $client->get('top250', [
            'query' => [
                'start' => $start,
            ],
        ]);

        $status = $response->getStatusCode();

        if ($status !== 200) {
            throw new RuntimeException(
                "HTTP {$status}，本页不自动重试"
            );
        }

        if (
            stripos(
                $response->getHeaderLine('Content-Type'),
                'text/html'
            ) === false
        ) {
            throw new RuntimeException('响应不是 HTML');
        }

        return (string) $response->getBody();
    }

    private function parsePage(string $html): array
    {
        if (trim($html) === '') {
            throw new RuntimeException('页面内容为空');
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            // 同步解析，中间没有协程挂起点。
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8">' . $html,
                LIBXML_NONET
            );

            if (! $loaded) {
                throw new RuntimeException('HTML 解析失败');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);

        $items = $xpath->query(
            '//ol[' . $this->hasClass('grid_view') . ']/li'
        );

        if ($items === false || $items->length === 0) {
            throw new RuntimeException(
                '没有找到电影列表：可能是页面结构变化或访问受限'
            );
        }

        $movies = [];

        foreach ($items as $item) {
            if (! $item instanceof DOMElement) {
                continue;
            }

            $title = $this->text(
                $xpath,
                $item,
                '(.//div[' . $this->hasClass('hd') . ']'
                . '/a/span[' . $this->hasClass('title') . '])[1]'
            );

            $url = $this->text(
                $xpath,
                $item,
                '(.//div[' . $this->hasClass('hd') . ']/a/@href)[1]'
            );

            $rankText = $this->text(
                $xpath,
                $item,
                '(.//div[' . $this->hasClass('pic') . ']/em)[1]'
            );

            $ratingText = $this->text(
                $xpath,
                $item,
                '(.//span[' . $this->hasClass('rating_num') . '])[1]'
            );

            // 不依赖已经不存在的 div.star。
            $countText = $this->text(
                $xpath,
                $item,
                '(.//span[contains(., "人评价")])[1]'
            );

            $matchedId = preg_match(
                '~/subject/([0-9]+)/~',
                $url,
                $idMatch
            );

            $matchedCount = preg_match(
                '/([0-9,]+)\s*人评价/u',
                $countText,
                $countMatch
            );

            if (
                $title === ''
                || $matchedId !== 1
                || ! ctype_digit($rankText)
                || ! is_numeric($ratingText)
                || $matchedCount !== 1
            ) {
                throw new RuntimeException(
                    '电影字段校验失败：' . json_encode([
                        'title' => $title,
                        'url' => $url,
                        'rank' => $rankText,
                        'rating' => $ratingText,
                        'rating_count_text' => $countText,
                    ], JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                        | JSON_THROW_ON_ERROR)
                );
            }

            $movies[] = [
                'douban_id' => $idMatch[1],
                'rank' => (int) $rankText,
                'title' => $title,
                'rating' => (float) $ratingText,
                'rating_count' => (int) str_replace(
                    ',',
                    '',
                    $countMatch[1]
                ),
                'intro' => $this->text(
                    $xpath,
                    $item,
                    '(.//div[' . $this->hasClass('bd') . ']/p)[1]'
                ),
                'quote' => $this->text(
                    $xpath,
                    $item,
                    '(.//p[' . $this->hasClass('quote') . ']/span)[1]'
                ),
                'url' => $url,
            ];
        }

        return $movies;
    }

    private function hasClass(string $class): string
    {
        // 参数只使用代码中固定的 class 名称。
        return 'contains(concat(" ", normalize-space(@class), " "),'
            . ' " ' . $class . ' ")';
    }

    private function text(
        DOMXPath $xpath,
        DOMElement $item,
        string $expression
    ): string {
        $value = (string) $xpath->evaluate(
            "string({$expression})",
            $item
        );

        return trim(
            preg_replace('/\s+/u', ' ', $value) ?? $value
        );
    }

    /**
     * 合并电影、按 ID 去重、按排名排序。
     */
    private function mergeMovies(array $pages): array
    {
        $movies = [];

        foreach ($pages as $items) {
            foreach ($items as $movie) {
                $movies[$movie['douban_id']] = $movie;
            }
        }

        $movies = array_values($movies);

        usort(
            $movies,
            static fn (array $left, array $right): int => $left['rank'] <=> $right['rank']
        );

        return $movies;
    }

    private function printReport(
        string $mode,
        array $report,
        bool $printMovies
    ): void {
        $movies = $this->mergeMovies($report['pages']);

        if ($printMovies) {
            foreach ($movies as $movie) {
                $this->line(json_encode(
                    $movie,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                ));
            }
        }

        foreach ($report['errors'] as $start => $message) {
            $this->error("页面 start={$start} 失败：{$message}");
        }

        $this->line(sprintf(
            '[%s] 成功 %d/%d 页，电影 %d 部，耗时 %.3f 秒',
            $mode,
            count($report['pages']),
            $report['requested_pages'],
            count($movies),
            $report['seconds']
        ));

        if ($report['errors'] !== []) {
            $this->exitCode = 1;
        }
    }
}
