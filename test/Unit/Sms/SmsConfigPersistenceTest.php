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

namespace HyperfTest\Unit\Sms;

use App\Sms\Config\DatabaseSmsConfigRepository;
use App\Sms\Config\SmsConfigWriter;
use App\Sms\Config\SmsCredentialCipher;
use App\Sms\Config\SmsRoutePublisher;
use Hyperf\Config\Config;
use Hyperf\Context\ApplicationContext;
use Hyperf\Database\Connection;
use Hyperf\Database\ConnectionResolverInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSource;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * 隔离内存数据库验证写入、版本与回滚，不验证 MySQL 行锁或并发语义。
 * @internal
 * @coversNothing
 */
final class SmsConfigPersistenceTest extends TestCase
{
    private ContainerInterface $originalContainer;

    private Connection $connection;

    private SmsConfigWriter $writer;

    private SmsRoutePublisher $publisher;

    private DatabaseSmsConfigRepository $repository;

    protected function setUp(): void
    {
        $this->originalContainer = ApplicationContext::hasContainer()
            ? ApplicationContext::getContainer() : new Container(new DefinitionSource([]));
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE sms_channels (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL,
            provider TEXT NOT NULL, credentials_ciphertext TEXT NOT NULL, options TEXT NOT NULL,
            enabled INTEGER NOT NULL, version INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE sms_templates (id INTEGER PRIMARY KEY AUTOINCREMENT,
            channel_id INTEGER NOT NULL REFERENCES sms_channels(id), scene TEXT NOT NULL,
            template_id TEXT NOT NULL CHECK(length(template_id) <= 128), sign_name TEXT NOT NULL,
            parameter_mapping TEXT NOT NULL, enabled INTEGER NOT NULL, version INTEGER NOT NULL,
            UNIQUE(channel_id, scene))');
        $pdo->exec('CREATE TABLE sms_routes (scene TEXT PRIMARY KEY, channel_id INTEGER NOT NULL,
            enabled INTEGER NOT NULL, version INTEGER NOT NULL,
            FOREIGN KEY(channel_id, scene) REFERENCES sms_templates(channel_id, scene))');
        $this->connection = new Connection($pdo);
        $resolver = $this->createMock(ConnectionResolverInterface::class);
        $resolver->method('connection')->willReturn($this->connection);
        $container = new Container(new DefinitionSource([]));
        $container->set(ConnectionResolverInterface::class, $resolver);
        $container->set(Db::class, new Db($container));
        ApplicationContext::setContainer($container);
        $cipher = new SmsCredentialCipher(new Config([
            'sms' => ['credentials_key' => base64_encode(random_bytes(32))],
        ]));
        $this->writer = new SmsConfigWriter($cipher);
        $this->publisher = new SmsRoutePublisher($cipher);
        $this->repository = new DatabaseSmsConfigRepository($cipher);
    }

    protected function tearDown(): void
    {
        ApplicationContext::setContainer($this->originalContainer);
    }

    public function testCreateEditAndSwitchReadFreshSnapshots(): void
    {
        $id = $this->createAliyun();
        $this->publisher->switchChannel('login_code', $id, 0);
        $old = $this->repository->forScene('login_code');
        self::assertSame('route:1/channel:1/template:1', $old->revision);
        $ciphertext = $this->connection->table('sms_channels')->where('id', $id)->value('credentials_ciphertext');
        self::assertStringNotContainsString('fake-secret', $ciphertext);
        $this->writer->updateChannel($id, 1);
        self::assertSame($ciphertext, $this->connection->table('sms_channels')->where('id', $id)->value('credentials_ciphertext'));
        $this->writer->updateChannel($id, 2, ['access_key_id' => 'new-id', 'access_key_secret' => 'new-secret']);
        $this->writer->updateTemplate($id, 1, 'SMS_NEW', '新签名', ['verify' => 'code']);
        $updated = $this->repository->forScene('login_code');
        self::assertSame('route:1/channel:3/template:2', $updated->revision);
        self::assertSame('new-secret', $updated->credentials['access_key_secret']);
        self::assertSame('SMS_NEW', $updated->templateId);
        self::assertSame(['verify' => 'code'], $updated->parameterMapping);
        self::assertSame('SMS_TEST', $old->templateId);
        $other = $this->writer->createChannel(
            '腾讯测试',
            'tencent',
            ['secret_id' => 'fake-id', 'secret_key' => 'fake-secret'],
            ['sdk_app_id' => 'fake-app', 'region' => 'ap-guangzhou'],
            '1234',
            '测试签名',
            ['code']
        );
        $this->publisher->switchChannel('login_code', $other, 1);
        self::assertSame('tencent', $this->repository->forScene('login_code')->provider);
        self::assertSame('route:2/channel:1/template:1', $this->repository->forScene('login_code')->revision);
    }

    public function testConflictsAndInvalidConfigurationLeaveDataUnchanged(): void
    {
        $id = $this->createAliyun();
        $this->publisher->switchChannel('login_code', $id, 0);
        $before = $this->state();
        foreach ([
            fn () => $this->writer->updateChannel($id, 99),
            fn () => $this->writer->updateTemplate($id, 99, 'OTHER', '签名', ['code' => 'code']),
            fn () => $this->writer->updateChannel($id, 1, []),
            fn () => $this->publisher->switchChannel('login_code', $id, 99),
            fn () => $this->publisher->switchChannel('login_code', $id, 0),
            fn () => $this->publisher->switchChannel('login_code', 999, 1),
        ] as $operation) {
            try {
                $operation();
                self::fail('预期拒绝无效写入');
            } catch (RuntimeException) {
                self::assertSame($before, $this->state());
            }
        }
    }

    public function testTemplateInsertFailureRollsBackChannelCreation(): void
    {
        try {
            $this->writer->createChannel(
                '失败渠道',
                'aliyun',
                ['access_key_id' => 'fake-id', 'access_key_secret' => 'fake-secret'],
                [],
                str_repeat('x', 129),
                '签名',
                ['code' => 'code']
            );
            self::fail('预期数据库拒绝过长模板');
        } catch (RuntimeException) {
            self::assertSame(0, $this->connection->table('sms_channels')->count());
            self::assertSame(0, $this->connection->table('sms_templates')->count());
        }
    }

    public function testOuterTransactionRollsBackCredentialChangeWhenTemplateConflicts(): void
    {
        $id = $this->createAliyun();
        $before = $this->state();
        try {
            Db::transaction(function () use ($id) {
                $this->writer->updateChannel($id, 1, ['access_key_id' => 'new', 'access_key_secret' => 'new']);
                $this->writer->updateTemplate($id, 99, 'OTHER', '签名', ['code' => 'code']);
            });
            self::fail('预期版本冲突');
        } catch (RuntimeException $exception) {
            self::assertSame('模板版本冲突，请刷新后重试', $exception->getMessage());
            self::assertSame($before, $this->state());
        }
    }

    public function testDisabledConfigurationCannotBePublishedOrRead(): void
    {
        $id = $this->createAliyun();
        $this->publisher->switchChannel('login_code', $id, 0);
        foreach (['sms_channels', 'sms_templates', 'sms_routes'] as $table) {
            $this->connection->table($table)->update(['enabled' => 0]);
            try {
                $this->repository->forScene('login_code');
                self::fail('预期禁用配置被拒绝');
            } catch (RuntimeException $exception) {
                self::assertSame('该场景没有有效短信配置', $exception->getMessage());
            }
            if ($table !== 'sms_routes') {
                try {
                    $this->publisher->switchChannel('login_code', $id, 1);
                    self::fail('预期禁用目标不可发布');
                } catch (RuntimeException $exception) {
                    self::assertSame('目标渠道或模板不可用', $exception->getMessage());
                }
            }
            $this->connection->table($table)->update(['enabled' => 1]);
        }
    }

    private function createAliyun(): int
    {
        return $this->writer->createChannel(
            '阿里云测试',
            'aliyun',
            ['access_key_id' => 'fake-id', 'access_key_secret' => 'fake-secret'],
            [],
            'SMS_TEST',
            '测试签名',
            ['code' => 'code']
        );
    }

    private function state(): string
    {
        return json_encode(array_map(
            fn (string $table) => $this->connection->table($table)->get()->all(),
            ['sms_channels', 'sms_templates', 'sms_routes']
        ), JSON_THROW_ON_ERROR);
    }
}
