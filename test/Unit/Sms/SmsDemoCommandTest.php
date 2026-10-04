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

use App\Command\SmsDemoCommand;
use App\Service\LoginSmsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 * @coversNothing
 */
final class SmsDemoCommandTest extends TestCase
{
    #[DataProvider('environments')]
    public function testEnvironmentGuard(string $environment, bool $allowed): void
    {
        $service = $this->createMock(LoginSmsService::class);
        $service->expects($allowed ? self::once() : self::never())->method('sendLoginCode')
            ->with('13800138000', '123456');
        $command = $this->getMockBuilder(SmsDemoCommand::class)->setConstructorArgs([$service])
            ->onlyMethods(['line'])->getMock();
        $command->expects($allowed ? self::once() : self::never())->method('line')
            ->with('模拟发送完成，请检查脱敏日志');
        $original = getenv('APP_ENV');
        try {
            putenv('APP_ENV=' . $environment);
            if (! $allowed) {
                $this->expectException(RuntimeException::class);
                $this->expectExceptionMessage('演示命令只允许 dev/testing 环境');
            }
            $command->handle();
        } finally {
            putenv($original === false ? 'APP_ENV' : 'APP_ENV=' . $original);
        }
    }

    public static function environments(): array
    {
        return [['dev', true], ['testing', true], ['prod', false], ['unknown', false]];
    }
}
