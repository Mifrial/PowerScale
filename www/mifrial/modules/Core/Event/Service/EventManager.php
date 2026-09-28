<?php

declare(strict_types=1);

namespace Mifrial\Core\Event\Service;

use Mifrial\Core\Event\Exception\EventException;
use Mifrial\Core\Event\Interface\Service\IEventListener;
use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Event\Interface\Value\IEventPayload;
use Mifrial\Core\Event\Interface\Value\IEventResult;
use Mifrial\Core\Event\Value\EventResult;
use Mifrial\Core\Event\Value\EventSubscription;
use Mifrial\Core\Kernel\Exception\MifrialException;
use Throwable;

/**
 * Синхронный process-local менеджер runtime-событий.
 */
final class EventManager implements IEventManager
{
    /**
     * @var array<string, array<int, array{
     *     listener: IEventListener,
     *     priority: int,
     *     sequence: int,
     *     subscription: EventSubscription
     * }>>
     */
    private array $listeners = [];

    /**
     * @var array<int, string>
     */
    private array $subscriptionEvents = [];

    /**
     * @var array<string, true>
     */
    private array $activeEventNames = [];

    /**
     * @var array<int, string>
     */
    private array $dispatchStack = [];

    private int $nextSubscriptionId = 1;

    private int $nextRegistrationSequence = 0;

    /**
     * Регистрирует listener события.
     *
     * @param string $eventName Canonical имя события.
     * @param IEventListener $listener Listener.
     * @param int $priority Приоритет, меньшее значение выполняется раньше.
     *
     * @return EventSubscription Непрозрачный token подписки.
     *
     * @throws EventException Если имя события некорректно.
     */
    public function on(
        string $eventName,
        IEventListener $listener,
        int $priority = 0,
    ): EventSubscription {
        $this->assertEventName($eventName);

        $subscription = EventSubscription::create($this, $this->nextSubscriptionId);
        ++$this->nextSubscriptionId;
        ++$this->nextRegistrationSequence;

        $this->listeners[$eventName] ??= [];
        $this->listeners[$eventName][] = [
            'listener' => $listener,
            'priority' => $priority,
            'sequence' => $this->nextRegistrationSequence,
            'subscription' => $subscription,
        ];
        $this->subscriptionEvents[$subscription->getId()] = $eventName;

        return $subscription;
    }

    /**
     * Снимает одну подписку.
     *
     * @param EventSubscription $subscription Token подписки.
     *
     * @return void
     *
     * @throws EventException Если token выдан другим менеджером.
     */
    public function off(EventSubscription $subscription): void
    {
        if (!$subscription->isOwnedBy($this)) {
            throw new EventException('EVENT_INVALID', 'Subscription belongs to another EventManager');
        }

        $subscriptionId = $subscription->getId();
        $eventName = $this->subscriptionEvents[$subscriptionId] ?? null;
        if ($eventName === null) {
            return;
        }

        $removed = false;
        foreach ($this->listeners[$eventName] as $index => $entry) {
            if ($entry['subscription'] === $subscription) {
                unset($this->listeners[$eventName][$index]);
                $removed = true;
                break;
            }
        }

        if (!$removed) {
            return;
        }

        $this->listeners[$eventName] = array_values($this->listeners[$eventName]);
        unset($this->subscriptionEvents[$subscriptionId]);
    }

    /**
     * Синхронно доставляет событие listeners.
     *
     * @param string $eventName Canonical имя события.
     * @param IEventPayload $payload Неизменяемый payload.
     *
     * @return IEventResult Агрегированный результат dispatch.
     *
     * @throws EventException Если имя события некорректно или обнаружена рекурсия.
     */
    public function fire(string $eventName, IEventPayload $payload): IEventResult
    {
        $this->assertEventName($eventName);
        $this->beginDispatch($eventName);

        try {
            $result = new EventResult();
            foreach ($this->getSnapshot($eventName) as $entry) {
                if ($this->dispatchEntry($result, $entry, $payload)) {
                    break;
                }
            }

            return $result;
        } finally {
            $this->endDispatch($eventName);
        }
    }

