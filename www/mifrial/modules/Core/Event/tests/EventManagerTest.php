<?php

declare(strict_types=1);

namespace Mifrial\Core\Event\Tests;

use Closure;
use LogicException;
use Mifrial\Core\Event\Exception\EventException;
use Mifrial\Core\Event\Interface\Service\IEventListener;
use Mifrial\Core\Event\Interface\Value\IEventIssue;
use Mifrial\Core\Event\Interface\Value\IEventPayload;
use Mifrial\Core\Event\Interface\Value\IEventResult;
use Mifrial\Core\Event\Service\EventManager;
use Mifrial\Core\Event\Value\EventIssue;
use Mifrial\Core\Event\Value\EventResult;
use Mifrial\Core\Event\Value\EventSubscription;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет синхронную семантику EventManager.
 */
final class EventManagerTest extends TestCase
{
    /**
     * Проверяет priority и FIFO для одинакового priority.
     *
     * @return void
     */
    public function testListenersRunByPriorityAndFifo(): void
    {
        $eventManager = new EventManager();
        $payload = new ArrayPayload([]);
        $calls = [];

        $eventManager->on(
            'TestGroup\TestModule.Test::beforeCreate',
            new RecordingListener(static function () use (&$calls): ?IEventResult {
                $calls[] = 'first';

                return null;
            }),
            priority: 100,
        );
        $eventManager->on(
            'TestGroup\TestModule.Test::beforeCreate',
            new RecordingListener(static function () use (&$calls): ?IEventResult {
                $calls[] = 'second';

                return null;
            }),
            priority: 100,
        );
        $eventManager->on(
            'TestGroup\TestModule.Test::beforeCreate',
            new RecordingListener(static function () use (&$calls): ?IEventResult {
                $calls[] = 'early';

                return null;
            }),
            priority: 10,
        );

        $result = $eventManager->fire('TestGroup\TestModule.Test::beforeCreate', $payload);

        self::assertSame(['early', 'first', 'second'], $calls);
        self::assertTrue($result->isSuccessful());
        self::assertSame(3, $result->getInvokedCount());
    }

    /**
     * Проверяет duplicate registration и token-only off.
     *
     * @return void
     */
    public function testDuplicateSubscriptionsAreIndependent(): void
    {
        $eventManager = new EventManager();
        $calls = 0;
        $listener = new RecordingListener(static function () use (&$calls): ?IEventResult {
            ++$calls;

            return null;
        });

        $firstSubscription = $eventManager->on('TestGroup\TestModule.Test::event', $listener);
        $secondSubscription = $eventManager->on('TestGroup\TestModule.Test::event', $listener);
        $eventManager->off($firstSubscription);

        $eventManager->fire('TestGroup\TestModule.Test::event', new ArrayPayload([]));
        self::assertSame(1, $calls);

        $eventManager->off($firstSubscription);
        $eventManager->off($secondSubscription);
        $eventManager->fire('TestGroup\TestModule.Test::event', new ArrayPayload([]));
        self::assertSame(1, $calls);
    }

    /**
     * Проверяет null как успешный результат listener-а.
     *
     * @return void
     */
    public function testNullListenerResultIsSuccessful(): void
    {
        $eventManager = new EventManager();
        $eventManager->on(
            'TestGroup\TestModule.Test::event',
            new RecordingListener(static fn (): ?IEventResult => null),
        );

        $result = $eventManager->fire(
            'TestGroup\TestModule.Test::event',
            new ArrayPayload([]),
        );

        self::assertTrue($result->isSuccessful());
        self::assertSame(1, $result->getInvokedCount());
        self::assertFalse($result->isStopped());
    }

