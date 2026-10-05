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

use App\Exception\Registration\RegistrationException;
use App\Repository\Registration\DatabaseUserRepository;
use Hyperf\Context\ApplicationContext;
use Hyperf\Database\Connection;
use Hyperf\Database\ConnectionResolverInterface;
use Hyperf\Database\Exception\QueryException;
use Hyperf\Database\Query\Builder;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSource;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * @internal
 * @coversNothing
 */
final class DatabaseUserRepositoryTest extends TestCase
{
    private ContainerInterface $originalContainer;

    protected function setUp(): void
    {
        $this->originalContainer = ApplicationContext::hasContainer()
            ? ApplicationContext::getContainer() : new Container(new DefinitionSource([]));
    }

    protected function tearDown(): void
    {
        ApplicationContext::setContainer($this->originalContainer);
    }

    public function testMysqlEmailConstraintConflictBecomesBusinessException(): void
    {
        $error = new PDOException('duplicate');
        $error->errorInfo = ['23000', 1062, "Duplicate entry for key 'demo_registration_users_email_unique'"];
        $this->installFailingConnection($error);
        $this->expectException(RegistrationException::class);
        $this->expectExceptionCode(40901);
        (new DatabaseUserRepository())->create('one@example.com', 'hash');
    }

    public function testRepeatedInsertsAndQueriesUseIndependentBuilders(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE demo_registration_users (id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, created_at TEXT NOT NULL)');
        $connection = new Connection($pdo);
        $resolver = $this->createMock(ConnectionResolverInterface::class);
        $resolver->method('connection')->willReturn($connection);
        $container = new Container(new DefinitionSource([]));
        $container->set(ConnectionResolverInterface::class, $resolver);
        $container->set(Db::class, new Db($container));
        ApplicationContext::setContainer($container);
        $repository = new DatabaseUserRepository();
        $first = $repository->create('one@example.com', 'first-hash');
        $second = $repository->create('two@example.com', 'second-hash');
        self::assertNotSame($first->id, $second->id);
        self::assertTrue($repository->existsByEmail('one@example.com'));
        self::assertTrue($repository->existsByEmail('two@example.com'));
        self::assertFalse($repository->existsByEmail('missing@example.com'));
        self::assertSame(2, $connection->table('demo_registration_users')->count());
        self::assertSame('first-hash', $connection->table('demo_registration_users')->where('id', $first->id)->value('password_hash'));
    }

    public function testOtherDatabaseErrorsAreNotReportedAsDuplicateEmail(): void
    {
        $error = new PDOException('sensitive database error');
        $error->errorInfo = ['HY000', 2006, 'connection lost'];
        $this->installFailingConnection($error);
        try {
            (new DatabaseUserRepository())->create('one@example.com', 'hash');
            self::fail('Expected database failure');
        } catch (RuntimeException $exception) {
            self::assertNotInstanceOf(RegistrationException::class, $exception);
            self::assertSame('注册数据写入失败', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    private function installFailingConnection(PDOException $error): void
    {
        $query = $this->createMock(Builder::class);
        $query->method('insertGetId')->willThrowException(new QueryException('insert', [], $error));
        $connection = $this->createMock(Connection::class);
        $connection->method('table')->willReturn($query);
        $resolver = $this->createMock(ConnectionResolverInterface::class);
        $resolver->method('connection')->willReturn($connection);
        $container = new Container(new DefinitionSource([]));
        $container->set(ConnectionResolverInterface::class, $resolver);
        $container->set(Db::class, new Db($container));
        ApplicationContext::setContainer($container);
    }
}
