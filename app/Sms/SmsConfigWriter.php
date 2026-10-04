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

namespace App\Sms;

use Hyperf\DbConnection\Db;
use InvalidArgumentException;
use RuntimeException;

class SmsConfigWriter
{
    public function __construct(private SmsCredentialCipher $cipher)
    {
    }

    public function createChannel(
        string $name,
        string $provider,
        array $credentials,
        array $options,
        string $templateId,
        string $signName,
        array $mapping,
    ): int {
        if (trim($name) === '' || mb_strlen($name) > 100) {
            throw new InvalidArgumentException('渠道名称必须为 1～100 个字符');
        }
        $this->validate($provider, $credentials, $options, $templateId, $signName, $mapping);
        $ciphertext = $this->cipher->encrypt($credentials);
        return Db::transaction(function () use (
            $name,
            $provider,
            $ciphertext,
            $options,
            $templateId,
            $signName,
            $mapping,
        ): int {
            $id = (int) Db::table('sms_channels')->insertGetId([
                'name' => $name, 'provider' => $provider,
                'credentials_ciphertext' => $ciphertext,
                'options' => json_encode((object) $options, JSON_THROW_ON_ERROR),
                'enabled' => 1, 'version' => 1,
            ]);
            Db::table('sms_templates')->insert([
                'channel_id' => $id, 'scene' => 'login_code',
                'template_id' => $templateId, 'sign_name' => $signName,
                'parameter_mapping' => json_encode($mapping, JSON_THROW_ON_ERROR),
                'enabled' => 1, 'version' => 1,
            ]);
            return $id;
        });
    }

    public function updateChannel(
        int $channelId,
        int $expectedVersion,
        ?array $credentials = null,
        ?array $options = null,
    ): void {
        Db::transaction(function () use ($channelId, $expectedVersion, $credentials, $options) {
            [$channel, $template] = $this->lockedPair($channelId);
            if ((int) $channel->version !== $expectedVersion) {
                throw new RuntimeException('渠道版本冲突，请刷新后重试');
            }
            $newCredentials = $credentials ?? $this->cipher->decrypt($channel->credentials_ciphertext);
            $newOptions = $options ?? json_decode($channel->options, true, 512, JSON_THROW_ON_ERROR);
            $mapping = json_decode($template->parameter_mapping, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($newOptions) || ! is_array($mapping)) {
                throw new RuntimeException('短信配置格式无效');
            }
            $this->validate(
                $channel->provider,
                $newCredentials,
                $newOptions,
                $template->template_id,
                $template->sign_name,
                $mapping
            );
            Db::table('sms_channels')->where('id', $channelId)->update([
                'credentials_ciphertext' => $credentials === null
                    ? $channel->credentials_ciphertext : $this->cipher->encrypt($newCredentials),
                'options' => json_encode((object) $newOptions, JSON_THROW_ON_ERROR),
                'version' => Db::raw('version + 1'),
            ]);
        });
    }

    public function updateTemplate(
        int $channelId,
        int $expectedVersion,
        string $templateId,
        string $signName,
        array $mapping,
    ): void {
        Db::transaction(function () use ($channelId, $expectedVersion, $templateId, $signName, $mapping) {
            [$channel, $template] = $this->lockedPair($channelId);
            if ((int) $template->version !== $expectedVersion) {
                throw new RuntimeException('模板版本冲突，请刷新后重试');
            }
            $options = json_decode($channel->options, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($options)) {
                throw new RuntimeException('短信选项格式无效');
            }
            $this->validate(
                $channel->provider,
                $this->cipher->decrypt($channel->credentials_ciphertext),
                $options,
                $templateId,
                $signName,
                $mapping
            );
            Db::table('sms_templates')->where('id', $template->id)->update([
                'template_id' => $templateId, 'sign_name' => $signName,
                'parameter_mapping' => json_encode($mapping, JSON_THROW_ON_ERROR),
                'version' => Db::raw('version + 1'),
            ]);
        });
    }

    private function lockedPair(int $channelId): array
    {
        $channel = Db::table('sms_channels')->where('id', $channelId)->lockForUpdate()->first();
        $template = Db::table('sms_templates')->where('channel_id', $channelId)
            ->where('scene', 'login_code')->lockForUpdate()->first();
        if ($channel === null || $template === null) {
            throw new RuntimeException('渠道或登录模板不存在');
        }
        return [$channel, $template];
    }

    private function validate(
        string $provider,
        array $credentials,
        array $options,
        string $templateId,
        string $signName,
        array $mapping,
    ): void {
        SmsConfigValidator::validate(new SmsConfigSnapshot(
            0,
            $provider,
            'candidate',
            $credentials,
            $options,
            $templateId,
            $signName,
            $mapping,
        ));
    }
}
