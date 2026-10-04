# 短信依赖注入测试

在仓库根目录的 Linux / WSL 环境执行：

```bash
vendor/bin/phpunit --bootstrap vendor/autoload.php test/Unit/Sms
```

这里覆盖默认 bootstrap，仅加载 Composer 自动加载，避免启动完整应用及连接外部服务。不需要启动 HTTP、Redis 或数据库，不读取本地 `.env`。

| 文件 | 验证内容 |
| --- | --- |
| `LoginSmsServiceTest.php` | 通过接口发送、参数校验、发送异常传播 |
| `LogSmsSenderTest.php` | 手机号脱敏，日志仅记录验证码长度 |
| `AliyunSmsSenderTest.php` | SDK 请求参数、成功和失败响应、缺少配置、SDK 异常 |
| `SmsBindingTest.php` | 实际依赖配置、SDK 工厂绑定、不同环境下容器解析及业务构造 |

环境绑定测试在读取依赖配置前设置 `APP_ENV`，每个用例创建新容器，并在读取后恢复原环境值。因此不受 PHPUnit 默认设置 `APP_ENV=testing` 的影响，也不会复用旧容器的实例。

开发环境使用 `dev`，生产环境使用 `prod`，测试环境使用 `testing`。没有设置时默认使用开发实现，未知环境应抛出异常。

阿里云实现测试使用 SDK Mock；生产环境绑定测试使用假凭据构造真实 SDK，但不调用发送方法。以上测试不发送真实短信，也不验证真实凭据、签名和模板的可用性。

## 新增业务如何测试

新增业务继续依赖 `SmsSenderInterface`，为业务单独编写测试，注入接口的 Mock。已有的发送器测试和环境绑定测试可以复用，不需要每个业务重复测试日志输出和阿里云 SDK。

以下注册业务仅用于演示，尚未创建对应业务类和测试文件。实际添加时，应根据注册规则补充手机号校验、账户状态检查和发送频率限制。

示例业务文件：`app/Service/RegisterService.php`。

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\SmsSenderInterface;

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

use App\Contract\SmsSenderInterface;
use App\Service\RegisterService;
use PHPUnit\Framework\TestCase;

final class RegisterServiceTest extends TestCase
{
    public function testRegistrationSendsCode(): void
    {
        $sender = $this->createMock(SmsSenderInterface::class);
        $sender->expects(self::once())
            ->method('sendCode')
            ->with('13800138000', '123456');

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
| 短信发送失败 | 按业务约定传播异常、返回错误或执行有限次数的重试 |

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

当前接口 `sendCode($mobile, $code)` 表达的是验证码发送，当前阿里云实现使用一组签名和模板配置。如果注册、登录需要不同模板，或者后续需要发送订单通知、物流提醒，应先明确短信场景、模板和参数的接口设计，再补充实现和测试。不要将订单内容直接塞进 `$code` 参数，也不要在业务中通过判断环境来选择发送器。
