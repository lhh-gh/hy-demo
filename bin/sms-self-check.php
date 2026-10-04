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
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Service\LoginSmsService;
use App\Sms\Config\SmsConfigSnapshot;
use App\Sms\Config\SmsConfigValidator;
use App\Sms\Config\SmsCredentialCipher;
use App\Sms\Contract\SmsConfigRepositoryInterface;
use App\Sms\Contract\SmsGatewayInterface;
use App\Sms\Contract\SmsSenderInterface;
use App\Sms\Gateway\SmsGatewayFactory;
use App\Sms\Message\SmsSendResult;
use App\Sms\Message\SmsSendStatus;
use App\Sms\Sender\RoutingSmsSender;
use Hyperf\Config\Config;

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$repository = new class implements SmsConfigRepositoryInterface {
    public int $reads = 0;

    public function forScene(string $scene): SmsConfigSnapshot
    {
        check($scene === 'login_code', '场景错误');
        ++$this->reads;
        return new SmsConfigSnapshot(
            $this->reads,
            $this->reads === 1 ? 'aliyun' : 'tencent',
            'revision:' . $this->reads,
            [],
            [],
            'demo',
            'demo',
            ['code'],
        );
    }
};
$gateway = new class implements SmsGatewayInterface {
    public array $calls = [];

    public function sendCode(SmsConfigSnapshot $config, string $mobile, string $code): SmsSendResult
    {
        $this->calls[] = [$config, $mobile, $code];
        return new SmsSendResult(SmsSendStatus::Accepted, $config->provider);
    }
};
$factory = new class($gateway) extends SmsGatewayFactory {
    public array $providers = [];

    public function __construct(private SmsGatewayInterface $gateway)
    {
    }

    public function forProvider(string $provider): SmsGatewayInterface
    {
        $this->providers[] = $provider;
        return $this->gateway;
    }
};
$sender = new RoutingSmsSender($repository, $factory);
$receipt = $sender->sendCode('13800138000', '123456');
check($receipt->status === SmsSendStatus::Accepted && $receipt->provider === 'aliyun', '路由未透传回执');
$sender->sendCode('13900139000', '654321');
check($repository->reads === 2, '没有逐次读取配置');
check($factory->providers === ['aliyun', 'tencent'], '平台未按新快照选择');
check($gateway->calls[0][0]->revision === 'revision:1', '旧快照被改变');
check($gateway->calls[1][1] === '13900139000', '手机号串用');
check($gateway->calls[0][2] === '123456' && $gateway->calls[1][2] === '654321', '验证码串用');

$cipher = new SmsCredentialCipher(new Config([
    'sms' => ['credentials_key' => base64_encode(random_bytes(32))],
]));
$plain = ['access_key_id' => 'fake-id', 'access_key_secret' => 'fake-secret'];
$encrypted = $cipher->encrypt($plain);
check($cipher->decrypt($encrypted) === $plain, '加密往返失败');
check($cipher->encrypt($plain) !== $encrypted, '未使用随机 nonce');
$raw = base64_decode(substr($encrypted, 3), true);
$raw[28] = chr(ord($raw[28]) ^ 1);
$rejected = false;
try {
    $cipher->decrypt('v1:' . base64_encode($raw));
} catch (RuntimeException) {
    $rejected = true;
}
check($rejected, '未拒绝篡改密文');

function rejects(callable $operation, string $message): void
{
    $failed = false;
    try {
        $operation();
    } catch (InvalidArgumentException|RuntimeException) {
        $failed = true;
    }
    check($failed, $message);
}

$makeConfig = static fn (string $provider, array $credentials, array $mapping, array $options = []) => new SmsConfigSnapshot(1, $provider, 'test', $credentials, $options, 'template', 'sign', $mapping);
$valid = $makeConfig('aliyun', $plain, ['code' => 'code']);
SmsConfigValidator::validate($valid);
SmsConfigValidator::validate($makeConfig(
    'tencent',
    ['secret_id' => 'fake', 'secret_key' => 'fake'],
    ['code'],
    ['sdk_app_id' => 'fake', 'region' => 'ap-guangzhou']
));
rejects(
    fn () => SmsConfigValidator::validate($makeConfig('qiniu', $plain, ['code'])),
    '未拒绝未实现平台'
);
rejects(
    fn () => SmsConfigValidator::validate($makeConfig('aliyun', [], ['code' => 'code'])),
    '未拒绝缺失凭据'
);
rejects(
    fn () => SmsConfigValidator::validate($makeConfig('aliyun', $plain, ['code'])),
    '未拒绝错误映射形状'
);
rejects(
    fn () => SmsConfigValidator::validate($makeConfig('aliyun', $plain, ['code' => 'password'])),
    '未拒绝未知业务字段'
);
$wrongCipher = new SmsCredentialCipher(new Config([
    'sms' => ['credentials_key' => base64_encode(random_bytes(32))],
]));
rejects(fn () => $wrongCipher->decrypt($encrypted), '未拒绝错误解密密钥');

$businessSender = new class implements SmsSenderInterface {
    public int $calls = 0;

    public function sendCode(string $mobile, string $code): SmsSendResult
    {
        ++$this->calls;
        throw new RuntimeException('模拟平台拒绝');
    }
};
$business = new LoginSmsService($businessSender);
rejects(fn () => $business->sendLoginCode("13800138000\n", '123456'), '未拒绝手机号末尾换行');
rejects(fn () => $business->sendLoginCode('13800138000', "123456\n"), '未拒绝验证码末尾换行');
check($businessSender->calls === 0, '无效输入仍然调用了发送器');
rejects(fn () => $business->sendLoginCode('13800138000', '123456'), '吞掉了平台异常');
check($businessSender->calls === 1, '有效输入没有进入发送器');
echo "PASS: routing, validation and credential encryption\n";
