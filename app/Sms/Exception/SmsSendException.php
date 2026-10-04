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

namespace App\Sms\Exception;

use App\Sms\Message\SmsSendResult;
use App\Sms\Message\SmsSendStatus;
use InvalidArgumentException;
use RuntimeException;

final class SmsSendException extends RuntimeException
{
    public function __construct(public readonly SmsSendResult $result)
    {
        if (! in_array($result->status, [SmsSendStatus::Rejected, SmsSendStatus::Unknown], true)) {
            throw new InvalidArgumentException('发送异常必须为拒绝或结果未知');
        }
        // 不附加 SDK 原始消息和 previous，防止凭据及请求正文进入日志。
        parent::__construct($result->status === SmsSendStatus::Rejected
            ? '短信平台拒绝受理'
            : '短信调用失败，受理结果待确认');
    }
}
