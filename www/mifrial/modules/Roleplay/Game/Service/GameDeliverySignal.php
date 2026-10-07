<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Event\Interface\Service\IEventManager;
use Throwable;

/**
 * fire после commit. Ошибка слушателя ответ команды не меняет.
 */
final class GameDeliverySignal
{
    /**
     * Создаёт сигнал.
     *
     * @param IEventManager $events Порт.
     *
     * @return void
     */
    public function __construct(private readonly IEventManager $events)
    {
    }

    /**
     * Шлёт факт. Исключение слушателя глотает.
     *
     * @param int $gameId Игра.
     * @param string $source Источник economy или strike.
     * @param int $sourceId Id журнала.
     * @param array<int, array<string, mixed>> $keys Ключи.
     *
     * @return void
     */
    public function recorded(int $gameId, string $source, int $sourceId, array $keys): void
    {
        try {
            $this->events->fire(
                GameDeliveryListener::EVENT,
                new GameDeliveryPayload($gameId, $source, $sourceId, $keys),
            );
        } catch (Throwable) {
            return;
        }
    }
}