    /**
     * Обрабатывает один элемент snapshot.
     *
     * @param EventResult $aggregate Aggregate-результат.
     * @param array $entry Snapshot entry.
     * @param IEventPayload $payload Payload события.
     *
     * @return bool true, если цепочку нужно остановить.
     *
     * @throws MifrialException Если listener выбросил ошибку проекта.
     * @throws EventException Если listener выбросил чужой Throwable.
     */
    private function dispatchEntry(
        EventResult $aggregate,
        array $entry,
        IEventPayload $payload,
    ): bool {
        try {
            $listenerResult = $this->invokeListener($entry['listener'], $payload);
            $stopDispatch = $listenerResult !== null && !$listenerResult->isSuccessful();
            $aggregate->merge($listenerResult ?? new EventResult(), $stopDispatch);

            return $stopDispatch;
        } catch (MifrialException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new EventException(
                'EVENT_LISTENER_FAILED',
                'Event listener result failed',
                $throwable,
            );
        }
    }

    /**
     * Вызывает один listener и приводит чужой Throwable к EventException.
     *
     * @param IEventListener $listener Listener.
     * @param IEventPayload $payload Payload.
     *
     * @return IEventResult|null Результат listener-а.
     *
     * @throws MifrialException Если listener выбросил ошибку проекта.
     * @throws EventException Если listener выбросил чужой Throwable.
     */
    private function invokeListener(
        IEventListener $listener,
        IEventPayload $payload,
    ): ?IEventResult {
        try {
            return $listener->handle($payload);
        } catch (MifrialException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new EventException(
                'EVENT_LISTENER_FAILED',
                'Event listener failed',
                $throwable,
            );
        }
    }

    /**
     * Возвращает snapshot listeners в deterministic порядке.
     *
     * @param string $eventName Имя события.
     *
     * @return array<int, array{
     *     listener: IEventListener,
     *     priority: int,
     *     sequence: int,
     *     subscription: EventSubscription
     * }> Snapshot listeners.
     */
    private function getSnapshot(string $eventName): array
    {
        $snapshot = array_values($this->listeners[$eventName] ?? []);
        usort($snapshot, [$this, 'compareListeners']);

        return $snapshot;
    }

    /**
     * Сравнивает listeners по priority и последовательности регистрации.
     *
     * @param array{priority: int, sequence: int} $left Первый listener.
     * @param array{priority: int, sequence: int} $right Второй listener.
     *
     * @return int Результат сравнения.
     */
    private function compareListeners(array $left, array $right): int
    {
        return ($left['priority'] <=> $right['priority'])
            ?: ($left['sequence'] <=> $right['sequence']);
    }

    /**
     * Проверяет canonical event name.
     *
     * @param string $eventName Имя события.
     *
     * @return void
     *
     * @throws EventException Если имя пустое или содержит внешние пробелы.
     */
    private function assertEventName(string $eventName): void
    {
        $trimmedEventName = trim($eventName);
        $matchesFormat = preg_match(
            '/^[A-Za-z][A-Za-z0-9_]*\\\\[A-Za-z][A-Za-z0-9_]*\.[A-Za-z][A-Za-z0-9_]*::[A-Za-z][A-Za-z0-9_]*$/D',
            $eventName,
        ) === 1;
        if ($trimmedEventName === '' || $trimmedEventName !== $eventName || !$matchesFormat) {
            throw new EventException(
                'EVENT_INVALID',
                'Event name must match ModuleGroup\\ModuleName.Subject::LifecycleOperation',
            );
        }
    }

    /**
     * Начинает dispatch и проверяет активный event cycle.
     *
     * @param string $eventName Имя события.
     *
     * @return void
     *
     * @throws EventException Если событие уже активно в текущем стеке.
     */
    private function beginDispatch(string $eventName): void
    {
        if (isset($this->activeEventNames[$eventName])) {
            $chain = [...$this->dispatchStack, $eventName];
            throw new EventException(
                'EVENT_RECURSION',
                'Recursive event dispatch: ' . implode(' -> ', $chain),
            );
        }

        $this->activeEventNames[$eventName] = true;
        $this->dispatchStack[] = $eventName;
    }

    /**
     * Завершает текущий dispatch.
     *
     * @param string $eventName Имя события.
     *
     * @return void
     */
    private function endDispatch(string $eventName): void
    {
        array_pop($this->dispatchStack);
        unset($this->activeEventNames[$eventName]);
    }
}
