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

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;
use App\Sms\Contract\SmsSenderInterface;
use App\Sms\Exception\SmsSendException;
use App\Sms\Message\LoginCodeInput;
use App\Sms\Message\SmsSendResult;
use App\Sms\Message\SmsSendStatus;
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

    public function sendCode(string $mobile, string $code): SmsSendResult
    {
        new LoginCodeInput($mobile, $code);
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
