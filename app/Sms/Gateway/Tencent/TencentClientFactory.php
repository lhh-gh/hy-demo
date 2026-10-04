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

namespace App\Sms\Gateway\Tencent;

use App\Sms\Config\SmsConfigSnapshot;
use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Sms\V20210111\SmsClient;

/** 根据本次快照创建客户端，不缓存凭据。 */
class TencentClientFactory
{
    public function create(SmsConfigSnapshot $config): SmsClient
    {
        $http = new HttpProfile();
        $http->setEndpoint('sms.tencentcloudapi.com');
        $http->setReqTimeout(5);
        $profile = new ClientProfile();
        $profile->setHttpProfile($http);
        return new SmsClient(new Credential(
            $config->credentials['secret_id'],
            $config->credentials['secret_key'],
        ), $config->options['region'], $profile);
    }
}
