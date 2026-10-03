# 仓库协作指南

## 适用范围与沟通

- 本文件适用于整个仓库；子目录存在 `AGENTS.md` 时，同时遵循其适用范围内的约定。
- 默认使用简体中文沟通和编写说明，代码标识符、命令及框架术语保持原样。
- 开始修改前查看 `git status --short`，保留已有的未提交修改和未跟踪文件；只修改当前任务相关内容。
- 交付时说明修改内容、验证结果及未验证的原因，不把未执行的命令描述为验证通过。

## Git 协作规范

### 分支与变更范围

- 开始工作时确认当前分支、工作区和暂存区：`git branch --show-current`、`git status --short`、`git diff`、`git diff --cached`。
- 需要创建分支时，默认使用 `codex/<简短英文描述>`，例如 `codex/order-validation`；用户指定分支名或要求继续现有分支时遵循其要求。
- 根据任务确定基准分支，不假设远端默认分支一定是 `main` 或 `master`。切换分支、同步远端或合并前先确认未提交修改不会被覆盖。
- 一次变更围绕一个明确目的；避免混入无关重构、全仓格式化、权限或换行符变更。不要为局部任务修改全局 Git 配置。
- 使用 `git add -- <具体路径>` 精确暂存；同一文件含无关修改时按块暂存，保留其他人的改动及原有暂存内容，不默认执行 `git add .` 或 `git add -A`。
- 不提交 `.env`、密钥、令牌、日志、缓存、`vendor/`、`runtime/` 等本地或生成内容。依赖变更应配套提交相关 `composer.json` 和 `composer.lock` 修改。

### 提交约定

- 新提交采用 Conventional Commits：`<type>(<scope>): <简短说明>`；`scope` 可省略，说明默认使用中文，准确描述本次变更。
- 常用类型：`feat`（功能）、`fix`（修复）、`docs`（文档）、`refactor`（重构）、`test`（测试）、`style`（纯格式）、`perf`（性能）、`build`（构建与依赖）、`ci`（持续集成）、`chore`（其他维护）。
- 示例：`docs(agents): 补充 Git 协作规范`、`fix(order): 修复重复提交导致的重复下单`。避免使用 `update`、`修改代码` 等无法说明目的的提交消息。
- 每个提交保持逻辑完整，并包含必要的实现、测试和说明；独立目的拆分提交。存在不兼容变更时使用 `!` 或 `BREAKING CHANGE:`，说明影响和迁移方式。
- 提交前检查 `git diff --cached` 和 `git diff --cached --check`，确认暂存内容属于本次提交，并按改动范围完成必要验证；提交后用 `git status --short` 核对剩余修改。

### 同步、合并与历史保护

- 同步时先获取远端状态并检查分叉情况，再选择快进、合并或变基；不盲目执行 `pull`，不对他人共享的历史自行变基。
- 解决冲突时理解双方修改的用途，保留应有行为；不要直接用整文件的 `ours` / `theirs` 覆盖冲突。解决后检查残留冲突标记并验证受影响功能。
- 不擅自执行 `reset --hard`、`clean -fd`、丢弃工作区修改、覆盖他人提交的 `commit --amend` 或强制推送。确需改写历史时，先确认任务授权和影响范围；使用 `--force-with-lease` 也不能替代这一判断。
- 创建 PR 时说明问题、变更后的行为、验证结果，以及必要的配置或迁移步骤；关联已有任务编号，不编造链接或测试结果。合并前确认目标分支、差异范围和检查状态。
- 提交、推送、创建或合并 PR 按当前任务授权范围执行；交付时明确哪些操作已完成，不把本地文件修改描述为已提交或已发布。

## 项目现状

- 项目基于 Hyperf 3.2，使用 Composer 管理依赖，要求 PHP >= 8.2。
- 当前服务配置和 Docker 镜像使用 Swoole；运行环境应为 Linux、WSL 或 Linux 容器。README 提及 Swow，但切换前需要核对服务配置与扩展兼容性。
- 项目仍以框架骨架和示例为主，已包含数据库、Redis、缓存、异步队列、AMQP 和 Elasticsearch 等组件依赖。安装依赖不代表相关外部服务已启动。
- `docs/` 是本地中文学习计划、实战教程和排障手册，通过根目录 `.gitignore` 的 `/docs/` 规则忽略，不纳入提交；其他检出环境可能没有该目录。教程中的电商、IM、微服务等设计不等于当前代码已实现；以 `app/`、`config/` 和 `composer.json` 为事实依据。

## 目录与入口

| 路径 | 用途 |
| --- | --- |
| `app/Controller/` | HTTP 控制器；公共依赖定义在 `AbstractController` |
| `app/Model/` | 数据模型；公共 `Model` 已集成模型缓存 |
| `app/Constants/`、`app/Exception/` | 错误码、业务异常和异常处理器 |
| `app/Listener/`、`app/Process/` | 事件监听器和自定义进程 |
| `config/routes.php` | 当前显式 HTTP 路由 |
| `config/autoload/` | 服务、数据库、Redis、队列等组件配置 |
| `config/container.php`、`config/config.php` | 容器及基础配置入口 |
| `bin/hyperf.php` | 命令行和服务启动入口 |
| `test/` | PHPUnit、Hyperf Testing 与 Pest 测试；引导文件为 `test/bootstrap.php` |
| `docs/` | 中文学习与实战文档 |
| `runtime/`、`vendor/` | 运行时生成文件和第三方依赖，不直接编辑或提交 |

