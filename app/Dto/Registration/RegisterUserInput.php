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

namespace App\Dto\Registration;

use App\Exception\Registration\RegistrationException;

final readonly class RegisterUserInput
{
    private function __construct(public string $email, public string $password)
    {
    }

    public static function fromArray(array $data): self
    {
        if (! is_string($data['email'] ?? null) || ! is_string($data['password'] ?? null)) {
            throw RegistrationException::invalidInput('email 和 password 必须是字符串');
        }

        // 本 Demo 约定邮箱大小写不敏感；密码不做 trim。
        $email = strtolower(trim($data['email']));
        $password = $data['password'];
        if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw RegistrationException::invalidInput('邮箱格式不正确');
        }
        // 使用 bcrypt，明确限制为 8～72 字节，避免静默截断。
        if (strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")) {
            throw RegistrationException::invalidInput('密码长度必须为 8～72 字节，且不能包含空字节');
        }

        return new self($email, $password);
    }
}
