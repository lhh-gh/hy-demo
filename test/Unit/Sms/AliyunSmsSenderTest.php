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

namespace HyperfTest\Unit\Sms;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsResponse;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsResponseBody;
use App\Sms\AliyunSmsSender;
use Hyperf\Config\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 * @coversNothing
 */
final class AliyunSmsSenderTest extends TestCase
{
    public function testSuccessfulRequestContainsExpectedParameters(): void
    {
        $client = $this->createMock(Dysmsapi::class);
        $client->expects(self::once())->method('sendSms')->with(
            self::callback(static function (SendSmsRequest $request): bool {
                self::assertSame('13800138000', $request->phoneNumbers);
                self::assertSame('测试签名', $request->signName);
                self::assertSame('SMS_TEST', $request->templateCode);
                self::assertSame(['code' => '012345'], json_decode($request->templateParam, true, 512, JSON_THROW_ON_ERROR));
                return true;
            })
        )->willReturn(new SendSmsResponse(['body' => new SendSmsResponseBody(['code' => 'OK'])]));

        (new AliyunSmsSender($client, $this->config()))->sendCode('13800138000', '012345');
    }

    #[DataProvider('failedResponses')]
    public function testFailedResponseThrows(?string $code, string $message): void
    {
        $client = $this->createMock(Dysmsapi::class);
        $response = new SendSmsResponse();
        $response->body = $code === null ? null : new SendSmsResponseBody(['code' => $code]);
        $client->expects(self::once())->method('sendSms')->willReturn($response);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('短信发送请求失败：' . $message);

        (new AliyunSmsSender($client, $this->config()))->sendCode('13800138000', '123456');
    }

    public static function failedResponses(): array
    {
        return [
            '业务失败' => ['isv.BUSINESS_LIMIT_CONTROL', 'isv.BUSINESS_LIMIT_CONTROL'],
            '空响应体' => [null, 'EMPTY_RESPONSE'],
        ];
    }

    #[DataProvider('missingConfiguration')]
    public function testMissingConfigurationDoesNotCallSdk(string $key): void
    {
        $config = $this->config();
        $config->set('sms.aliyun.' . $key, '');
        $client = $this->createMock(Dysmsapi::class);
        $client->expects(self::never())->method('sendSms');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('阿里云短信签名或模板未配置');

        (new AliyunSmsSender($client, $config))->sendCode('13800138000', '123456');
    }

    public static function missingConfiguration(): array
    {
        return [['sign_name'], ['template_code']];
    }

    public function testSdkExceptionIsPropagated(): void
    {
        $failure = new RuntimeException('连接超时');
        $client = $this->createMock(Dysmsapi::class);
        $client->expects(self::once())->method('sendSms')->willThrowException($failure);
        $this->expectExceptionObject($failure);

        (new AliyunSmsSender($client, $this->config()))->sendCode('13800138000', '123456');
    }

    private function config(): Config
    {
        return new Config(['sms' => ['aliyun' => [
            'sign_name' => '测试签名',
            'template_code' => 'SMS_TEST',
        ]]]);
    }
}
