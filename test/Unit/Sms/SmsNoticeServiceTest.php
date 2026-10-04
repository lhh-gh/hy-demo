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

use App\Service\LoginSmsService;
use App\Service\SmsNoticeService;
use App\Sms\Contract\SmsSenderInterface;
use App\Sms\Message\SmsSendResult;
use App\Sms\Message\SmsSendStatus;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class SmsNoticeServiceTest extends TestCase
{
    #[DataProvider('invalidInputs')]
    public function testNoticeCannotBypassLoginValidation(string $mobile, string $code, string $message): void
    {
        $sender = $this->createMock(SmsSenderInterface::class);
        $sender->expects(self::never())->method('sendCode');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        (new SmsNoticeService(new LoginSmsService($sender)))->notify($mobile, $code);
    }

    public static function invalidInputs(): array
    {
        return LoginSmsServiceTest::invalidInputs();
    }

    public function testNoticePreservesReceipt(): void
    {
        $receipt = new SmsSendResult(SmsSendStatus::Accepted, 'tencent', 'request-id');
        $sender = $this->createMock(SmsSenderInterface::class);
        $sender->expects(self::once())->method('sendCode')->with('13800138000', '012345')->willReturn($receipt);
        self::assertSame($receipt, (new SmsNoticeService(new LoginSmsService($sender)))->notify('13800138000', '012345'));
    }
}
