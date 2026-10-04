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

namespace App\Sms\Sender;

use App\Sms\Contract\SmsSenderInterface;
use App\Sms\Message\LoginCodeInput;
use App\Sms\Message\SmsSendResult;
use App\Sms\Message\SmsSendStatus;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

class LogSmsSender implements SmsSenderInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory->get('sms');
    }

    public function sendCode(string $mobile, string $code): SmsSendResult
    {
        new LoginCodeInput($mobile, $code);
        $this->logger->info('模拟发送短信验证码，未调用短信服务', [
            'mobile' => substr($mobile, 0, 3)
                . '****'
                . substr($mobile, -4),
            'code_length' => strlen($code),
        ]);
        return new SmsSendResult(SmsSendStatus::Simulated, 'log');
    }
}
