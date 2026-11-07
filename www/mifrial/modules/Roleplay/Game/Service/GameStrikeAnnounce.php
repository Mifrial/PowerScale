<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;

/**
 * Запись и сигнал команды удара.
 */
final class GameStrikeAnnounce
{
    /**
     * Создаёт сигнал команды.
     *
     * @param GameCommandRepository $commands Журнал команд.
     * @param GameDeliverySignal $deliverySignal Сигнал доставки.
     *
     * @return void
     */
    public function __construct(
        private readonly GameCommandRepository $commands,
        private readonly GameDeliverySignal $deliverySignal,
    ) {
    }

    /**
     * Сигнал доставки по id уже лежащей команды. Лист не пишет.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ.
     * @param string $source Источник команды.
     *
     * @return void
     */
    public function recorded(int $gameId, string $idempotencyKey, string $source = 'strike'): void
    {
        $stored = $this->commands->findByKey($gameId, $source, $idempotencyKey);
        if ($stored === null) {
            return;
        }

        $this->deliverySignal->recorded($gameId, 'strike', $stored['id'], []);
    }

    /**
     * Запоминает решение окна и шлёт realtime-сигнал.
     *
     * `resumeSingle`/`resumeWide` уже пишут команду `strike_window`/
     * `wide_window`; промежуточная фаза манёвра команды не пишет, поэтому
     * итог сохраняется здесь. Сигнал идёт как `strike` с id команды, чтобы
     * `GameDelivery` нашёл бой по журналу и другие клиенты обновили окно.
     *
     * @param int $gameId Игра.
     * @param int $sessionId Сессия.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $body Тело команды.
     * @param array<string, mixed> $result Итог окна.
     * @param string $source Источник команды.
     *
     * @return void
     */
    public function window(
        int $gameId,
        int $sessionId,
        string $idempotencyKey,
        array $body,
        array $result,
        string $source,
    ): void {
        $stored = $this->find($gameId, $source, $idempotencyKey);
        if ($stored === null) {
            $stored = $this->store($gameId, $sessionId, $source, $idempotencyKey, $body, $result);
        }

        if ($stored === null) {
            return;
        }

        $this->deliverySignal->recorded($gameId, 'strike', $stored['id'], []);
    }

    /**
     * Записывает команду окна и возвращает сохранённую строку.
     *
     * @param int $gameId Игра.
     * @param int $sessionId Сессия.
     * @param string $source Источник.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $body Тело.
     * @param array<string, mixed> $result Итог.
     *
     * @return array<string, mixed>|null Строка или null.
     */
    private function store(
        int $gameId,
        int $sessionId,
        string $source,
        string $idempotencyKey,
        array $body,
        array $result,
    ): ?array {
        try {
            $this->commands->add($gameId, $sessionId, $source, $idempotencyKey, $body, $result);
        } catch (GameBattleConflictException) {
            // Параллельный запрос уже записал команду.
        }

        return $this->find($gameId, $source, $idempotencyKey);
    }

    /**
     * Находит команду окна по ключу.
     *
     * @param int $gameId Игра.
     * @param string $source Источник.
     * @param string $idempotencyKey Ключ.
     *
     * @return array<string, mixed>|null Строка или null.
     */
    private function find(int $gameId, string $source, string $idempotencyKey): ?array
    {
        return $this->commands->findByKey($gameId, $source, $idempotencyKey);
    }
}
