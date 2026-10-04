<?php

declare(strict_types=1);

namespace App\Factory;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use Darabonba\OpenApi\Models\Config;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

class AliyunSmsClientFactory
{
    public function __invoke(
        ContainerInterface $container,
        array $parameters = []
    ): Dysmsapi {
        $options = $container->get(ConfigInterface::class)
            ->get('sms.aliyun');

        foreach (['access_key_id', 'access_key_secret', 'endpoint'] as $key) {
            if (! is_array($options) || ! is_string($options[$key] ?? null)
                || trim($options[$key]) === '') {
                throw new \RuntimeException('阿里云短信配置缺失：' . $key);
            }
        }
        $config = new Config([
            'accessKeyId' => $options['access_key_id'],
            'accessKeySecret' => $options['access_key_secret'],
            'endpoint' => $options['endpoint'],
            'connectTimeout' => 3000,
            'readTimeout' => 5000,
        ]);

        return new Dysmsapi($config);
    }
}