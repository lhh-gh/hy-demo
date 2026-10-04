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

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use App\Service\LoginSmsService;
use App\Sms\Config\DatabaseSmsConfigRepository;
use App\Sms\Config\SmsConfigWriter;
use App\Sms\Config\SmsRoutePublisher;
use App\Sms\Contract\SmsConfigRepositoryInterface;
use App\Sms\Contract\SmsSenderInterface;
use App\Sms\Gateway\Aliyun\AliyunSmsClientFactory;
use App\Sms\Sender\LogSmsSender;
use App\Sms\Sender\RoutingSmsSender;
use Hyperf\Config\Config;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSource;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

$original = getenv('APP_ENV');
try {
    foreach (['dev' => LogSmsSender::class, 'testing' => LogSmsSender::class,
        'prod' => RoutingSmsSender::class] as $environment => $expected) {
        putenv('APP_ENV=' . $environment);
        $definitions = require dirname(__DIR__) . '/config/autoload/dependencies.php';
        if (($definitions[SmsSenderInterface::class] ?? null) !== $expected
            || ($definitions[Dysmsapi::class] ?? null) !== AliyunSmsClientFactory::class
            || ($definitions[SmsConfigRepositoryInterface::class] ?? null) !== DatabaseSmsConfigRepository::class) {
            throw new RuntimeException('环境绑定或 SDK 工厂绑定缺失：' . $environment);
        }
        $container = new Container(new DefinitionSource($definitions));
        $container->set(ConfigInterface::class, new Config(['sms' => ['aliyun' => [
            'access_key_id' => 'fake-id', 'access_key_secret' => 'fake-secret',
            'endpoint' => 'dysmsapi.aliyuncs.com',
        ]]]));
        $container->set(LoggerFactory::class, new class extends LoggerFactory {
            public function __construct()
            {
            }

            public function get(string $name = 'hyperf', ?string $channel = null): LoggerInterface
            {
                return new NullLogger();
            }
        });
        $sender = $container->get(SmsSenderInterface::class);
        foreach ([SmsConfigRepositoryInterface::class => DatabaseSmsConfigRepository::class,
            SmsConfigWriter::class => SmsConfigWriter::class,
            SmsRoutePublisher::class => SmsRoutePublisher::class,
            Dysmsapi::class => Dysmsapi::class] as $id => $implementation) {
            if (! $container->get($id) instanceof $implementation) {
                throw new RuntimeException('容器装配失败：' . $id);
            }
        }
        if (! $sender instanceof $expected
            || ! $container->get(LoginSmsService::class) instanceof LoginSmsService) {
            throw new RuntimeException('容器装配失败：' . $environment);
        }
        if ($sender !== $container->get(SmsSenderInterface::class)) {
            throw new RuntimeException('同一容器标识未复用实例');
        }
    }
} finally {
    putenv($original === false ? 'APP_ENV' : 'APP_ENV=' . $original);
}
echo "PASS: dynamic bindings, config services and legacy SDK factory\n";
