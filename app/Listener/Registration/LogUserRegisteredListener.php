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

namespace App\Listener\Registration;

use App\Event\Registration\UserRegistered;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;

#[Listener]
final class LogUserRegisteredListener implements ListenerInterface
{
    public function __construct(private readonly StdoutLoggerInterface $logger)
    {
    }

    public function listen(): array
    {
        return [UserRegistered::class];
    }

    public function process(object $event): void
    {
        if ($event instanceof UserRegistered) {
            $this->logger->info('分层 Demo：用户注册成功', ['user_id' => $event->userId]);
        }
    }
}
