<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;

use function Hyperf\Support\env;

#[Command]
class EnvPropertyCommand extends HyperfCommand
{
    protected ?string $name = 'env:property';

    protected string $description = '通过 name 属性定义的环境信息命令';

    public function handle(): void
    {
        $this->line('PHP 版本：' . PHP_VERSION);
        $this->line('Swoole 版本：' . (phpversion('swoole') ?: '未加载'));
        $this->line('当前环境：' . (string) env('APP_ENV', '未设置'));
    }
}