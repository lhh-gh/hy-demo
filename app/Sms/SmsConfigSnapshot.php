<?php

declare(strict_types=1);

namespace App\Sms;

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