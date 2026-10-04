<?php

declare(strict_types=1);

namespace App\Sms;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;
use App\Contract\SmsSenderInterface;
use Hyperf\Contract\ConfigInterface;
use RuntimeException;
use Throwable;

class AliyunSmsSender implements SmsSenderInterface
{
    public function __construct(
        private Dysmsapi $client,
        private ConfigInterface $config,
    ) {
    }

    public function sendCode(string $mobile, string $code): void
    {
        $sign = (string) $this->config->get('sms.aliyun.sign_name', '');
        $template = (string) $this->config->get('sms.aliyun.template_code', '');
        if ($sign === '' || $template === '') {
            throw new RuntimeException('短信签名或模板未配置');
        }
        $request = new SendSmsRequest([
            'phoneNumbers' => $mobile,
            'signName' => $sign,
            'templateCode' => $template,
            'templateParam' => json_encode(['code' => $code], JSON_THROW_ON_ERROR),
        ]);
        try {
            $response = $this->client->sendSms($request);
        } catch (Throwable) {
            // 不向默认异常日志传播可能含请求内容的 SDK 原始异常。
            throw new RuntimeException('短信调用失败，受理结果待确认');
        }
        if ($response->body?->code !== 'OK') {
            throw new RuntimeException('短信平台拒绝受理');
        }
    }
}