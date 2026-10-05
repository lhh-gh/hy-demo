# Hyperf 用户注册分层 Demo

本案例对应文章《别再甩锅给 PHP 了，行吗》中的注册场景，提供当前项目可使用的实现。接口为 `POST /demo/registration`，使用独立 MySQL 表 `demo_registration_users`。

这是教学注册流程，包含输入校验、密码哈希、数据库写入、重复邮箱处理和注册事件；没有实现登录、邮箱激活、邮件发送、限流或完整用户管理。不要把注册成功理解为邮箱已经验证。

## 1. 调用链与文件导航

```text
POST /demo/registration
    → Controller：接收请求
    → RegisterUserInput：校验并规范化输入
    → RegisterUserService：编排注册流程
        → UserRepositoryInterface
            → DatabaseUserRepository：独立 Builder + MySQL
        → EventDispatcherInterface
            → UserRegistered → LogUserRegisteredListener
    → RegisteredUser：不包含密码的输出对象
    → HTTP 201 JSON

RegistrationException
    → RegistrationExceptionHandler
    → HTTP 422 / 409 JSON
```

| 层次 | 文件 | 职责 |
| --- | --- | --- |
| Controller | [RegistrationDemoController.php](../../app/Controller/RegistrationDemoController.php) | 接收输入、调用服务、返回 201 |
| 输入 DTO | [RegisterUserInput.php](../../app/Dto/Registration/RegisterUserInput.php) | 类型、邮箱和密码校验；不可变请求数据 |
| 输出 DTO | [RegisteredUser.php](../../app/Dto/Registration/RegisteredUser.php) | 仅返回 ID 和邮箱 |
| Service | [RegisterUserService.php](../../app/Service/Registration/RegisterUserService.php) | 重复检查、哈希、持久化、派发事件 |
| Repository 接口 | [UserRepositoryInterface.php](../../app/Repository/Registration/UserRepositoryInterface.php) | 持久化契约，便于替换和测试 |
| Repository 实现 | [DatabaseUserRepository.php](../../app/Repository/Registration/DatabaseUserRepository.php) | 查询、插入和 MySQL 唯一键冲突转换 |
| 业务异常 | [RegistrationException.php](../../app/Exception/Registration/RegistrationException.php) | 明确区分业务错误码和 HTTP 状态 |
| 异常处理器 | [RegistrationExceptionHandler.php](../../app/Exception/Handler/RegistrationExceptionHandler.php) | 只处理本 Demo 的业务异常 |
| 事件 | [UserRegistered.php](../../app/Event/Registration/UserRegistered.php) | 不可变事件，只携带用户 ID |
| 监听器 | [LogUserRegisteredListener.php](../../app/Listener/Registration/LogUserRegisteredListener.php) | 记录注册成功日志，不发送邮件 |
| 数据表 | [registration_demo.sql](registration_demo.sql) | MySQL 表结构和邮箱唯一约束 |
| 测试 | [测试目录](../../test/Unit/Registration) | DTO、服务、错误响应和持久化行为 |

## 2. 为什么这样分层

Controller 使用 PHP 8 属性注解：类上的 `#[Controller(prefix: '/demo')]` 与方法上的 `#[PostMapping(path: 'registration')]` 组合为 `POST /demo/registration`；服务通过 `#[Inject]` 注入。项目已扫描 `app/`，因此不再在 `config/routes.php` 重复注册该接口。

Controller 不查询数据库，也不做密码哈希。Service 依赖 Repository 接口，通过构造函数显式声明依赖；测试可以传入 Mock，无需用反射修改共享服务或覆盖全局容器里的业务依赖。

DTO 是每次请求创建的普通 PHP readonly 对象，不依赖不存在或未经确认的 Data 属性，也不提供包含明文密码的序列化方法。只存放本次请求的数据，绝不注入为共享单例。

Repository 每次调用都使用 `Db::table()` 创建新 Builder，没有将 Model、Builder、用户或参数放入属性。这里使用框架 Query Builder 完成简单插入，不额外创建 Model；因此也不会引入项目 Model 基类的 Redis 模型缓存依赖。业务复杂到需要模型能力时，可在 Repository 内改为每次创建独立 Model，保持接口不变。

