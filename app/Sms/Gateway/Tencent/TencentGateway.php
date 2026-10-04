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

namespace App\Sms\Gateway\Tencent;

use App\Sms\Config\SmsConfigSnapshot;
use App\Sms\Config\SmsConfigValidator;
use App\Sms\Contract\SmsGatewayInterface;
use App\Sms\Exception\SmsSendException;
use App\Sms\Message\LoginCodeInput;
use App\Sms\Message\SmsSendResult;
use App\Sms\Message\SmsSendStatus;
use RuntimeException;
use TencentCloud\Sms\V20210111\Models\SendSmsRequest;
use Throwable;

class TencentGateway implements SmsGatewayInterface
{
    public function __construct(private TencentClientFactory $clients)
    {
    }

    public function sendCode(SmsConfigSnapshot $config, string $mobile, string $code): SmsSendResult
    {
        new LoginCodeInput($mobile, $code);
        SmsConfigValidator::validate($config);
        if ($config->provider !== 'tencent') {
            throw new RuntimeException('短信适配器与平台不匹配');
        }
        $parameters = [];
        foreach ($config->parameterMapping as $field) {
            $parameters[] = ['code' => $code][$field];
        }
        try {
            $client = $this->clients->create($config);
            $request = new SendSmsRequest();
            $request->fromJsonString(json_encode([
                // 公共输入约束已验证为大陆 11 位手机号。
                'PhoneNumberSet' => ['+86' . $mobile],
                'SmsSdkAppId' => $config->options['sdk_app_id'],
                'SignName' => $config->signName,
                'TemplateId' => $config->templateId,
                'TemplateParamSet' => $parameters,
            ], JSON_THROW_ON_ERROR));
            $response = $client->SendSms($request);
        } catch (Throwable) {
            throw new SmsSendException(new SmsSendResult(SmsSendStatus::Unknown, 'tencent'));
        }
        $statuses = $response->SendStatusSet ?? [];
        $requestId = $response->RequestId ?? null;
        if (count($statuses) !== 1 || empty($statuses[0]->Code)) {
            throw new SmsSendException(new SmsSendResult(SmsSendStatus::Unknown, 'tencent', $requestId));
        }
        if ($statuses[0]->Code !== 'Ok') {
            throw new SmsSendException(new SmsSendResult(SmsSendStatus::Rejected, 'tencent', $requestId, $statuses[0]->Code));
        }
        return new SmsSendResult(SmsSendStatus::Accepted, 'tencent', $requestId);
    }
}
