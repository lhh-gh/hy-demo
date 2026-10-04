# 短信配置表与服务使用

`sms.sql` 对应本地《Hyperf依赖注入与多平台短信动态配置实战》第 15.1 节的 MySQL 8 三表设计，为第 17 节写入和发布服务提供存储。文件已提供，尚未对任何数据库执行。

在隔离的空测试数据库中由部署人员导入 SQL；它不是自动迁移，不包含覆盖已有表、删除数据或真实凭据的语句。已有同名表时先核对结构，不要直接重复执行。配置 `DB_PREFIX` 非空时，需要同步调整建表脚本中的表名和外键引用。

## 准备与调用

1. 设置 `.env.example` 所说明的 `SMS_CREDENTIALS_KEY`，所有 Worker/实例保持一致。用 `openssl rand -base64 32` 生成并安全保存，已有密文不能直接更换密钥。
2. 在已引导 Hyperf 的内部命令或已鉴权服务中，注入 `App\Sms\SmsConfigWriter` 和 `App\Sms\SmsRoutePublisher`。当前没有配置管理 HTTP 接口或管理页面。
3. 调用 `createChannel($name, $provider, $credentials, $options, $templateId, $signName, $mapping)`，返回渠道 ID，并原子创建该渠道的 `login_code` 模板；渠道和模板版本均为 1。支持 `aliyun` 和 `tencent`，七牛云尚未实现。
4. 调用 `switchChannel('login_code', $channelId, 0)` 首次发布，路由版本为 1。切换已有路由时传该路由的实际版本；重复创建和过期版本均应拒绝。
5. 调用 `updateChannel($channelId, $channelVersion, $credentials, $options)` 更新渠道。凭据或 options 传 `null` 保留原值；凭据数组是完整替换，不是字段合并，空表单应先转换为 `null`。
6. 调用 `updateTemplate($channelId, $templateVersion, $templateId, $signName, $mapping)` 更新模板。这里传渠道 ID，但版本取对应登录模板的版本，不是渠道或路由版本。

阿里云凭据字段为 `access_key_id/access_key_secret`，options 可为空，映射如 `['code' => 'code']`；腾讯云为 `secret_id/secret_key`，options 需要 `sdk_app_id/region`，映射如 `['code']`。真实值应来自受控输入，不写进仓库或日志。

编辑当前生效渠道或模板会影响下一次主库读取；本实现没有草稿隔离。需要同时修改凭据和模板时，在同一外层 `Db::transaction()` 中依次调用 `updateChannel()`、`updateTemplate()`，任一步失败则整体回滚。所有写入采用渠道→模板→路由顺序；修改代码或绑定后重启/重载服务，单纯修改数据库配置无需重启。

管理接口接入时需补齐身份认证、场景权限、脱敏读取和审计。仅将版本冲突及首次发布的数据库重复键冲突转换为 409，不要把连接失败等所有数据库异常都归为版本冲突；参数错误映射为 422。这里提供内部服务，不直接暴露数据库异常给客户端。

## 验证

在仓库根目录执行：

```bash
php bin/sms-self-check.php
php bin/sms-binding-check.php
vendor/bin/phpunit --bootstrap vendor/autoload.php test/Unit/Sms
```

前两个脚本不启动应用、不访问数据库或云平台。测试套件的持久化用例使用独立 SQLite 内存数据库（需要 `pdo_sqlite`），验证顺序写入、版本冲突、回滚和动态读取；不读取 `.env`、不连接实际 MySQL，不证明 MySQL 行锁或多 Worker 并发行为。

`APP_ENV=testing php bin/hyperf.php sms:demo` 可验证完整应用命令发现与日志绑定。它会引导应用，应先检查本地服务依赖；命令仅允许 dev/testing。MySQL 建表、并发锁、跨 Worker 配置生效和真实供应商发送仍需按文档第 19.3 节另行联调。
