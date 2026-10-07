<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Interface\Service;

/**
 * Магазин и typed EconomyOperation.
 */
interface IGameEconomy
{
    /**
     * Проводит операцию или возвращает прежний итог того же ключа.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param string $idempotencyKey Ключ повтора.
     * @param array $parts Части.
     * @param array $expectedVersions Ожидаемые версии.
     *
     * @return array<string, mixed> Итог без листа.
     */
    public function apply(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        string $idempotencyKey,
        array $parts,
        array $expectedVersions,
    ): array;

    /**
     * Позиции магазина.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     *
     * @return array<string, mixed> Список.
     */
    public function getShop(int $gameId, int $actorUserId, bool $viewAll): array;

    /**
     * Заменяет набор позиций.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param array $positions Будущий набор.
     * @param array $expectedPositions Текущие версии, включая удаляемые.
     *
     * @return array<string, mixed> Набор.
     */
    public function replaceShop(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $positions,
        array $expectedPositions,
    ): array;
}
