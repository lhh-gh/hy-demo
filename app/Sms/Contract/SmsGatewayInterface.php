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

namespace App\Sms\Contract;

use App\Sms\Config\SmsConfigSnapshot;
use App\Sms\Message\SmsSendResult;

/**
 *  平台适配器.
 */
interface SmsGatewayInterface
{
    public function sendCode(
        SmsConfigSnapshot $config,
        string $mobile,
        string $code
    ): SmsSendResult;
}
