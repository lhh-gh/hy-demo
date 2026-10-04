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
use function Hyperf\Support\env;

return [
    'aliyun' => [
        'access_key_id' => env('ALIYUN_ACCESS_KEY_ID', ''),
        'access_key_secret' => env('ALIYUN_ACCESS_KEY_SECRET', ''),
        'sign_name' => env('ALIYUN_SMS_SIGN_NAME', ''),
        'template_code' => env('ALIYUN_SMS_TEMPLATE_CODE', ''),
        'endpoint' => 'dysmsapi.aliyuncs.com',
    ],
    'credentials_key' => env('SMS_CREDENTIALS_KEY', ''),
];
