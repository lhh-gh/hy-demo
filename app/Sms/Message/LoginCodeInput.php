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

namespace App\Sms\Message;

use InvalidArgumentException;

/** 所有验证码发送入口共用的输入约束。 */
final readonly class LoginCodeInput
{
    public function __construct(public string $mobile, public string $code)
    {
        if (! preg_match('/^1[3-9]\d{9}$/D', $mobile)) {
            throw new InvalidArgumentException('手机号格式不正确');
        }
        if (! preg_match('/^\d{6}$/D', $code)) {
            throw new InvalidArgumentException('验证码必须是 6 位数字');
        }
    }
}
