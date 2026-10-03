<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;

use function Hyperf\Support\env;

// ① 让 Hyperf 自动发现并注册命令
#[Command]
class EnvInfoCommand extends HyperfCommand
{
    // ② 通过构造函数定义命令名称
    public function __construct()
    {
        parent::__construct('app:env');
    }

    // ③ 配置命令说明
    public function configure(): void
    {
        parent::configure();

        $this->setDescription('输出 PHP、Swoole 版本及当前运行环境');
    }

    // ④ 执行命令时调用，负责输出环境信息
    public function handle(): void
    {
        $swooleVersion = phpversion('swoole');
        $appEnv = (string) env('APP_ENV', '未设置');

        $this->line('PHP 版本：' . PHP_VERSION);
        $this->line('Swoole 版本：' . ($swooleVersion ?: '未加载'));
        $this->line('当前环境：' . $appEnv);
    }
}