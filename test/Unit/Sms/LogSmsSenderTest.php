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

use App\Sms\LogSmsSender;
use Hyperf\Logger\LoggerFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @internal
 * @coversNothing
 */
final class LogSmsSenderTest extends TestCase
{
    public function testLogMasksMobileAndDoesNotContainCode(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(
            '模拟发送短信验证码，未调用短信服务',
            ['mobile' => '138****8000', 'code_length' => 6]
        );
        $factory = $this->createMock(LoggerFactory::class);
        $factory->expects(self::once())->method('get')->with('sms')->willReturn($logger);

        (new LogSmsSender($factory))->sendCode('13800138000', '012345');
    }
}