查询 Builder 使用参数绑定，不拼接用户输入。Repository 并非所有简单业务都必须添加；本案例保留该层，是为了展示可替换的持久化边界和隔离测试。

## 3. 输入规则

请求示例：

```json
{
  "email": "demo@example.com",
  "password": "demo-password-123"
}
```

规则：

- email、password 必须是字符串，数组和缺失值返回 422。
- 邮箱去除首尾空白，统一为小写；这是本 Demo 明确采用的业务规则。
- 邮箱最多 254 字节，使用 PHP 邮箱格式校验；本 Demo 面向 ASCII 邮箱。
- 密码不做 trim，首尾空格是密码的一部分。
- 密码为 8～72 字节，拒绝空字节。这里按字节而非字符计数。
- 使用 bcrypt、cost=10；上限用于避免 bcrypt 静默截断超长输入。实际项目应根据负载和安全要求评估算法与成本。
- 多余字段不写入数据库。DTO 密码只传给密码哈希函数，不放入响应、事件或日志。

密码哈希属于 CPU 密集计算，协程不能让它自动变成非阻塞工作。真实注册接口还需要限流与容量评估。

## 4. 并发注册为什么不能只“先查再插”

```text
请求 A：邮箱不存在
请求 B：邮箱不存在
请求 A：插入成功
请求 B：插入违反邮箱唯一约束 → 返回 409
```

`existsByEmail()` 只提供提前提示，真正的一致性保证来自数据库的唯一索引。

Repository 只将 MySQL 错误号 1062 且包含指定邮箱唯一索引名的错误转换为“邮箱已注册”。连接失败、其他约束冲突等错误不能冒充邮箱重复。SQL 中的索引名与代码判断配套，修改时应同步。

本案例只有单条用户 INSERT，依靠 InnoDB 自动提交和唯一索引，不需要为“预检查 + 插入”人为添加事务。将来增加用户角色等多表写入时，应在 Service 编排事务；事件应在提交后处理。

## 5. 事件与失败边界

事件通过 `EventDispatcherInterface::dispatch(new UserRegistered($id))` 派发。监听器使用 `#[Listener]` 注册，不再手动重复注册。

默认派发是同步的，不等于异步队列。本 Demo 的监听器只记录日志：

- 用户写入成功后，注册已经完成。
- 日志监听器失败时，Service 尝试记录告警，仍返回注册成功。
- 告警日志本身失败也不改变已经完成的注册。
- 告警只包含用户 ID 和异常类型，不记录异常参数、邮箱或密码。
- 全局 SQL 监听器 `DbQueryExecutedListener` 只记录带占位符的 SQL 和耗时，不回填或附带绑定参数，避免将邮箱和密码哈希写入查询日志。该策略适用于所有参数化查询；原始 SQL 中直接写入的字面量仍会出现在日志中，业务代码应继续使用参数绑定。
- SQL 日志写入失败由该监听器内部隔离，不影响已完成的数据库写入或后续注册事件；真实数据库错误仍正常上抛。日志故障时不向同一日志设施再次写入，故障期间相应 SQL 诊断日志会缺失。
- Repository 对插入失败给出通用异常，避免默认异常日志打印 SQL 绑定中的密码哈希；这会减少底层诊断信息，生产环境可增加经过脱敏的结构化错误监控。

这种“尽力处理”的事件适用于演示日志，不保证事件可靠交付。若发送激活邮件是必需步骤，应采用事务 Outbox、异步消费者和重试幂等策略；不要简单吞掉邮件失败后宣称“激活邮件已发送”。

## 6. 本地运行

在仓库根目录的 Linux / WSL 环境执行。使用隔离的开发数据库，按已有配置设置数据库连接，不覆盖现有 `.env`。

1. 在开发 MySQL 数据库执行 [registration_demo.sql](registration_demo.sql)。可在已连接到正确数据库的 MySQL 客户端运行：

   ```sql
   SOURCE /home/phpgo/www/dnmp2/www/hy-demo/database/sql/registration_demo.sql;
   ```

   路径以 MySQL 客户端所在环境为准。SQL 不包含 DROP，也不会静默跳过已有表。如果配置了 `DB_PREFIX`，执行前须为表名加相同前缀，保留邮箱唯一索引名。

