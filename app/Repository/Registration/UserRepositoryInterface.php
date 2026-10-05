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

namespace App\Repository\Registration;

use App\Dto\Registration\RegisteredUser;

interface UserRepositoryInterface
{
    public function existsByEmail(string $email): bool;

    /** 必须由持久化层保证邮箱唯一，冲突时抛出 RegistrationException。 */
    public function create(string $email, string $passwordHash): RegisteredUser;
}
