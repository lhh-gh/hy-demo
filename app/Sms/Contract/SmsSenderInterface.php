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

namespace App\Sms\Contract;

use App\Sms\Exception\SmsSendException;
use App\Sms\Message\SmsSendResult;

interface SmsSenderInterface
{
    /**
     * 返回受理或模拟回执（不代表送达）；失败抛出 SmsSendException。
     * 无效输入和配置错误在发送前拒绝。
     * @throws SmsSendException
     */
    public function sendCode(string $mobile, string $code): SmsSendResult;
}