2. 按项目原有方式启动服务：

   ```bash
   composer start
   ```

   如果服务已运行，重启或按既有机制重载。Demo 数据操作只需要 MySQL，但项目全局启动还可能启动已有 Redis 队列进程，仍应满足项目原有环境要求。

3. 调用接口：

   ```bash
   curl -i -X POST http://127.0.0.1:9501/demo/registration \
     -H 'Content-Type: application/json' \
     --data '{"email":"demo@example.com","password":"demo-password-123"}'
   ```

预期首次注册返回 HTTP 201：

```json
{"ok":true,"data":{"id":1,"email":"demo@example.com"}}
```

ID 是示意值，以数据库实际自增值为准。再次使用同一邮箱，预期 HTTP 409：

```json
{"ok":false,"code":40901,"message":"该邮箱已注册"}
```

密码少于 8 字节或邮箱非法，预期 HTTP 422。业务错误由专用异常处理器转换；其他异常交给项目现有异常处理器，不承诺所有错误都是相同 JSON 格式。

以上 HTTP 输出是预期示例，并非本次已执行的接口实测记录。

## 7. 测试与验证范围

执行本 Demo 的框架引导测试：

```bash
composer test -- test/Unit/Registration
```

也可以只加载 Composer 自动加载器，隔离运行本 Demo：

```bash
vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php test/Unit/Registration
```

测试覆盖输入边界、邮箱规范化、密码哈希、同一服务连续注册的数据隔离、预检查后发生唯一冲突时不派发事件、监听器及日志失败、业务错误 HTTP 映射、MySQL 异常分类，以及 SQLite 内存数据库中的独立插入和查询。

[RegistrationSqlLoggingTest.php](../../test/Unit/Registration/RegistrationSqlLoggingTest.php) 还将真实 `DbQueryExecutedListener` 挂载到 SQLite 的 `QueryExecuted` 事件链，验证日志不包含邮箱、明文密码和完整密码哈希，INSERT 日志失败后仍返回注册结果并派发一次事件，以及日志故障不会掩盖真实数据库写入失败。

本轮 SQL 日志修复使用 Composer 自动加载器隔离运行测试（附加 `--do-not-cache-result`），实际通过：18 个测试、73 个断言；SQL 监听器、注册 Service、Repository 和新增测试的 PHPStan 检查，以及修改的 PHP 文件格式检查均通过。MySQL 错误映射使用模拟异常，SQLite 验证持久化及查询日志事件链，不替代 MySQL 唯一索引并发测试。

本轮未重新运行框架引导测试，未对现有数据库执行 SQL，未启动 HTTP 服务进行接口联调，也未运行真实 MySQL 并发测试。后续应在隔离 MySQL 环境用相同邮箱并发注册，验证只新增一条记录，其余返回 409。

此前验证记录：项目默认的全目录测试发现过程会加载已有 Pest 示例；仅用 `--filter` 时曾遇到 `Pest/Support/Backtrace.php: Undefined array key "file"`，未执行到筛选用例。该轮指定本 Demo 测试目录的框架引导测试通过（15 个测试、52 个断言）；本轮未重复验证该入口，也未为此修改已有 Pest 测试。

## 8. 与原文章的主要差异

| 原示例问题 | 本案例处理 |
| --- | --- |
| DTO 属性和旧命名空间兼容性不明 | 使用原生 readonly DTO 和项目现有组件 |
| Mock 返回 stdClass，但接口要求 User | 使用明确的 RegisteredUser 返回类型 |
| 密码错误先于预期的重复邮箱异常 | 测试使用合法密码，独立测试各错误分支 |
| 容器取事件对象再调用 handle | 使用标准事件派发接口，每次创建新事件 |
| 把异常码当 HTTP 状态 | 在专用异常处理器显式映射 |
| 只预检查邮箱是否存在 | MySQL 唯一约束兜底 |
| 邮件失败可能把已注册用户报告为失败 | 明确持久化与演示日志事件的失败边界 |
| 请求状态可能进入共享对象 | 服务只保存依赖，请求数据全部局部持有 |

阅读代码建议从 Controller 开始，依次跟进 DTO、Service、Repository 和事件，再看异常处理器与测试。
