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

namespace App\Sms\Config;

use Hyperf\Contract\ConfigInterface;
use RuntimeException;

class SmsCredentialCipher
{
    public function __construct(private ConfigInterface $config)
    {
    }

    public function encrypt(array $credentials): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $encrypted = openssl_encrypt(
            json_encode($credentials, JSON_THROW_ON_ERROR),
            'aes-256-gcm',
            $this->key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            'sms-credentials:v1',
            16,
        );
        if ($encrypted === false) {
            throw new RuntimeException('短信凭据加密失败');
        }
        return 'v1:' . base64_encode($iv . $tag . $encrypted);
    }

    public function decrypt(string $ciphertext): array
    {
        $raw = str_starts_with($ciphertext, 'v1:')
            ? base64_decode(substr($ciphertext, 3), true) : false;
        if ($raw === false || strlen($raw) <= 28) {
            throw new RuntimeException('短信凭据密文格式无效');
        }
        $plain = openssl_decrypt(
            substr($raw, 28),
            'aes-256-gcm',
            $this->key(),
            OPENSSL_RAW_DATA,
            substr($raw, 0, 12),
            substr($raw, 12, 16),
            'sms-credentials:v1',
        );
        if ($plain === false) {
            throw new RuntimeException('短信凭据解密失败');
        }
        $data = json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data) || array_is_list($data)) {
            throw new RuntimeException('短信凭据必须为对象');
        }
        return $data;
    }

    private function key(): string
    {
        $key = base64_decode((string) $this->config->get('sms.credentials_key', ''), true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('短信凭据加密密钥配置无效');
        }
        return $key;
    }
}