    /**
     * Проверяет aggregation errors, warnings и payloads с fail-fast.
     *
     * @return void
     */
    public function testResultAggregatesIssuesAndStopsOnError(): void
    {
        $eventManager = new EventManager();
        $calls = [];
        $eventManager->on(
            'TestGroup\TestModule.Test::event',
            new RecordingListener(static function () use (&$calls): ?IEventResult {
                $calls[] = 'warning';
                $result = new EventResult();
                $result->addWarning(new EventIssue('NORMALIZED', 'Name normalized'));
                $result->addPayload(new ArrayPayload(['source' => 'warning']));

                return $result;
            }),
        );
        $eventManager->on(
            'TestGroup\TestModule.Test::event',
            new RecordingListener(static function () use (&$calls): ?IEventResult {
                $calls[] = 'error';
                $result = new EventResult();
                $result->addError(new EventIssue('INVALID', 'Name is invalid'));
                $result->addPayload(new ArrayPayload(['source' => 'error']));

                return $result;
            }),
            priority: 10,
        );
        $eventManager->on(
            'TestGroup\TestModule.Test::event',
            new RecordingListener(static function () use (&$calls): ?IEventResult {
                $calls[] = 'skipped';

                return null;
            }),
            priority: 20,
        );

        $result = $eventManager->fire(
            'TestGroup\TestModule.Test::event',
            new ArrayPayload([]),
        );

        self::assertSame(['warning', 'error'], $calls);
        self::assertFalse($result->isSuccessful());
        self::assertTrue($result->isStopped());
        self::assertCount(1, $result->getWarnings());
        self::assertCount(1, $result->getErrors());
        self::assertCount(2, $result->getPayloads());
        self::assertSame(2, $result->getInvokedCount());
    }

    /**
     * Проверяет failure custom result без errors.
     *
     * @return void
     */
    public function testCustomFailureWithoutErrorsStopsAggregate(): void
    {
        $eventManager = new EventManager();
        $calls = [];
        $eventManager->on(
            'TestGroup\TestModule.Test::event',
            new RecordingListener(static function (): ?IEventResult {
                return new FalseResult();
            }),
        );
        $eventManager->on(
            'TestGroup\TestModule.Test::event',
            new RecordingListener(static function () use (&$calls): ?IEventResult {
                $calls[] = 'unexpected';

                return null;
            }),
            priority: 10,
        );

        $result = $eventManager->fire(
            'TestGroup\TestModule.Test::event',
            new ArrayPayload([]),
        );

        self::assertFalse($result->isSuccessful());
        self::assertTrue($result->isStopped());
        self::assertSame(1, $result->getInvokedCount());
        self::assertSame([], $calls);
    }

    /**
     * Проверяет snapshot semantics при add/off во время fire.
     *
     * @return void
     */
    public function testMutationDuringFireUsesSnapshot(): void
    {
        $eventManager = new EventManager();
        $calls = [];
        $secondSubscription = null;
        $eventManager->on(
            'TestGroup\TestModule.Test::event',
            new RecordingListener(
                static function () use (&$calls, &$secondSubscription, $eventManager): ?IEventResult {
                    $calls[] = 'first';
                    if ($secondSubscription instanceof EventSubscription) {
                        $eventManager->off($secondSubscription);
                    }
                    $eventManager->on(
                        'TestGroup\TestModule.Test::event',
                        new RecordingListener(static function () use (&$calls): ?IEventResult {
                            $calls[] = 'added';

                            return null;
                        }),
                    );

                    return null;
                },
            ),
        );
        $secondSubscription = $eventManager->on(
            'TestGroup\TestModule.Test::event',
            new RecordingListener(static function () use (&$calls): ?IEventResult {
                $calls[] = 'second';

                return null;
            }),
        );

        $eventManager->fire('TestGroup\TestModule.Test::event', new ArrayPayload([]));
        self::assertSame(['first', 'second'], $calls);

        $calls = [];
        $eventManager->fire('TestGroup\TestModule.Test::event', new ArrayPayload([]));
        self::assertSame(['first', 'added'], $calls);
    }

    /**
     * Проверяет вложенные события без цикла.
     *
     * @return void
     */
    public function testNestedDifferentEventsAreAllowed(): void
    {
        $eventManager = new EventManager();
        $calls = [];
        $eventManager->on(
            'TestGroup\TestModule.Test::outer',
            new RecordingListener(static function () use ($eventManager, &$calls): ?IEventResult {
                $calls[] = 'outer';
                $eventManager->fire(
                    'TestGroup\TestModule.Test::inner',
                    new ArrayPayload([]),
                );

                return null;
            }),
        );
        $eventManager->on(
            'TestGroup\TestModule.Test::inner',
            new RecordingListener(static function () use (&$calls): ?IEventResult {
                $calls[] = 'inner';

                return null;
            }),
        );

        $result = $eventManager->fire(
            'TestGroup\TestModule.Test::outer',
            new ArrayPayload([]),
        );

        self::assertTrue($result->isSuccessful());
        self::assertSame(['outer', 'inner'], $calls);
    }

