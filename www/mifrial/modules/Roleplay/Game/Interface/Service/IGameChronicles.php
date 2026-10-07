<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Interface\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Dto\GameChronicleEntryRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Записи летописи одной игры. Не лист и не сессия.
 */
interface IGameChronicles
{
    /**
     * Открывает карточку для чтения летописи.
     *
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $gameId Игра.
     *
     * @return void
     *
     * @throws GameNotFoundException Если игры нет или карточка скрыта.
     */
    public function requireVisible(int $actorUserId, bool $viewAll, int $gameId): void;

    /**
     * Список по смещению.
     *
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $gameId Игра.
     *
     * @return list<GameChronicleEntryRecord> Строки.
     *
     * @throws GameNotFoundException Если карточка скрыта.
     * @throws GameInvalidException Если строка битая.
     */
    public function getEntries(int $actorUserId, bool $viewAll, int $gameId): array;

    /**
     * Создаёт запись.
     *
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     * @param bool $editAll Ключ game.edit_all.
     * @param int $gameId Игра.
     * @param string $title Заголовок.
     * @param string $content Текст.
     * @param array<mixed> $offset Шесть единиц.
     *
     * @return GameChronicleEntryRecord Строка.
     *
     * @throws GameNotFoundException Если карточка скрыта.
     * @throws GameInvalidException Если время или заголовок.
     * @throws ActionException AUTH_DENIED если карточка видна, а писать нельзя.
     */
    public function addEntry(
        int $actorUserId,
        bool $viewAll,
        bool $editAll,
        int $gameId,
        string $title,
        string $content,
        array $offset,
    ): GameChronicleEntryRecord;

    /**
     * Меняет запись.
     *
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     * @param bool $editAll Ключ game.edit_all.
     * @param int $entryId Запись.
     * @param string $title Заголовок.
     * @param string $content Текст.
     * @param array<mixed> $offset Шесть единиц.
     *
     * @return GameChronicleEntryRecord Строка.
     *
     * @throws GameNotFoundException Если записи нет или карточка скрыта.
     * @throws GameInvalidException Если время или заголовок.
     * @throws ActionException AUTH_DENIED если писать нельзя.
     */
    public function updateEntry(
        int $actorUserId,
        bool $viewAll,
        bool $editAll,
        int $entryId,
        string $title,
        string $content,
        array $offset,
    ): GameChronicleEntryRecord;

    /**
     * Удаляет запись.
     *
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     * @param bool $editAll Ключ game.edit_all.
     * @param int $entryId Запись.
     *
     * @return void
     *
     * @throws GameNotFoundException Если записи нет или карточка скрыта.
     * @throws ActionException AUTH_DENIED если писать нельзя.
     */
    public function deleteEntry(int $actorUserId, bool $viewAll, bool $editAll, int $entryId): void;
}
