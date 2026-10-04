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

namespace App\Sms\Gateway\Aliyun;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use App\Sms\Config\SmsConfigSnapshot;
use Darabonba\OpenApi\Models\Config;

/** 根据本次快照创建客户端，不缓存凭据。 */
class AliyunClientFactory
{
    public function create(SmsConfigSnapshot $config): Dysmsapi
    {
        return new Dysmsapi(new Config([
            'accessKeyId' => $config->credentials['access_key_id'],
            'accessKeySecret' => $config->credentials['access_key_secret'],
            'endpoint' => 'dysmsapi.aliyuncs.com',
            'connectTimeout' => 3000,
            'readTimeout' => 5000,
        ]));
    }
}
