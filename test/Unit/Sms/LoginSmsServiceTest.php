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
use App\Sms\Contract\SmsSenderInterface;
use App\Sms\Message\SmsSendResult;
use App\Sms\Message\SmsSendStatus;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 * @coversNothing
 */
final class LoginSmsServiceTest extends TestCase
{
    public function testValidCodeIsDelegatedToInterface(): void
    {
        $sender = $this->createMock(SmsSenderInterface::class);
        $receipt = new SmsSendResult(SmsSendStatus::Accepted, 'aliyun', 'request-id');
        $sender->expects(self::once())->method('sendCode')->with('13800138000', '012345')->willReturn($receipt);

        self::assertSame($receipt, (new LoginSmsService($sender))->sendLoginCode('13800138000', '012345'));
    }

    #[DataProvider('invalidInputs')]
    public function testInvalidInputDoesNotSend(string $mobile, string $code, string $message): void
    {
        $sender = $this->createMock(SmsSenderInterface::class);
        $sender->expects(self::never())->method('sendCode');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new LoginSmsService($sender))->sendLoginCode($mobile, $code);
    }

    public static function invalidInputs(): array
    {
        return [
            '手机号无效' => ['123', '123456', '手机号格式不正确'],
            '手机号末尾换行' => ["13800138000\n", '123456', '手机号格式不正确'],
            '验证码末尾换行' => ['13800138000', "123456\n", '验证码必须是 6 位数字'],
            '验证码不足六位' => ['13800138000', '12345', '验证码必须是 6 位数字'],
            '验证码超过六位' => ['13800138000', '1234567', '验证码必须是 6 位数字'],
            '验证码包含字母' => ['13800138000', '12345a', '验证码必须是 6 位数字'],
        ];
    }

    public function testSenderFailureIsPropagated(): void
    {
        $failure = new RuntimeException('短信服务不可用');
        $sender = $this->createMock(SmsSenderInterface::class);
        $sender->expects(self::once())->method('sendCode')->willThrowException($failure);
        $this->expectExceptionObject($failure);

        (new LoginSmsService($sender))->sendLoginCode('13800138000', '123456');
    }
}