    /**
     * Проверяет прямой и косвенный event cycle.
     *
     * @return void
     */
    public function testEventCycleThrowsWithDiagnosticChain(): void
    {
        $eventManager = new EventManager();
        $eventManager->on(
            'TestGroup\TestModule.Test::direct',
            new RecordingListener(static function (IEventPayload $payload) use ($eventManager): ?IEventResult {
                $eventManager->fire('TestGroup\TestModule.Test::direct', $payload);

                return null;
            }),
        );
        $eventManager->on(
            'TestGroup\TestModule.Test::a',
            new RecordingListener(static function (IEventPayload $payload) use ($eventManager): ?IEventResult {
                $eventManager->fire('TestGroup\TestModule.Test::b', $payload);

                return null;
            }),
        );
        $eventManager->on(
            'TestGroup\TestModule.Test::b',
            new RecordingListener(static function (IEventPayload $payload) use ($eventManager): ?IEventResult {
                $eventManager->fire('TestGroup\TestModule.Test::a', $payload);

                return null;
            }),
        );

        try {
            $eventManager->fire('TestGroup\TestModule.Test::direct', new ArrayPayload([]));
            self::fail('Direct event cycle did not throw');
        } catch (EventException $exception) {
            self::assertSame('EVENT_RECURSION', $exception->getErrorCode());
            self::assertStringContainsString(
                'TestGroup\TestModule.Test::direct -> TestGroup\TestModule.Test::direct',
                $exception->getMessage(),
            );
        }

        try {
            $eventManager->fire('TestGroup\TestModule.Test::a', new ArrayPayload([]));
            self::fail('Indirect event cycle did not throw');
        } catch (EventException $exception) {
            self::assertSame('EVENT_RECURSION', $exception->getErrorCode());
            self::assertStringContainsString(
                'TestGroup\TestModule.Test::a -> TestGroup\TestModule.Test::b -> TestGroup\TestModule.Test::a',
                $exception->getMessage(),
            );
        }

        $result = $eventManager->fire('TestGroup\TestModule.Test::free', new ArrayPayload([]));
        self::assertTrue($result->isSuccessful());
    }

    /**
     * Проверяет wrapping чужого Throwable с сохранением previous.
     *
     * @return void
     */
    public function testForeignListenerThrowableIsWrapped(): void
    {
        $eventManager = new EventManager();
        $eventManager->on(
            'TestGroup\TestModule.Test::event',
            new RecordingListener(static function (): ?IEventResult {
                throw new LogicException('foreign failure');
            }),
        );

        try {
            $eventManager->fire(
                'TestGroup\TestModule.Test::event',
                new ArrayPayload([]),
            );
            self::fail('Foreign listener throwable did not throw');
        } catch (EventException $exception) {
            self::assertSame('EVENT_LISTENER_FAILED', $exception->getErrorCode());
            self::assertInstanceOf(LogicException::class, $exception->getPrevious());
        }
    }

    /**
     * Проверяет, что MifrialException listener-а не заменяется.
     *
     * @return void
     */
    public function testMifrialListenerExceptionIsRethrown(): void
    {
        $eventManager = new EventManager();
        $expected = new EventException('CUSTOM', 'custom failure');
        $eventManager->on(
            'TestGroup\TestModule.Test::event',
            new RecordingListener(static function () use ($expected): ?IEventResult {
                throw $expected;
            }),
        );

        try {
            $eventManager->fire('TestGroup\TestModule.Test::event', new ArrayPayload([]));
            self::fail('Mifrial listener exception did not throw');
        } catch (EventException $exception) {
            self::assertSame($expected, $exception);
        }
    }

