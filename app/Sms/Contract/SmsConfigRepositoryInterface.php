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

use App\Sms\Config\SmsConfigSnapshot;

interface SmsConfigRepositoryInterface
{
    public function forScene(string $scene): SmsConfigSnapshot;
}
