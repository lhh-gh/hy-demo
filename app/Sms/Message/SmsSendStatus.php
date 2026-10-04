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

enum SmsSendStatus: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Unknown = 'unknown';
    case Simulated = 'simulated';
}
