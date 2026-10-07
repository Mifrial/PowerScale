<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Event\Exception\EventException;
use Mifrial\Core\Event\Interface\Value\IEventPayload;

/**
 * Факт уже применённой команды. Листа в payload нет.
 */
final class GameDeliveryPayload implements IEventPayload
{
    /**
     * Создаёт payload.
     *
     * @param int $gameId Игра.
     * @param string $source Источник economy или strike.
     * @param int $sourceId Id журнала.
     * @param array<int, array<string, mixed>> $keys Ключи.
     *
     * @return void
     */
    public function __construct(
        private readonly int $gameId,
        private readonly string $source,
        private readonly int $sourceId,
        private readonly array $keys,
    ) {
    }

    /**
     * Проверяет ключ.
     *
     * @param string $key Имя.
     *
     * @return bool true, если ключ есть.
     */
    public function has(string $key): bool
    {
        return in_array($key, ['gameId', 'source', 'sourceId', 'keys'], true);
    }

    /**
     * Возвращает значение.
     *
     * @param string $key Имя.
     *
     * @return mixed Значение.
     *
     * @throws EventException Если ключа нет.
     */
    public function get(string $key): mixed
    {
        return match ($key) {
            'gameId' => $this->gameId,
            'source' => $this->source,
            'sourceId' => $this->sourceId,
            'keys' => $this->keys,
            default => throw new EventException('EVENT_INVALID', 'Delivery payload has no ' . $key),
        };
    }
}
