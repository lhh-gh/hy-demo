<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;

use function Hyperf\Support\env;

#[Command(
    name: 'env:attribute',
    description: '通过属性注解定义的环境信息命令',
)]
class EnvAttributeCommand extends HyperfCommand
{
    public function handle(): void
    {
        $this->line('PHP 版本：' . PHP_VERSION);
        $this->line('Swoole 版本：' . (phpversion('swoole') ?: '未加载'));
        $this->line('当前环境：' . (string) env('APP_ENV', '未设置'));
    }
}