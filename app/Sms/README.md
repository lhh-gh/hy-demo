# 短信模块目录

当前模块负责登录验证码发送，生产路由固定使用 login_code 场景。

```text
app/Sms/
├── Contract/                 # 短信模块接口
│   ├── SmsSenderInterface.php
│   ├── SmsGatewayInterface.php
│   └── SmsConfigRepositoryInterface.php
├── Config/                   # 配置读取、校验、加密、写入与路由发布
│   ├── DatabaseSmsConfigRepository.php
│   ├── SmsConfigSnapshot.php
│   ├── SmsConfigValidator.php
│   ├── SmsCredentialCipher.php
│   ├── SmsConfigWriter.php
│   └── SmsRoutePublisher.php
├── Gateway/                  # 厂商选择及协议适配
│   ├── SmsGatewayFactory.php
│   ├── Aliyun/
│   │   ├── AliyunGateway.php
│   │   ├── AliyunClientFactory.php
│   │   └── AliyunSmsClientFactory.php
│   └── Tencent/
│       ├── TencentGateway.php
│       └── TencentClientFactory.php
├── Sender/                   # 发送接口的实现
│   ├── RoutingSmsSender.php
│   ├── LogSmsSender.php
│   └── AliyunSmsSender.php
├── Message/                  # 输入约束、发送回执和状态
│   ├── LoginCodeInput.php
│   ├── SmsSendResult.php
│   └── SmsSendStatus.php
└── Exception/
    └── SmsSendException.php
```

业务入口仍在 [LoginSmsService](../Service/LoginSmsService.php)，业务侧依赖的 [SmsSenderInterface](Contract/SmsSenderInterface.php) 位于 App\Sms\Contract，短信模块接口位于 App\Sms\Contract。

生产链路为 LoginSmsService → RoutingSmsSender → 配置仓库读取快照 → SmsGatewayFactory → 对应厂商 Gateway。Gateway 注入同平台的客户端工厂，根据每次快照创建客户端。开发和测试环境绑定 LogSmsSender。AliyunSmsSender 是保留的静态配置示例，生产环境不绑定它。AliyunSmsClientFactory 为这个静态示例提供基于配置文件的 SDK 客户端；AliyunClientFactory 根据动态配置快照创建客户端。

新增厂商时，在 Gateway 下新增平台目录，将适配器与客户端工厂放在一起，再更新 SmsGatewayFactory、SmsConfigValidator 和适配器测试。新增业务场景需要先扩展发送契约及路由规则，不能只增加厂商适配器。

本次整理修改了原 App\Sms 下类的命名空间；调用方、依赖绑定、测试和自检脚本必须使用对应的新命名空间，不提供旧类名别名。修改后需按部署流程刷新自动加载及框架容器缓存，并重启或重载常驻服务。

离线检查及结果契约见 [短信测试说明](../../test/Unit/Sms/README.md)。
