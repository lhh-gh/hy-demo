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

use App\Sms\Contract\SmsConfigRepositoryInterface;
use Hyperf\DbConnection\Db;
use RuntimeException;

class DatabaseSmsConfigRepository implements SmsConfigRepositoryInterface
{
    public function __construct(private SmsCredentialCipher $cipher)
    {
    }

    public function forScene(string $scene): SmsConfigSnapshot
    {
        $row = Db::table('sms_routes as r')
            ->useWritePdo()
            ->join('sms_channels as c', 'c.id', '=', 'r.channel_id')
            ->join('sms_templates as t', function ($join) {
                $join->on('t.channel_id', '=', 'c.id')->on('t.scene', '=', 'r.scene');
            })
            ->where('r.scene', $scene)
            ->where('r.enabled', 1)->where('c.enabled', 1)->where('t.enabled', 1)
            ->first([
                'r.version as route_version', 'c.id as channel_id', 'c.provider',
                'c.credentials_ciphertext', 'c.options', 'c.version as channel_version',
                't.template_id', 't.sign_name', 't.parameter_mapping',
                't.version as template_version',
            ]);
        if ($row === null) {
            throw new RuntimeException('该场景没有有效短信配置');
        }
        $options = json_decode($row->options, true, 512, JSON_THROW_ON_ERROR);
        $mapping = json_decode($row->parameter_mapping, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($options) || ! is_array($mapping)) {
            throw new RuntimeException('短信选项和参数映射格式无效');
        }
        $snapshot = new SmsConfigSnapshot(
            channelId: (int) $row->channel_id,
            provider: $row->provider,
            revision: sprintf(
                'route:%s/channel:%s/template:%s',
                $row->route_version,
                $row->channel_version,
                $row->template_version
            ),
            credentials: $this->cipher->decrypt($row->credentials_ciphertext),
            options: $options,
            templateId: $row->template_id,
            signName: $row->sign_name,
            parameterMapping: $mapping,
        );
        SmsConfigValidator::validate($snapshot);
        return $snapshot;
    }
}
