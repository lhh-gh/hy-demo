<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\SmsSenderInterface;
use Hyperf\Di\Annotation\Inject;

class SmsNoticeService
{
    #[Inject]
    protected SmsSenderInterface $sender;

    public function notify(string $mobile, string $code): void
    {
        $this->sender->sendCode($mobile, $code);
    }
}