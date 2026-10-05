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

namespace App\Exception\Registration;

use App\Exception\BusinessException;

final class RegistrationException extends BusinessException
{
    private function __construct(public readonly int $httpStatus, int $code, string $message)
    {
        parent::__construct($code, $message);
    }

    public static function invalidInput(string $message): self
    {
        return new self(422, 42201, $message);
    }

    public static function emailTaken(): self
    {
        return new self(409, 40901, '该邮箱已注册');
    }
}
