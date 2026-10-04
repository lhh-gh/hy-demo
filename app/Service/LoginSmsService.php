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

use App\Sms\Contract\SmsSenderInterface;
use App\Sms\Message\LoginCodeInput;
use App\Sms\Message\SmsSendResult;

class LoginSmsService
{
    public function __construct(private SmsSenderInterface $sender)
    {
    }

    public function sendLoginCode(string $mobile, string $code): SmsSendResult
    {
        new LoginCodeInput($mobile, $code);

        return $this->sender->sendCode($mobile, $code);
    }
}
