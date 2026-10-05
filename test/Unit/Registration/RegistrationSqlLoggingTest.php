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

namespace HyperfTest\Unit\Registration;

use App\Dto\Registration\RegisterUserInput;
use App\Event\Registration\UserRegistered;
use App\Exception\Registration\RegistrationException;
use App\Listener\DbQueryExecutedListener;
use App\Repository\Registration\DatabaseUserRepository;
use App\Service\Registration\RegisterUserService;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Database\Connection;
use Hyperf\Database\ConnectionResolver;
use Hyperf\Database\ConnectionResolverInterface;
use Hyperf\Database\Events\QueryExecuted;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSource;
use Hyperf\Event\EventDispatcher;
use Hyperf\Event\ListenerProvider;
use Hyperf\Logger\LoggerFactory;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @internal
 * @coversNothing
 */
final class RegistrationSqlLoggingTest extends TestCase
{
    private ContainerInterface $originalContainer;

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->originalContainer = ApplicationContext::hasContainer()
            ? ApplicationContext::getContainer() : new Container(new DefinitionSource([]));
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE demo_registration_users (id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, created_at TEXT NOT NULL)');
    }

    protected function tearDown(): void
    {
        ApplicationContext::setContainer($this->originalContainer);
    }

    public function testRegistrationSqlLogsDoNotContainCredentialsOrEmail(): void
    {
        $records = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info')
            ->willReturnCallback(static function (string $message, array $context = []) use (&$records): void {
                $records[] = ['message' => $message, 'context' => $context];
            });
        $service = $this->createService($logger, new ListenerProvider());
        $input = RegisterUserInput::fromArray(['email' => 'one@example.com', 'password' => 'synthetic-password-123']);
        $user = $service->register($input);
        $hash = $this->pdo->query('SELECT password_hash FROM demo_registration_users')->fetchColumn();

        self::assertSame($input->email, $user->email);
        self::assertTrue(password_verify($input->password, $hash));
        self::assertSame([[], []], array_column($records, 'context'));
        $log = implode("\n", array_column($records, 'message'));
        self::assertStringContainsString('insert into', $log);
        self::assertStringContainsString('?', $log);
        self::assertStringNotContainsString($input->email, $log);
        self::assertStringNotContainsString($input->password, $log);
        self::assertStringNotContainsString($hash, $log);
    }

    public function testInsertLogFailureDoesNotChangeRegistrationOrDispatchResult(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(3))->method('info')
            ->willReturnCallback(static function (string $message): void {
                if (str_contains($message, 'insert into')) {
                    throw new RuntimeException('SQL log sink unavailable');
                }
            });
        $events = [];
        $listeners = new ListenerProvider();
        $listeners->on(UserRegistered::class, static function (UserRegistered $event) use (&$events): void {
            $events[] = $event->userId;
        });
        $service = $this->createService($logger, $listeners);
        $input = RegisterUserInput::fromArray(['email' => 'one@example.com', 'password' => 'synthetic-password-123']);
        $user = $service->register($input);

        self::assertGreaterThan(0, $user->id);
        self::assertSame($input->email, $user->email);
        self::assertSame($user->id, (int) $this->pdo->query('SELECT id FROM demo_registration_users')->fetchColumn());
        try {
            $service->register($input);
            self::fail('Expected duplicate email');
        } catch (RegistrationException $exception) {
            self::assertSame(409, $exception->httpStatus);
        }
        self::assertSame([$user->id], $events);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM demo_registration_users')->fetchColumn());
    }

    public function testDatabaseFailureStillPropagatesWhenSqlLoggingFails(): void
    {
        $this->pdo->exec("CREATE TRIGGER reject_registration BEFORE INSERT ON demo_registration_users
            BEGIN SELECT RAISE(ABORT, 'simulated write failure'); END");
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->willThrowException(new RuntimeException('SQL log sink unavailable'));
        $events = [];
        $listeners = new ListenerProvider();
        $listeners->on(UserRegistered::class, static function (UserRegistered $event) use (&$events): void {
            $events[] = $event->userId;
        });
        $service = $this->createService($logger, $listeners);

        try {
            $service->register(RegisterUserInput::fromArray(['email' => 'one@example.com', 'password' => 'synthetic-password-123']));
            self::fail('Expected database failure');
        } catch (RuntimeException $exception) {
            self::assertNotInstanceOf(RegistrationException::class, $exception);
            self::assertSame('注册数据写入失败', $exception->getMessage());
        }
        self::assertSame([], $events);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM demo_registration_users')->fetchColumn());
    }

    private function createService(LoggerInterface $sqlLogger, ListenerProvider $listeners): RegisterUserService
    {
        $container = new Container(new DefinitionSource([]));
        $factory = $this->createMock(LoggerFactory::class);
        $factory->method('get')->willReturn($sqlLogger);
        $container->set(LoggerFactory::class, $factory);
        $listeners->on(QueryExecuted::class, [new DbQueryExecutedListener($container), 'process']);
        $dispatcher = new EventDispatcher($listeners);
        $connection = new Connection($this->pdo);
        $connection->setEventDispatcher($dispatcher);
        $container->set(ConnectionResolverInterface::class, new ConnectionResolver(['default' => $connection]));
        $container->set(Db::class, new Db($container));
        ApplicationContext::setContainer($container);

        return new RegisterUserService(new DatabaseUserRepository(), $dispatcher, $this->createMock(StdoutLoggerInterface::class));
    }
}