    /**
     * Проверяет canonical name и ownership token.
     *
     * @return void
     */
    public function testInvalidNameAndForeignTokenAreRejected(): void
    {
        $eventManager = new EventManager();

        try {
            $eventManager->fire('Test::event', new ArrayPayload([]));
            self::fail('Short event name did not throw');
        } catch (EventException $exception) {
            self::assertSame('EVENT_INVALID', $exception->getErrorCode());
        }

        $foreignManager = new EventManager();
        $subscription = $foreignManager->on(
            'TestGroup\TestModule.Test::event',
            new RecordingListener(static fn (): ?IEventResult => null),
        );

        try {
            $eventManager->off($subscription);
            self::fail('Foreign subscription did not throw');
        } catch (EventException $exception) {
            self::assertSame('EVENT_INVALID', $exception->getErrorCode());
        }

        $ownedManager = new EventManager();
        $realSubscription = $ownedManager->on(
            'TestGroup\TestModule.Test::event',
            new RecordingListener(static fn (): ?IEventResult => null),
        );
        $forgedSubscription = EventSubscription::create(
            $ownedManager,
            $realSubscription->getId(),
        );
        $ownedManager->off($forgedSubscription);
        $ownedManager->off($realSubscription);

        $result = $ownedManager->fire(
            'TestGroup\TestModule.Test::event',
            new ArrayPayload([]),
        );
        self::assertSame(0, $result->getInvokedCount());
    }
}

/**
 * Минимальный read-only payload для тестов.
 */
final class ArrayPayload implements IEventPayload
{
    /**
     * Создаёт payload.
     *
     * @param array<string, mixed> $values Значения payload.
     *
     * @return void
     */
    public function __construct(
        private readonly array $values,
    ) {
    }

    /**
     * Проверяет наличие ключа.
     *
     * @param string $key Ключ.
     *
     * @return bool true, если ключ существует.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * Возвращает значение ключа.
     *
     * @param string $key Ключ.
     *
     * @return mixed Значение.
     *
     * @throws EventException Если ключ отсутствует.
     */
    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            throw new EventException('EVENT_INVALID', 'Payload key does not exist: ' . $key);
        }

        return $this->values[$key];
    }
}

/**
 * Listener-адаптер для unit tests.
 */
final class RecordingListener implements IEventListener
{
    /**
     * Создаёт listener с callback.
     *
     * @param Closure $callback Callback обработки payload.
     *
     * @return void
     */
    public function __construct(
        private readonly Closure $callback,
    ) {
    }

    /**
     * Передаёт payload callback.
     *
     * @param IEventPayload $payload Payload события.
     *
     * @return IEventResult|null Результат callback.
     */
    public function handle(IEventPayload $payload): ?IEventResult
    {
        return ($this->callback)($payload);
    }
}

/**
 * Некорректный для errors-only сценария custom result.
 */
final class FalseResult implements IEventResult
{
    /**
     * Добавляет warning.
     *
     * @param IEventIssue $issue Проблема.
     *
     * @return void
     */
    public function addWarning(
        IEventIssue $issue,
    ): void {
        unset($issue);
    }

    /**
     * Добавляет ошибку.
     *
     * @param IEventIssue $issue Проблема.
     *
     * @return void
     */
    public function addError(
        IEventIssue $issue,
    ): void {
        unset($issue);
    }

    /**
     * Добавляет payload.
     *
     * @param IEventPayload $payload Payload.
     *
     * @return void
     */
    public function addPayload(IEventPayload $payload): void
    {
        unset($payload);
    }

    /**
     * Возвращает warnings.
     *
     * @return array<int, IEventIssue> Warnings.
     */
    public function getWarnings(): array
    {
        return [];
    }

    /**
     * Возвращает ошибки.
     *
     * @return array<int, IEventIssue> Ошибки.
     */
    public function getErrors(): array
    {
        return [];
    }

    /**
     * Возвращает payloads.
     *
     * @return array<int, IEventPayload> Payloads.
     */
    public function getPayloads(): array
    {
        return [];
    }

    /**
     * Проверяет успешность.
     *
     * @return bool Всегда false.
     */
    public function isSuccessful(): bool
    {
        return false;
    }

    /**
     * Проверяет остановку.
     *
     * @return bool Всегда false.
     */
    public function isStopped(): bool
    {
        return false;
    }

    /**
     * Возвращает число вызовов.
     *
     * @return int Всегда 0.
     */
    public function getInvokedCount(): int
    {
        return 0;
    }

    /**
     * Объединяет результат.
     *
     * @param IEventResult $result Результат.
     * @param bool $stop Флаг остановки.
     *
     * @return void
     */
    public function merge(IEventResult $result, bool $stop = false): void
    {
        unset($result, $stop);
    }
}
