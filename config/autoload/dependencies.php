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
use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use App\Contract\SmsSenderInterface;
use App\Factory\AliyunSmsClientFactory;
use App\Sms\DatabaseSmsConfigRepository;
use App\Sms\LogSmsSender;
use App\Sms\RoutingSmsSender;
use App\Sms\SmsConfigRepositoryInterface;

use function Hyperf\Support\env;

return [
    Dysmsapi::class => AliyunSmsClientFactory::class,
    SmsConfigRepositoryInterface::class => DatabaseSmsConfigRepository::class,
    SmsSenderInterface::class => match (env('APP_ENV', 'dev')) {
        'dev', 'testing' => LogSmsSender::class,
        'prod' => RoutingSmsSender::class,
        default => throw new RuntimeException('未配置该环境的短信发送策略'),
    },
];
