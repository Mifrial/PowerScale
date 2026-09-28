<?php

declare(strict_types=1);

namespace Mifrial\Core\Event\Value;

use Mifrial\Core\Event\Exception\EventException;

/**
 * Непрозрачный process-local token подписки EventManager.
 */
final class EventSubscription
{
    /**
     * Создаёт token.
     *
     * @param object $owner Владелец token.
     * @param int $id Id token.
     *
     * @return void
     */
    private function __construct(
        private readonly object $owner,
        private readonly int $id,
    ) {
    }

    /**
     * Создаёт token, принадлежащий одному EventManager.
     *
     * @param object $owner Владелец token.
     * @param int $id Уникальный id в пределах владельца.
     *
     * @return self Token подписки.
     *
     * @throws EventException Если id некорректен.
     */
    public static function create(object $owner, int $id): self
    {
        if ($id < 1) {
            throw new EventException('EVENT_INVALID', 'Subscription id must be positive');
        }

        return new self($owner, $id);
    }

    /**
     * Проверяет владельца token.
     *
     * @param object $owner Предполагаемый владелец.
     *
     * @return bool true, если token выдан владельцем.
     */
    public function isOwnedBy(object $owner): bool
    {
        return $this->owner === $owner;
    }

    /**
     * Возвращает внутренний id token.
     *
     * @return int Id подписки.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Запрещает сериализацию process-local token.
     *
     * @return array<string, never> Не возвращается.
     *
     * @throws EventException Всегда, потому что token process-local.
     */
    public function __serialize(): array
    {
        return $this->throwSerializationException();
    }

    /**
     * Запрещает восстановление token из сериализованных данных.
     *
     * @param array<string, mixed> $data Сериализованные данные.
     *
     * @return void
     *
     * @throws EventException Всегда, потому что token process-local.
     */
    public function __unserialize(array $data): void
    {
        unset($data);

        $this->throwSerializationException();
    }

    /**
     * Выбрасывает ошибку для попытки переноса token между процессами.
     *
     * @return never Не возвращается.
     *
     * @throws EventException Всегда.
     */
    private function throwSerializationException(): never
    {
        throw new EventException('EVENT_INVALID', 'Event subscription is process-local');
    }
}
