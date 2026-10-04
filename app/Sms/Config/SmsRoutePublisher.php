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

use Hyperf\DbConnection\Db;
use InvalidArgumentException;
use RuntimeException;

class SmsRoutePublisher
{
    public function __construct(private SmsCredentialCipher $cipher)
    {
    }

    public function switchChannel(string $scene, int $channelId, int $expectedVersion): void
    {
        if ($scene !== 'login_code' || $channelId < 1 || $expectedVersion < 0) {
            throw new InvalidArgumentException('路由发布参数无效');
        }
        Db::transaction(function () use ($scene, $channelId, $expectedVersion) {
            // 所有后台写入路径约定相同加锁顺序：渠道 → 模板 → 路由。
            $channel = Db::table('sms_channels')->where('id', $channelId)
                ->lockForUpdate()->first();
            $template = Db::table('sms_templates')->where('channel_id', $channelId)
                ->where('scene', $scene)->lockForUpdate()->first();
            if ($channel === null || $template === null
                || (int) $channel->enabled !== 1 || (int) $template->enabled !== 1) {
                throw new RuntimeException('目标渠道或模板不可用');
            }
            $options = json_decode($channel->options, true, 512, JSON_THROW_ON_ERROR);
            $mapping = json_decode($template->parameter_mapping, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($options) || ! is_array($mapping)) {
                throw new RuntimeException('短信配置格式无效');
            }
            SmsConfigValidator::validate(new SmsConfigSnapshot(
                $channelId,
                $channel->provider,
                'candidate',
                $this->cipher->decrypt($channel->credentials_ciphertext),
                $options,
                $template->template_id,
                $template->sign_name,
                $mapping,
            ));
            if ($expectedVersion === 0) {
                // 唯一键兜底并发首次创建；失败时整个事务回滚。
                Db::table('sms_routes')->insert([
                    'scene' => $scene, 'channel_id' => $channelId,
                    'enabled' => 1, 'version' => 1,
                ]);
                return;
            }
            $affected = Db::table('sms_routes')->where('scene', $scene)
                ->where('version', $expectedVersion)->update([
                    'channel_id' => $channelId,
                    'enabled' => 1,
                    'version' => Db::raw('version + 1'),
                ]);
            if ($affected !== 1) {
                throw new RuntimeException('路由不存在或版本冲突，请刷新后重试');
            }
            // 正式实现：在此事务内插入不含敏感数据的审计记录。
        });
    }
}
