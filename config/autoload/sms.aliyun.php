<?php

declare(strict_types=1);

use function Hyperf\Support\env;

// 短信配置示例，配置键为 sms.aliyun.*
return [
    'aliyun' => [
        'access_key_id' => env('ALIYUN_ACCESS_KEY_ID', ''),
        'access_key_secret' => env('ALIYUN_ACCESS_KEY_SECRET', ''),
        'sign_name' => env('ALIYUN_SMS_SIGN_NAME', ''),
        'template_code' => env('ALIYUN_SMS_TEMPLATE_CODE', ''),
    ],
];