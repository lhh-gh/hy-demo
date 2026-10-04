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

namespace App\Listener;

use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\AfterWorkerStart;
use Hyperf\Framework\Event\BeforeMainServerStart;
use Hyperf\Framework\Event\BeforeServerStart;
use Hyperf\Framework\Event\BeforeWorkerStart;
use Hyperf\Framework\Event\BootApplication;
use Hyperf\Framework\Event\MainWorkerStart;
use Hyperf\Framework\Event\OtherWorkerStart;
use ReflectionClass;

#[Listener]
class StartupTraceListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            BootApplication::class,
            BeforeMainServerStart::class,
            BeforeServerStart::class,
            BeforeWorkerStart::class,
            MainWorkerStart::class,
            OtherWorkerStart::class,
            AfterWorkerStart::class,
        ];
    }

    public function process(object $event): void
    {
        $eventName = (new ReflectionClass($event))->getShortName();
        $workerId = property_exists($event, 'workerId')
            ? (string) $event->workerId
            : '-';
        printf(
            "[startup] event=%s pid=%d worker=%s\n",
            $eventName,
            getmypid(),
            $workerId
        );
    }
}
