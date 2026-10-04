<?php

declare(strict_types=1);

namespace App\Sms;

use App\Contract\SmsSenderInterface;

class RoutingSmsSender implements SmsSenderInterface
{
    public function __construct(
        private SmsConfigRepositoryInterface $configs,
        private SmsGatewayFactory $gateways
    ) {
    }

    public function sendCode(string $mobile, string $code): void
    {
        $config = $this->configs->forScene('login_code');
        $gateway = $this->gateways->forProvider($config->provider);
        $gateway->sendCode($config, $mobile, $code);
    }
}