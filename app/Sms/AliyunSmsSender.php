<?php

declare(strict_types=1);

namespace App\Sms;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;
use App\Contract\SmsSenderInterface;
use Hyperf\Contract\ConfigInterface;
use RuntimeException;

class AliyunSmsSender implements SmsSenderInterface
{
    public function __construct(
        private Dysmsapi $client,
        private ConfigInterface $config
    ) {
    }

    public function sendCode(string $mobile, string $code): void
    {
        $signName = (string) $this->config->get(
            'sms.aliyun.sign_name',
            ''
        );

        $templateCode = (string) $this->config->get(
            'sms.aliyun.template_code',
            ''
        );

        if ($signName === '' || $templateCode === '') {
            throw new RuntimeException('阿里云短信签名或模板未配置');
        }

        // 每次调用都创建独立的请求对象。
        $request = new SendSmsRequest([
            'phoneNumbers' => $mobile,
            'signName' => $signName,
            'templateCode' => $templateCode,
            'templateParam' => json_encode(
                ['code' => $code],
                JSON_THROW_ON_ERROR
            ),
        ]);

        $response = $this->client->sendSms($request);
        $body = $response->body;

        if ($body?->code !== 'OK') {
            throw new RuntimeException(
                '短信发送请求失败：'
                . ($body?->code ?? 'EMPTY_RESPONSE')
            );
        }
    }
}