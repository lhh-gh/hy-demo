<?php

declare(strict_types=1);

namespace App\Contract;

interface SmsSenderInterface
{
    /**
     * 发送验证码。
     *  接口只定义 :输入手机号、验证码 → 发起发送
     * 发送失败时抛出异常。
     */
    public function sendCode(string $mobile, string $code): void;
}