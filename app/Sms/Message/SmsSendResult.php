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

namespace App\Sms\Message;

/**
 * 厂商受理回执，不代表最终送达；不保存手机号、验证码或原始响应。
 */
final readonly class SmsSendResult
{
    public function __construct(
        public SmsSendStatus $status,
        public string $provider,
        public ?string $requestId = null,
        public ?string $errorCode = null,
    ) {
    }
}
