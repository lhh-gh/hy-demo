<?php

declare(strict_types=1);

namespace App\Sms;

use RuntimeException;
use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Sms\V20210111\Models\SendSmsRequest;
use TencentCloud\Sms\V20210111\SmsClient;
use Throwable;

class TencentGateway implements SmsGatewayInterface
{
    public function sendCode(SmsConfigSnapshot $config, string $mobile, string $code): void
    {
        SmsConfigValidator::validate($config);
        if ($config->provider !== 'tencent') {
            throw new RuntimeException('短信适配器与平台不匹配');
        }
        $parameters = [];
        foreach ($config->parameterMapping as $field) {
            $parameters[] = ['code' => $code][$field];
        }
        try {
            $http = new HttpProfile();
            $http->setEndpoint('sms.tencentcloudapi.com');
            $http->setReqTimeout(5);
            $profile = new ClientProfile();
            $profile->setHttpProfile($http);
            $client = new SmsClient(new Credential(
                $config->credentials['secret_id'],
                $config->credentials['secret_key'],
            ), $config->options['region'], $profile);
            $request = new SendSmsRequest();
            $request->fromJsonString(json_encode([
                // LoginSmsService 已验证为大陆 11 位手机号。
                'PhoneNumberSet' => ['+86' . $mobile],
                'SmsSdkAppId' => $config->options['sdk_app_id'],
                'SignName' => $config->signName,
                'TemplateId' => $config->templateId,
                'TemplateParamSet' => $parameters,
            ], JSON_THROW_ON_ERROR));
            $response = $client->SendSms($request);
        } catch (Throwable) {
            throw new RuntimeException('短信调用失败，受理结果待确认');
        }
        $statuses = $response->SendStatusSet ?? [];
        if (count($statuses) !== 1 || $statuses[0]->Code !== 'Ok') {
            throw new RuntimeException('短信平台拒绝受理');
        }
    }
}
