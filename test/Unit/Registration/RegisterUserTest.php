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

use App\Dto\Registration\RegisteredUser;
use App\Dto\Registration\RegisterUserInput;
use App\Event\Registration\UserRegistered;
use App\Exception\Handler\RegistrationExceptionHandler;
use App\Exception\Registration\RegistrationException;
use App\Repository\Registration\UserRepositoryInterface;
use App\Service\Registration\RegisterUserService;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\HttpMessage\Server\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

/**
 * @internal
 * @coversNothing
 */
final class RegisterUserTest extends TestCase
{
    #[DataProvider('invalidInputs')]
    public function testInvalidInputIsRejected(array $data): void
    {
        $this->expectException(RegistrationException::class);
        $this->expectExceptionCode(42201);
        RegisterUserInput::fromArray($data);
    }

    public static function invalidInputs(): array
    {
        return [
            'missing' => [[]],
            'array email' => [['email' => [], 'password' => 'password123']],
            'array password' => [['email' => 'one@example.com', 'password' => []]],
            'bad email' => [['email' => 'wrong', 'password' => 'password123']],
            'short password' => [['email' => 'one@example.com', 'password' => 'pass123']],
            'long password' => [['email' => 'one@example.com', 'password' => str_repeat('a', 73)]],
            'null byte' => [['email' => 'one@example.com', 'password' => "password\0"]],
        ];
    }

    public function testRepeatedCallsHaveIndependentDataAndHashes(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::exactly(2))->method('existsByEmail')
            ->willReturn(false);
        $nextId = 0;
        $repository->expects(self::exactly(2))->method('create')
            ->willReturnCallback(function (string $email, string $hash) use (&$nextId): RegisteredUser {
                self::assertNotSame(' password123 ', $hash);
                self::assertTrue(password_verify(' password123 ', $hash));
                return new RegisteredUser(++$nextId, $email);
            });
        $events = $this->createMock(EventDispatcherInterface::class);
        $ids = [];
        $events->expects(self::exactly(2))->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$ids): object {
                self::assertInstanceOf(UserRegistered::class, $event);
                $ids[] = $event->userId;
                return $event;
            });
        $service = new RegisterUserService($repository, $events, $this->createMock(StdoutLoggerInterface::class));
        $first = $service->register(RegisterUserInput::fromArray([
            'email' => ' ONE@EXAMPLE.COM ', 'password' => ' password123 ',
        ]));
        $second = $service->register(RegisterUserInput::fromArray([
            'email' => 'two@example.com', 'password' => ' password123 ',
        ]));
        self::assertSame(['id' => 1, 'email' => 'one@example.com'], $first->toArray());
        self::assertSame(['id' => 2, 'email' => 'two@example.com'], $second->toArray());
        self::assertSame([1, 2], $ids);
    }

    public function testKnownDuplicateDoesNotWriteOrDispatch(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('existsByEmail')->willReturn(true);
        $repository->expects(self::never())->method('create');
        $events = $this->createMock(EventDispatcherInterface::class);
        $events->expects(self::never())->method('dispatch');
        $service = new RegisterUserService($repository, $events, $this->createMock(StdoutLoggerInterface::class));
        $this->expectExceptionCode(40901);
        $service->register(RegisterUserInput::fromArray(['email' => 'one@example.com', 'password' => 'password123']));
    }

    public function testConflictAfterPrecheckDoesNotDispatch(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('existsByEmail')->willReturn(false);
        $repository->method('create')->willThrowException(RegistrationException::emailTaken());
        $events = $this->createMock(EventDispatcherInterface::class);
        $events->expects(self::never())->method('dispatch');
        $service = new RegisterUserService($repository, $events, $this->createMock(StdoutLoggerInterface::class));
        $this->expectExceptionCode(40901);
        $service->register(RegisterUserInput::fromArray(['email' => 'one@example.com', 'password' => 'password123']));
    }

    public function testListenerAndLoggerFailureDoNotUndoRegistration(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('existsByEmail')->willReturn(false);
        $repository->method('create')->willReturn(new RegisteredUser(42, 'one@example.com'));
        $events = $this->createMock(EventDispatcherInterface::class);
        $events->method('dispatch')->willThrowException(new RuntimeException('listener failed'));
        $logger = $this->createMock(StdoutLoggerInterface::class);
        $logger->expects(self::once())->method('warning')->willThrowException(new RuntimeException('logger failed'));
        $service = new RegisterUserService($repository, $events, $logger);
        $user = $service->register(RegisterUserInput::fromArray(['email' => 'one@example.com', 'password' => 'password123']));
        self::assertSame(42, $user->id);
    }

    public function testExceptionHandlerMapsBusinessCodeToHttpStatus(): void
    {
        $handler = new RegistrationExceptionHandler();
        foreach ([RegistrationException::emailTaken(), RegistrationException::invalidInput('邮箱格式不正确')] as $exception) {
            self::assertTrue($handler->isValid($exception));
            $response = $handler->handle($exception, new Response());
            self::assertSame($exception->httpStatus, $response->getStatusCode());
            $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertFalse($body['ok']);
            self::assertSame($exception->getCode(), $body['code']);
        }
        self::assertFalse($handler->isValid(new RuntimeException()));
    }
}
