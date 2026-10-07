<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Interface\Service;

use Mifrial\Roleplay\Game\Dto\GameMemberPatch;
use Mifrial\Roleplay\Game\Dto\GameMemberRecord;
use Mifrial\Roleplay\Game\Dto\GamePatch;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Dto\NewGameMember;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Строка игры без HTTP и без сессии.
 */
interface IGames
{
    /**
     * Создаёт игру в мире и ревизии.
     *
     * @param NewGame $new Поля.
     *
     * @return int Id.
     *
     * @throws GameNotFoundException Если нет владельца, мира или ревизии.
     * @throws GameInvalidException Если вход или мир выключен.
     */
    public function add(NewGame $new): int;

    /**
     * Строка по id. Без ACL.
     *
     * @param int $id Игра.
     *
     * @return GameRecord Игра.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если строка битая.
     */
    public function get(int $id): GameRecord;

    /**
     * Переписывает изменяемые поля. completed не пишет.
     *
     * @param int $id Игра.
     * @param GamePatch $patch Поля.
     *
     * @return GameRecord Игра.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если completed или вход.
     */
    public function update(int $id, GamePatch $patch): GameRecord;

    /**
     * Свои строки или все.
     *
     * @param int $ownerUserId Владелец.
     * @param bool $viewAll Все строки.
     *
     * @return list<GameRecord> Игры.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function getListByOwnerOrAll(int $ownerUserId, bool $viewAll): array;

    /**
     * Добавляет участника, не владельца.
     *
     * @param NewGameMember $new Поля.
     *
     * @return GameMemberRecord Строка.
     *
     * @throws GameNotFoundException Если нет игры или учётки.
     * @throws GameInvalidException Если владелец, роль, пара или completed.
     */
    public function addMember(NewGameMember $new): GameMemberRecord;

    /**
     * Строка пары. Без ACL.
     *
     * @param int $gameId Игра.
     * @param int $userId Учётка.
     *
     * @return GameMemberRecord Участник.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если строка битая.
     */
    public function getMember(int $gameId, int $userId): GameMemberRecord;

    /**
     * Меняет роль. completed не пишет.
     *
     * @param int $gameId Игра.
     * @param int $userId Учётка.
     * @param GameMemberPatch $patch Роль.
     *
     * @return GameMemberRecord Участник.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если роль или completed.
     */
    public function updateMember(int $gameId, int $userId, GameMemberPatch $patch): GameMemberRecord;

    /**
     * Снимает участника. completed не удаляет.
     *
     * @param int $gameId Игра.
     * @param int $userId Учётка.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если completed.
     */
    public function deleteMember(int $gameId, int $userId): void;

    /**
     * Участники игры. Владельца в списке нет.
     *
     * @param int $gameId Игра.
     *
     * @return list<GameMemberRecord> Строки.
     *
     * @throws GameNotFoundException Если игры нет.
     * @throws GameInvalidException Если строка битая.
     */
    public function getMemberList(int $gameId): array;
}
