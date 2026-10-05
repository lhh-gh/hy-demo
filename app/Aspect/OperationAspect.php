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

namespace App\Aspect;

use App\Annotation\Operation;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Throwable;

/**
 *  1.匹配带有 Operation 注解的业务方法。
 * 2.读取方法或类上的操作名称。
 * 3.执行业务方法。
 * 4.无论成功还是失败，都记录结果和耗时。
 * 5.业务异常继续抛出，由控制器转成 HTTP 响应。
 */
#[Aspect]
class OperationAspect extends AbstractAspect
{
    public array $annotations = [Operation::class];

    public function __construct(private StdoutLoggerInterface $logger)
    {
    }

    public function process(
        ProceedingJoinPoint $proceedingJoinPoint,
    ): mixed {
        $metadata = $proceedingJoinPoint->getAnnotationMetadata();

        // 本 demo 规定：方法上的声明优先，其次使用类上的声明。
        $operation = $metadata->method[Operation::class]
            ?? $metadata->class[Operation::class]
            ?? null;

        if (! $operation instanceof Operation) {
            return $proceedingJoinPoint->process();
        }

        $startedAt = hrtime(true);

        $record = [
            'operation' => $operation->name,
            'target' => $proceedingJoinPoint->className
                . '::' . $proceedingJoinPoint->methodName,
            'success' => false,
        ];

        try {
            // 执行后续切面，最终执行原业务方法。
            $result = $proceedingJoinPoint->process();

            $record['success'] = true;

            return $result;
        } catch (Throwable $exception) {
            $record['exception'] = $exception::class;

            // 保留原业务异常。
            throw $exception;
        } finally {
            $record['duration_ms'] = round(
                (hrtime(true) - $startedAt) / 1_000_000,
                3,
            );

            try {
                $this->logger->log(
                    $record['success'] ? 'notice' : 'warning',
                    '[annotation-demo] ' . json_encode(
                        $record,
                        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                    ),
                );
            } catch (Throwable $logException) {
                // 普通操作日志失败时，不覆盖业务返回值或原异常。
                error_log(
                    '[annotation-demo] 日志写入失败：'
                    . $logException->getMessage(),
                );
            }
        }
    }
}
