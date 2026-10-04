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
        if (! preg_match('/^1[3-9]\d{9}$/D', $mobile)) {
            throw new InvalidArgumentException('手机号格式不正确');
        }

        if (! preg_match('/^\d{6}$/D', $code)) {
            throw new InvalidArgumentException('验证码必须是 6 位数字');
        }

        $this->sender->sendCode($mobile, $code);
    }
}
