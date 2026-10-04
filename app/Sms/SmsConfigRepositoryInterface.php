<?php

declare(strict_types=1);

namespace App\Sms;

interface SmsConfigRepositoryInterface
{
    public function forScene(string $scene): SmsConfigSnapshot;
}