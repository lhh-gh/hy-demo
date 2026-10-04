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

namespace App\Sms\Gateway;

use App\Sms\Contract\SmsGatewayInterface;
use App\Sms\Gateway\Aliyun\AliyunGateway;
use App\Sms\Gateway\Tencent\TencentGateway;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * 平台工厂类.
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
            default => throw new RuntimeException('短信平台尚未实现：' . $provider),
        };

        return $this->container->get($class);
    }
}
