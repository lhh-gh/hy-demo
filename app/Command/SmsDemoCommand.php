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

use App\Service\LoginSmsService;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use RuntimeException;

use function Hyperf\Support\env;

#[Command]
class SmsDemoCommand extends HyperfCommand
{
    public function __construct(private LoginSmsService $service)
    {
        parent::__construct('sms:demo');
    }

    public function handle(): void
    {
        if (! in_array(env('APP_ENV', 'dev'), ['dev', 'testing'], true)) {
            throw new RuntimeException('演示命令只允许 dev/testing 环境');
        }
        $this->service->sendLoginCode('13800138000', '123456');
        $this->line('模拟发送完成，请检查脱敏日志');
    }
}
