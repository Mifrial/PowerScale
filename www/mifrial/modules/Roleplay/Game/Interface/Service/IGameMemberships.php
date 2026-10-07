<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Interface\Service;

use Mifrial\Roleplay\Game\Dto\GameCharacterRecord;
use Mifrial\Roleplay\Game\Exception\GameConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Строка персонажа в игре. Не пишет таблицу character.
 */
interface IGameMemberships
{
    /**
     * Подаёт существующего персонажа.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     * @param int $ownerUserId Актор, владелец листа.
     *
     * @return GameCharacterRecord Заявка.
     *
     * @throws GameNotFoundException Если нет игры, персонажа или актор не владелец.
     * @throws GameInvalidException Если completed или живой вход уже есть.
     */
    public function submit(int $gameId, int $characterId, int $ownerUserId): GameCharacterRecord;

    /**
     * Строка пары. Считает reviewState.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     *
     * @return GameCharacterRecord Строка.
     *
     * @throws GameNotFoundException Если нет строки или персонажа.
     * @throws GameInvalidException Если строка битая.
     */
    public function get(int $gameId, int $characterId): GameCharacterRecord;

    /**
     * Строки игры без чтения actual.
     *
     * @param int $gameId Игра.
     * @param int|null $ownerUserId Фильтр владельца или все.
     *
     * @return list<GameCharacterRecord> Строки без reviewState.
     *
     * @throws GameNotFoundException Если игры нет.
     * @throws GameInvalidException Если строка битая.
     */
    public function getListByGame(int $gameId, ?int $ownerUserId): array;

    /**
     * Пишет snapshot actual. Actual не меняет.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     * @param int $expectedActualVersion Ожидаемый actual_version.
     * @param int $expectedMembershipRevision Ожидаемая revision.
     *
     * @return GameCharacterRecord Строка.
     *
     * @throws GameNotFoundException Если нет строки или персонажа.
     * @throws GameInvalidException Если статус, completed.
     * @throws GameConflictException Если версия устарела.
     */
    public function approve(
        int $gameId,
        int $characterId,
        int $expectedActualVersion,
        int $expectedMembershipRevision,
    ): GameCharacterRecord;

    /**
     * Оставляет строку и пишет причину.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     * @param int $expectedMembershipRevision Ожидаемая revision.
     * @param string $reason Причина.
     * @param int $actorUserId Актор return.
     *
     * @return GameCharacterRecord Строка.
     *
     * @throws GameNotFoundException Если нет строки или персонажа.
     * @throws GameInvalidException Если статус, пустая причина или completed.
     * @throws GameConflictException Если revision устарела.
     */
    public function returnToOwner(
        int $gameId,
        int $characterId,
        int $expectedMembershipRevision,
        string $reason,
        int $actorUserId,
    ): GameCharacterRecord;

    /**
     * Удаляет только submitted.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     * @param int $expectedMembershipRevision Ожидаемая revision.
     *
     * @return void
     *
     * @throws GameNotFoundException Если нет строки.
     * @throws GameInvalidException Если статус не submitted или completed.
     * @throws GameConflictException Если revision устарела.
     */
    public function reject(int $gameId, int $characterId, int $expectedMembershipRevision): void;

    /**
     * Выход владельца персонажа.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     * @param int $ownerUserId Актор.
     * @param int $expectedMembershipRevision Ожидаемая revision.
     *
     * @return GameCharacterRecord Строка.
     *
     * @throws GameNotFoundException Если нет строки или актор не владелец.
     * @throws GameInvalidException Если статус или completed.
     * @throws GameConflictException Если revision устарела.
     */
    public function leave(
        int $gameId,
        int $characterId,
        int $ownerUserId,
        int $expectedMembershipRevision,
    ): GameCharacterRecord;

    /**
     * Пишет бонус строки.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     * @param int $expectedMembershipRevision Ожидаемая revision.
     * @param int $osBonus ОС.
     * @param int $orBonus ОР.
     * @param int $olBonus ОЛ.
     *
     * @return GameCharacterRecord Строка.
     *
     * @throws GameNotFoundException Если нет строки или персонажа.
     * @throws GameInvalidException Если статус, бонус или completed.
     * @throws GameConflictException Если revision устарела.
     */
    public function setBonus(
        int $gameId,
        int $characterId,
        int $expectedMembershipRevision,
        int $osBonus,
        int $orBonus,
        int $olBonus,
    ): GameCharacterRecord;

    /**
     * Потолок игры плюс бонус. null потолка остаётся null.
     *
     * @param int|null $gameCeiling Потолок игры.
     * @param int $bonus Бонус строки.
     *
     * @return int|null Лимит или null.
     */
    public function getPointsLimit(?int $gameCeiling, int $bonus): ?int;
}
