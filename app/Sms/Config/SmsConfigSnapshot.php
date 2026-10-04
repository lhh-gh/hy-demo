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

namespace App\Sms\Config;

final readonly class SmsConfigSnapshot
{
    public function __construct(
        public int $channelId,
        public string $provider,
        public string $revision,
        public array $credentials,
        public array $options,
        public string $templateId,
        public string $signName,
        public array $parameterMapping,
    ) {
    }
}
