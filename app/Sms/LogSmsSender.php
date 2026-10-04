<?php

declare(strict_types=1);

namespace App\Sms;

use App\Contract\SmsSenderInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

class LogSmsSender implements SmsSenderInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory->get('sms');
    }

    public function sendCode(string $mobile, string $code): void
    {
        $this->logger->info('模拟发送短信验证码，未调用短信服务', [
            'mobile' => substr($mobile, 0, 3)
                . '****'
                . substr($mobile, -4),
            'code_length' => strlen($code),
        ]);
    }
}