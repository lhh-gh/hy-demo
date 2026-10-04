<?php
declare(strict_types=1);
namespace App\Service;

use App\Contract\SmsSenderInterface;
use InvalidArgumentException;

class LoginSmsService
{
    public function __construct(private SmsSenderInterface $sender)
    {
    }

    public function sendLoginCode(string $mobile, string $code): void
    {
        if (! preg_match('/^1[3-9]\d{9}$/', $mobile)) {
            throw new InvalidArgumentException('手机号格式不正确');
        }

        if (! preg_match('/^\d{6}$/', $code)) {
            throw new InvalidArgumentException('验证码必须是 6 位数字');
        }

        $this->sender->sendCode($mobile, $code);
    }
}
