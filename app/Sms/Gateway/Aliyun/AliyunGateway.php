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

namespace App\Sms\Gateway\Aliyun;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;
use App\Sms\Config\SmsConfigSnapshot;
use App\Sms\Config\SmsConfigValidator;
use App\Sms\Contract\SmsGatewayInterface;
use App\Sms\Exception\SmsSendException;
use App\Sms\Message\LoginCodeInput;
use App\Sms\Message\SmsSendResult;
use App\Sms\Message\SmsSendStatus;
use RuntimeException;
use Throwable;

class AliyunGateway implements SmsGatewayInterface
{
    public function __construct(private AliyunClientFactory $clients)
    {
    }

    public function sendCode(SmsConfigSnapshot $config, string $mobile, string $code): SmsSendResult
    {
        new LoginCodeInput($mobile, $code);
        SmsConfigValidator::validate($config);
        if ($config->provider !== 'aliyun') {
            throw new RuntimeException('短信适配器与平台不匹配');
        }
        $parameters = [];
        foreach ($config->parameterMapping as $name => $field) {
            $parameters[$name] = ['code' => $code][$field];
        }
        try {
            $client = $this->clients->create($config);
            $response = $client->sendSms(new SendSmsRequest([
                'phoneNumbers' => $mobile,
                'signName' => $config->signName,
                'templateCode' => $config->templateId,
                'templateParam' => json_encode($parameters, JSON_THROW_ON_ERROR),
            ]));
        } catch (Throwable) {
            throw new SmsSendException(new SmsSendResult(SmsSendStatus::Unknown, 'aliyun'));
        }
        $status = $response->body?->code;
        $requestId = $response->body?->requestId;
        if ($status === null || $status === '') {
            throw new SmsSendException(new SmsSendResult(SmsSendStatus::Unknown, 'aliyun', $requestId));
        }
        if ($status !== 'OK') {
            throw new SmsSendException(new SmsSendResult(SmsSendStatus::Rejected, 'aliyun', $requestId, $status));
        }
        return new SmsSendResult(SmsSendStatus::Accepted, 'aliyun', $requestId);
    }
}
