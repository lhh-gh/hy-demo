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
use App\Sms\Config\SmsConfigSnapshot;
use App\Sms\Exception\SmsSendException;
use App\Sms\Gateway\Aliyun\AliyunClientFactory;
use App\Sms\Gateway\Aliyun\AliyunGateway;
use App\Sms\Gateway\Tencent\TencentClientFactory;
use App\Sms\Gateway\Tencent\TencentGateway;
use App\Sms\Message\SmsSendStatus;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TencentCloud\Sms\V20210111\Models\SendSmsResponse as TencentResponse;
use TencentCloud\Sms\V20210111\Models\SendStatus;
use TencentCloud\Sms\V20210111\SmsClient;

/**
 * @internal
 * @coversNothing
 */
final class DynamicGatewayTest extends TestCase
{
    #[DataProvider('outcomes')]
    public function testProviderContract(string $provider, string $outcome, SmsSendStatus $expected): void
    {
        $config = $this->config($provider);
        if ($provider === 'aliyun') {
            $client = $this->createMock(Dysmsapi::class);
            $call = $client->expects(self::once())->method('sendSms')->with(self::callback(static function (SendSmsRequest $request): bool {
                self::assertSame('13800138000', $request->phoneNumbers);
                self::assertSame('template', $request->templateCode);
                self::assertSame('sign', $request->signName);
                self::assertSame(['verification' => '012345'], json_decode($request->templateParam, true));
                return true;
            }));
            $response = new SendSmsResponse(['body' => $outcome === 'empty' ? null : new SendSmsResponseBody([
                'code' => $outcome === 'accepted' ? 'OK' : 'isv.BUSINESS_LIMIT_CONTROL',
                'requestId' => 'request-id',
            ])]);
            $factory = $this->createMock(AliyunClientFactory::class);
            $factory->expects(self::once())->method('create')->with(self::identicalTo($config))->willReturn($client);
            $gateway = new AliyunGateway($factory);
        } else {
            $client = $this->createMock(SmsClient::class);
            $call = $client->expects(self::once())->method('__call')->with('SendSms', self::callback(static function (array $args): bool {
                $request = $args[0];
                self::assertSame(['+8613800138000'], $request->PhoneNumberSet);
                self::assertSame(['012345'], $request->TemplateParamSet);
                self::assertSame('template', $request->TemplateId);
                self::assertSame('sign', $request->SignName);
                self::assertSame('app-id', $request->SmsSdkAppId);
                return true;
            }));
            $status = new SendStatus();
            $status->Code = $outcome === 'accepted' ? 'Ok' : 'LimitExceeded.PhoneNumberDailyLimit';
            $response = new TencentResponse();
            $response->RequestId = 'request-id';
            $response->SendStatusSet = $outcome === 'empty' ? [] : [$status];
            $factory = $this->createMock(TencentClientFactory::class);
            $factory->expects(self::once())->method('create')->with(self::identicalTo($config))->willReturn($client);
            $gateway = new TencentGateway($factory);
        }
        if ($outcome === 'timeout') {
            $call->willThrowException(new RuntimeException('secret=fake-secret mobile=13800138000 code=012345'));
        } else {
            $call->willReturn($response);
        }

        try {
            $result = $gateway->sendCode($config, '13800138000', '012345');
            self::assertSame(SmsSendStatus::Accepted, $expected, '失败结果必须抛出异常');
        } catch (SmsSendException $exception) {
            self::assertNotSame(SmsSendStatus::Accepted, $expected);
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('fake-secret', $exception->getMessage());
            self::assertStringNotContainsString('012345', $exception->getMessage());
            $result = $exception->result;
        }
        self::assertSame($expected, $result->status);
        self::assertSame($provider, $result->provider);
        self::assertSame($outcome === 'timeout' || ($provider === 'aliyun' && $outcome === 'empty') ? null : 'request-id', $result->requestId);
        self::assertSame($expected === SmsSendStatus::Rejected
            ? ($provider === 'aliyun' ? 'isv.BUSINESS_LIMIT_CONTROL' : 'LimitExceeded.PhoneNumberDailyLimit')
            : null, $result->errorCode);
    }

    public static function outcomes(): iterable
    {
        foreach (['aliyun', 'tencent'] as $provider) {
            foreach (['accepted' => SmsSendStatus::Accepted, 'rejected' => SmsSendStatus::Rejected, 'empty' => SmsSendStatus::Unknown, 'timeout' => SmsSendStatus::Unknown] as $outcome => $status) {
                yield $provider . '-' . $outcome => [$provider, $outcome, $status];
            }
        }
    }

    public function testDirectGatewayRejectsInvalidInputBeforeCreatingClient(): void
    {
        $factory = $this->createMock(TencentClientFactory::class);
        $factory->expects(self::never())->method('create');
        $this->expectException(InvalidArgumentException::class);
        (new TencentGateway($factory))->sendCode($this->config('tencent'), "13800138000\n", '123456');
    }

    public function testFactoriesDoNotReuseClientsAcrossSnapshots(): void
    {
        foreach ([new AliyunClientFactory(), new TencentClientFactory()] as $factory) {
            $provider = $factory instanceof AliyunClientFactory ? 'aliyun' : 'tencent';
            self::assertNotSame($factory->create($this->config($provider)), $factory->create($this->config($provider)));
        }
    }

    private function config(string $provider): SmsConfigSnapshot
    {
        return new SmsConfigSnapshot(
            1,
            $provider,
            'revision-1',
            [
                'access_key_id' => 'fake-id', 'access_key_secret' => 'fake-secret',
                'secret_id' => 'fake-id', 'secret_key' => 'fake-secret',
            ],
            ['region' => 'ap-guangzhou', 'sdk_app_id' => 'app-id'],
            'template',
            'sign',
            $provider === 'aliyun' ? ['verification' => 'code'] : ['code']
        );
    }
}
