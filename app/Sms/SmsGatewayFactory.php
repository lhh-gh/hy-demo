<?php

declare(strict_types=1);

namespace App\Sms;

use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * 平台工厂类
 */
class SmsGatewayFactory
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function forProvider(string $provider): SmsGatewayInterface
    {
        $class = match ($provider) {
            'aliyun' => AliyunGateway::class,
            'tencent' => TencentGateway::class,
            default => throw new RuntimeException('不支持的短信平台：' . $provider),
        };

        return $this->container->get($class);
    }
}
