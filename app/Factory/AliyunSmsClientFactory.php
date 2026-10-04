<?php

declare(strict_types=1);

namespace App\Factory;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use Darabonba\OpenApi\Models\Config;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

class AliyunSmsClientFactory
{
    public function __invoke(
        ContainerInterface $container,
        array $parameters = []
    ): Dysmsapi {
        $config = $container->get(ConfigInterface::class);

        $accessKeyId = (string) $config->get(
            'sms.aliyun.access_key_id',
            ''
        );

        $accessKeySecret = (string) $config->get(
            'sms.aliyun.access_key_secret',
            ''
        );

        if ($accessKeyId === '' || $accessKeySecret === '') {
            throw new RuntimeException('阿里云短信凭据未配置');
        }

        $sdkConfig = new Config([
            'accessKeyId' => $accessKeyId,
            'accessKeySecret' => $accessKeySecret,
            'endpoint' => 'dysmsapi.aliyuncs.com',
            'connectTimeout' => 3000,
            'readTimeout' => 5000,
        ]);

        return new Dysmsapi($sdkConfig);
    }
}