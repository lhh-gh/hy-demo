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

use App\Sms\Message\SmsSendResult;

/** 兼容原通知入口；当前只支持登录验证码。 */
class SmsNoticeService
{
    public function __construct(private LoginSmsService $loginSms)
    {
    }

    public function notify(string $mobile, string $code): SmsSendResult
    {
        return $this->loginSms->sendLoginCode($mobile, $code);
    }
}
