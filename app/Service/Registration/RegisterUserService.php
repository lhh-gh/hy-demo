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

namespace App\Service\Registration;

use App\Dto\Registration\RegisteredUser;
use App\Dto\Registration\RegisterUserInput;
use App\Event\Registration\UserRegistered;
use App\Exception\Registration\RegistrationException;
use App\Repository\Registration\UserRepositoryInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

final class RegisterUserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly EventDispatcherInterface $events,
        private readonly StdoutLoggerInterface $logger,
    ) {
    }

    public function register(RegisterUserInput $input): RegisteredUser
    {
        if ($this->users->existsByEmail($input->email)) {
            throw RegistrationException::emailTaken();
        }

        $hash = password_hash($input->password, PASSWORD_BCRYPT, ['cost' => 10]);
        // existsByEmail 只负责提前提示，create 的唯一约束才是并发兜底。
        $user = $this->users->create($input->email, $hash);

        try {
            $this->events->dispatch(new UserRegistered($user->id));
        } catch (Throwable $exception) {
            // 用户已持久化。演示性日志事件失败不改变注册结果，也不记录异常中的敏感参数。
            try {
                $this->logger->warning('注册事件处理失败', [
                    'user_id' => $user->id,
                    'exception_type' => $exception::class,
                ]);
            } catch (Throwable) {
                // 日志设施故障也不应把已经完成的注册变成接口失败。
            }
        }

        return $user;
    }
}
