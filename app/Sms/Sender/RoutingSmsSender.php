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

namespace App\Sms\Sender;

use App\Sms\Contract\SmsConfigRepositoryInterface;
use App\Sms\Contract\SmsSenderInterface;
use App\Sms\Gateway\SmsGatewayFactory;
use App\Sms\Message\LoginCodeInput;
use App\Sms\Message\SmsSendResult;

class RoutingSmsSender implements SmsSenderInterface
{
    public function __construct(
        private SmsConfigRepositoryInterface $configs,
        private SmsGatewayFactory $gateways
    ) {
    }

    public function sendCode(string $mobile, string $code): SmsSendResult
    {
        new LoginCodeInput($mobile, $code);
        $config = $this->configs->forScene('login_code');
        $gateway = $this->gateways->forProvider($config->provider);
        return $gateway->sendCode($config, $mobile, $code);
    }
}
