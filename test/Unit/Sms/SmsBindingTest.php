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
use App\Contract\SmsSenderInterface;
use App\Factory\AliyunSmsClientFactory;
use App\Service\LoginSmsService;
use App\Sms\AliyunSmsSender;
use App\Sms\LogSmsSender;
use Hyperf\Config\Config;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSource;
use Hyperf\Logger\LoggerFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * @internal
 * @coversNothing
 */
final class SmsBindingTest extends TestCase
{
    #[DataProvider('environments')]
    public function testEnvironmentResolvesExpectedSender(?string $environment, string $expected): void
    {
        $definitions = $this->definitions($environment);
        self::assertSame(AliyunSmsClientFactory::class, $definitions[Dysmsapi::class]);

        // 每个用例新建容器，使用假凭据构造 SDK，不调用发送接口。
        $container = new Container(new DefinitionSource($definitions));
        $container->set(ConfigInterface::class, new Config(['sms' => ['aliyun' => [
            'access_key_id' => 'test-key',
            'access_key_secret' => 'test-secret',
        ]]]));
        $loggerFactory = $this->createMock(LoggerFactory::class);
        $loggerFactory->method('get')->willReturn(new NullLogger());
        $container->set(LoggerFactory::class, $loggerFactory);

        self::assertInstanceOf($expected, $container->get(SmsSenderInterface::class));
        self::assertInstanceOf(LoginSmsService::class, $container->get(LoginSmsService::class));
    }

    public static function environments(): array
    {
        return [
            '开发' => ['dev', LogSmsSender::class],
            '测试' => ['testing', LogSmsSender::class],
            '生产' => ['prod', AliyunSmsSender::class],
            '未设置时默认开发' => [null, LogSmsSender::class],
        ];
    }

    public function testUnknownEnvironmentIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('未配置该环境的短信发送策略');
        $this->definitions('unknown');
    }

    private function definitions(?string $environment): array
    {
        $original = getenv('APP_ENV');
        try {
            putenv($environment === null ? 'APP_ENV' : 'APP_ENV=' . $environment);
            return require dirname(__DIR__, 3) . '/config/autoload/dependencies.php';
        } finally {
            putenv($original === false ? 'APP_ENV' : 'APP_ENV=' . $original);
        }
    }
}
