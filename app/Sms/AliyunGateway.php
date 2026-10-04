<?php

declare(strict_types=1);

namespace App\Sms;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;
use Darabonba\OpenApi\Models\Config;
use RuntimeException;
use Throwable;

class AliyunGateway implements SmsGatewayInterface
{
    public function sendCode(SmsConfigSnapshot $config, string $mobile, string $code): void
    {
        SmsConfigValidator::validate($config);
        if ($config->provider !== 'aliyun') {
            throw new RuntimeException('短信适配器与平台不匹配');
        }
        $parameters = [];
        foreach ($config->parameterMapping as $name => $field) {
            $parameters[$name] = ['code' => $code][$field];
        }
        try {
            $client = new Dysmsapi(new Config([
                'accessKeyId' => $config->credentials['access_key_id'],
                'accessKeySecret' => $config->credentials['access_key_secret'],
                'endpoint' => 'dysmsapi.aliyuncs.com',
                'connectTimeout' => 3000,
                'readTimeout' => 5000,
            ]));
            $response = $client->sendSms(new SendSmsRequest([
                'phoneNumbers' => $mobile,
                'signName' => $config->signName,
                'templateCode' => $config->templateId,
                'templateParam' => json_encode($parameters, JSON_THROW_ON_ERROR),
            ]));
        } catch (Throwable) {
            throw new RuntimeException('短信调用失败，受理结果待确认');
        }
        if ($response->body?->code !== 'OK') {
            throw new RuntimeException('短信平台拒绝受理');
        }
    }
}
