# 短信依赖注入测试

在仓库根目录的 Linux / WSL 环境执行：

```bash
vendor/bin/phpunit --bootstrap vendor/autoload.php test/Unit/Sms
```

这里覆盖默认 bootstrap，仅加载 Composer 自动加载，避免启动完整应用及连接外部服务。不需要启动 HTTP、Redis 或数据库服务，不读取本地 `.env`。持久化测试需要 `pdo_sqlite`，仅创建隔离的内存数据库。

| 文件 | 验证内容 |
| --- | --- |
| `LoginSmsServiceTest.php` | 通过接口发送、参数校验、发送异常传播 |
| `LogSmsSenderTest.php` | 手机号脱敏，日志仅记录验证码长度 |
| `AliyunSmsSenderTest.php` | SDK 请求参数、成功和失败响应、缺少配置、SDK 异常脱敏且不保留原始异常链 |
| `SmsBindingTest.php` | 实际依赖配置、SDK 工厂绑定、配置仓库解析、不同环境下容器解析及业务构造 |
| `SmsConfigPersistenceTest.php` | 渠道和模板写入、发布切换、配置即时读取、版本冲突、禁用配置及事务回滚 |
| `SmsDemoCommandTest.php` | dev/testing 模拟调用，prod/未知环境在发送前拒绝 |

也可执行 `php bin/sms-self-check.php` 验证路由快照、加密与校验，执行 `php bin/sms-binding-check.php` 检查当前动态绑定及管理服务装配。数据库初始化与服务调用说明见 [短信配置表与服务使用](../../../database/sql/README.md)。SQLite 用例不能替代 MySQL 行锁、并发首次发布或多 Worker 联调。

环境绑定测试在读取依赖配置前设置 `APP_ENV`，每个用例创建新容器，并在读取后恢复原环境值。因此不受 PHPUnit 默认设置 `APP_ENV=testing` 的影响，也不会复用旧容器的实例。

开发环境使用 `dev`，生产环境使用 `prod`，测试环境使用 `testing`。开发和测试绑定 `LogSmsSender`，生产绑定 `RoutingSmsSender`；配置仓库接口独立绑定到 `DatabaseSmsConfigRepository`。没有设置时默认使用开发实现，未知环境应抛出异常。

阿里云实现测试使用 SDK Mock；环境绑定测试解析真实的路由服务及配置仓库，并使用含 endpoint 的假配置构造静态阿里云 SDK，但不调用发送或数据库查询方法。以上测试不发送真实短信，也不验证真实凭据、签名、模板及数据库配置的可用性。

生产动态发送前，需准备 `sms_routes`、`sms_channels`、`sms_templates` 表和有效配置，并设置 `.env.example` 中说明的 `SMS_CREDENTIALS_KEY`。用 `openssl rand -base64 32` 生成密钥，安全保存并让所有 Worker/实例使用同一值；已有密文不能直接更换密钥。环境和依赖绑定修改后需重启或重载常驻服务。数据库配置读取和两家平台的真实发送仍需单独联调。

## 发送契约与本轮架构调整

sendCode()、sendLoginCode() 和 SmsNoticeService::notify() 现在返回只读 SmsSendResult。现有忽略返回值的调用方式仍可使用；自定义发送器、Gateway 实现及测试替身必须同步修改返回类型，并显式提供回执。SmsNoticeService 改为构造注入 LoginSmsService，手动实例化时需传入该服务。

- Accepted：厂商确认受理，不代表最终送达。
- Simulated：开发或测试环境只记录脱敏日志，没有发送。
- Rejected：明确拒绝，通过 SmsSendException::$result 读取状态、平台、请求 ID 和错误码。
- Unknown：超时、SDK 异常或缺少有效状态，仍抛出 SmsSendException；不能据此自动重发或切换平台。

失败异常仍继承 RuntimeException，不携带 SDK 原始异常链、原始响应或厂商错误消息。配置与参数错误在发送前拒绝，不作为厂商受理结果。请求 ID 可能缺失，调用方应接受 null。

业务入口统一复用 LoginCodeInput 的大陆手机号与六位验证码约束；原通知入口经过登录服务，各发送实现也会校验，防止直接调用绕过。当前路由仍固定为 login_code。

