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
use App\Exception\Registration\RegistrationException;
use Hyperf\Database\Exception\QueryException;
use Hyperf\DbConnection\Db;
use RuntimeException;

final class DatabaseUserRepository implements UserRepositoryInterface
{
    public function existsByEmail(string $email): bool
    {
        return Db::table('demo_registration_users')->where('email', $email)->exists();
    }

    public function create(string $email, string $passwordHash): RegisteredUser
    {
        try {
            $id = Db::table('demo_registration_users')->insertGetId([
                'email' => $email,
                'password_hash' => $passwordHash,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (QueryException $exception) {
            // 本 Demo 的 MySQL 表只有自增主键和邮箱唯一键；仅转换指定邮箱约束冲突。
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062
                && str_contains((string) ($exception->errorInfo[2] ?? ''), 'demo_registration_users_email_unique')) {
                throw RegistrationException::emailTaken();
            }
            // 不把含 SQL 绑定参数（邮箱、密码哈希）的异常交给默认日志处理器。
            throw new RuntimeException('注册数据写入失败');
        }

        return new RegisteredUser((int) $id, $email);
    }
}