## 环境与常用命令

以下命令在仓库根目录的 Linux / WSL / 容器环境中执行。Windows PowerShell 可通过 `wsl -d Ubuntu-22.04 --cd /home/phpgo/www/dnmp2/www/hy-demo` 进入当前项目环境；其他机器按实际路径调整。

| 目的 | 命令 |
| --- | --- |
| 安装锁定版本的依赖 | `composer install` |
| 查看 PHP 与 Swoole 环境 | `php -v`、`php --ri swoole` |
| 启动 HTTP 服务 | `composer start` 或 `php bin/hyperf.php start` |
| 运行 Composer 配置的测试 | `composer test` |
| 筛选测试 | `composer test -- --filter ExampleTest` |
| 运行 Pest 测试 | `vendor/bin/pest --bootstrap test/bootstrap.php` |
| 静态分析 | `composer analyse` |
| 检查 PHP 格式 | `vendor/bin/php-cs-fixer fix --dry-run --diff` |
| 修复 PHP 格式 | `composer cs-fix` |
| 构建并启动开发容器 | `docker compose up --build` |

- 仅在 `.env` 不存在时从 `.env.example` 创建；按本地环境配置外部服务，不覆盖已有配置，不在输出或提交中泄露凭据。
- HTTP 默认监听 `0.0.0.0:9501`。当前 Compose 服务名为 `hyperf-skeleton`，容器内工作目录为 `/opt/www`；Compose 未提供 MySQL、Redis 或 RabbitMQ 服务。
- 当前异步队列使用 Redis，`AsyncQueueConsumer` 通过 `#[Process]` 注册。启动或验证队列功能前确认 Redis 可用。
- `composer install` 会触发清理 `runtime/container` 的脚本；不要在不明确影响的情况下对正在使用的服务执行依赖变更。
- `composer cs-fix` 默认扫描整个仓库。局部改动优先给 PHP CS Fixer 传入具体文件路径，避免引入无关格式变化。
- 依赖版本以 `composer.lock` 为准；仅在任务需要时调整依赖并同步锁文件，不为解决环境缺失而跳过平台要求。

## PHP 与 Hyperf 开发约定

- 遵循 `.php-cs-fixer.php`，保留 `declare(strict_types=1);`、现有文件头、四空格缩进和短数组语法；新增类型声明需与父类及框架签名兼容。
- 遵循 Composer PSR-4：业务代码使用 `App\` 对应 `app/`，测试自动加载使用 `HyperfTest\` 对应 `test/`。不要默认 `Tests\` 已配置自动加载。
- 优先沿用现有依赖注入、控制器、异常和模型基类。复杂业务逻辑按需要拆分为服务，不为简单功能引入额外架构。
- 使用 Hyperf 3 的命名空间函数，例如 `use function Hyperf\Support\env;`，不要假设 Laravel 风格的全局辅助函数可用。
- Hyperf 是常驻内存的协程服务。不要在单例属性、静态变量或全局变量中保存请求级可变状态；使用局部变量或框架上下文隔离请求数据。
- 数据库、Redis、HTTP 等 I/O 优先使用现有 Hyperf 组件和连接池。涉及事务、并发更新和消息重试时，明确一致性与幂等行为。
- 新增路由、监听器或进程时，核对现有配置与属性扫描方式，避免重复注册。修改常驻服务代码后需要重启或按已有开发机制重载才能验证。
- 配置项集中到 `config/`，环境差异通过环境变量表达；新增变量时同步 `.env.example` 和必要说明。

## 验证与文档维护

- 根据改动选择最小但有效的检查：PHP 语法、相关测试、静态分析或接口验证；纯文档修改检查内容、路径、命令和 Markdown，无需启动完整服务。
- PHPUnit 配置为 `phpunit.xml.dist`，测试环境设置为 `APP_ENV=testing`；这不自动保证数据库和 Redis 已隔离，集成测试前需核对实际连接配置。
- PHPStan 配置为 `phpstan.neon.dist`，当前级别为 0，扫描 `app/` 与 `config/`；不要通过放宽规则掩盖新问题。
- `test/Cases/` 包含类式 HTTP 测试，`test/Feature/` 和 `test/Unit/` 包含 Pest 示例。按测试类型选择运行器，确认目标用例确实执行。
- 修改接口行为时同步相关断言。示例测试可能与本地正在修改的接口不同，遇到失败先确认基线和实际行为，不为通过测试删除有意义的断言。
- 缺少 PHP 扩展、依赖或外部服务导致检查无法执行时，说明具体阻碍和后续验证命令。
- 修改 `docs/` 时保持中文叙述、相对链接和代码块可复制；区分教程示例、计划功能与当前实现，避免将预期输出写成实际验证结果。
