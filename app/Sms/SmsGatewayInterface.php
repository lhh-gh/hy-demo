<?php

declare(strict_types=1);

namespace App\Sms;
/**
 *  平台适配器
 */
interface SmsGatewayInterface
{
    public function sendCode(
        SmsConfigSnapshot $config,
        string $mobile,
        string $code
    ): void;
}
