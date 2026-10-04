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

use RuntimeException;

class SmsConfigValidator
{
    public static function validate(SmsConfigSnapshot $config): void
    {
        $fields = match ($config->provider) {
            'aliyun' => ['access_key_id', 'access_key_secret'],
            'tencent' => ['secret_id', 'secret_key'],
            default => throw new RuntimeException('短信平台尚未实现'),
        };
        foreach ($fields as $field) {
            if (! is_string($config->credentials[$field] ?? null)
                || trim($config->credentials[$field]) === '') {
                throw new RuntimeException('短信凭据字段缺失：' . $field);
            }
        }
        if (trim($config->templateId) === '' || trim($config->signName) === '') {
            throw new RuntimeException('短信模板或签名为空');
        }
        if ($config->parameterMapping === []) {
            throw new RuntimeException('短信模板映射不能为空');
        }
        foreach ($config->parameterMapping as $value) {
            if ($value !== 'code') {
                throw new RuntimeException('登录模板仅支持 code 字段');
            }
        }
        if ($config->provider === 'aliyun') {
            foreach (array_keys($config->parameterMapping) as $key) {
                if (! is_string($key) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $key)) {
                    throw new RuntimeException('阿里云模板映射必须使用命名参数');
                }
            }
        } else {
            if (! array_is_list($config->parameterMapping)) {
                throw new RuntimeException('腾讯云模板映射必须为顺序数组');
            }
            foreach (['sdk_app_id', 'region'] as $field) {
                if (! is_string($config->options[$field] ?? null)
                    || trim($config->options[$field]) === '') {
                    throw new RuntimeException('腾讯云选项字段缺失：' . $field);
                }
            }
        }
    }
}