动态 Gateway 通过 AliyunClientFactory / TencentClientFactory 根据每次快照创建 SDK 客户端，Hyperf 自动构造注入；测试可替换工厂。静态示例 AliyunSmsSender 同步遵循回执及异常契约，生产绑定仍为动态路由。

新增 DynamicGatewayTest 覆盖两家动态适配器的参数映射、受理回执、拒绝错误码、空响应、SDK 异常脱敏，以及发送前校验；SmsNoticeServiceTest 验证通知入口无法绕过校验。绑定测试覆盖新工厂依赖的自动装配。所有 SDK 调用均模拟，不发送真实短信。

## 新增业务如何测试

新增业务继续依赖 `SmsSenderInterface`，为业务单独编写测试，注入接口的 Mock。已有的发送器测试和环境绑定测试可以复用，不需要每个业务重复测试日志输出和阿里云 SDK。

以下注册业务仅用于演示，尚未创建对应业务类和测试文件。实际添加时，应根据注册规则补充手机号校验、账户状态检查和发送频率限制。

示例业务文件：`app/Service/RegisterService.php`。

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Sms\Contract\SmsSenderInterface;

class RegisterService
{
    public function __construct(private SmsSenderInterface $sender)
    {
    }

    public function sendRegisterCode(string $mobile, string $code): void
    {
        // 在这里执行注册业务校验，校验通过后发送。
        $this->sender->sendCode($mobile, $code);
    }
}
```

示例测试文件：`test/Unit/Sms/RegisterServiceTest.php`。

```php
<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Sms;

use App\Sms\Contract\SmsSenderInterface;
use App\Service\RegisterService;
use PHPUnit\Framework\TestCase;

final class RegisterServiceTest extends TestCase
{
    public function testRegistrationSendsCode(): void
    {
        $sender = $this->createMock(SmsSenderInterface::class);
        $sender->expects(self::once())
            ->method('sendCode')
            ->with('13800138000', '123456')
            ->willReturn(new \App\Sms\Message\SmsSendResult(\App\Sms\Message\SmsSendStatus::Accepted, 'aliyun'));

        $service = new RegisterService($sender);
        $service->sendRegisterCode('13800138000', '123456');
    }
}
```

Mock 会检查调用次数和参数，不会执行任何真实发送器。完成上述文件后，可以单独运行：

```bash
vendor/bin/phpunit --bootstrap vendor/autoload.php test/Unit/Sms/RegisterServiceTest.php
```

每个新业务应按实际实现补充自己的规则测试：

| 业务场景 | 应验证的行为 |
| --- | --- |
| 正常注册 | 调用短信接口一次，手机号和验证码正确 |
| 手机号已注册 | 拒绝操作，不调用短信接口 |
| 发送过于频繁 | 拒绝操作，不调用短信接口 |
| 短信发送失败 | 按业务约定传播类型化异常；结果未知时不得自动重发 |

对于拒绝发送的场景，在调用业务方法前设置以下断言，并断言业务约定的异常或返回值：

```php
$sender->expects(self::never())->method('sendCode');
```

如果新业务还依赖用户查询、频率限制等服务，单元测试也应替换这些依赖，控制“已注册”“超过频率”等条件。涉及真实数据库或 Redis 的集成测试需要另行配置隔离的测试环境，不能假设上述自动加载命令会自动隔离外部服务。

可参考现有的 [LoginSmsServiceTest.php](LoginSmsServiceTest.php)，其中包含输入校验和发送异常传播测试。

## 什么时候需要补充发送器测试

| 变更范围 | 需要补充的测试 |
| --- | --- |
| 新增使用相同验证码接口的业务 | 新业务的调用条件、参数和失败处理 |
| 修改日志内容或阿里云请求、响应处理 | 对应发送器的测试 |
| 调整环境选择或依赖注册 | 环境绑定测试 |
| 新增订单通知、物流提醒等短信类型 | 新接口行为、模板参数映射及使用它的新业务测试 |

当前接口 `sendCode($mobile, $code)` 表达的是验证码发送，生产路由固定读取 `login_code` 场景的渠道、签名和模板，静态阿里云发送器仍使用配置文件中的一组签名和模板。如果注册、登录需要不同模板，或者后续需要发送订单通知、物流提醒，应先明确短信场景、模板和参数的接口设计，再补充实现和测试。不要将订单内容直接塞进 `$code` 参数，也不要在业务中通过判断环境来选择发送器。
