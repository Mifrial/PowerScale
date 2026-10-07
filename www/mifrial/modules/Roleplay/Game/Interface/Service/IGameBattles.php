<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Interface\Service;

/**
 * Идентичность боя, состав и CAS. Лист не пишет.
 */
interface IGameBattles
{
    /**
     * Открывает новый бой.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param string $idempotencyKey Ключ повтора.
     * @param array $participants Пары type/id.
     *
     * @return array<string, mixed> Итог без листа.
     */
    public function start(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        string $idempotencyKey,
        array $participants,
    ): array;

    /**
     * Заменяет состав открытого боя.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ повтора.
     * @param array $participants Пары type/id.
     * @param int $expectedVersion Ожидаемая версия боя.
     *
     * @return array<string, mixed> Итог без листа.
     */
    public function setRoster(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        array $participants,
        int $expectedVersion,
    ): array;

    /**
     * Закрывает один бой. Сессию не останавливает.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ повтора.
     * @param int $expectedVersion Ожидаемая версия боя.
     *
     * @return array<string, mixed> Итог без листа.
     */
    public function end(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedVersion,
    ): array;
}
