<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Dto\GameCharacterRecord;
use Mifrial\Roleplay\Game\Dto\GameNpcRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGameMemberships;
use Mifrial\Roleplay\Game\Interface\Service\IGameProjections;

/**
 * Roster и лист по ключам. Запись сессии и боя не делает.
 */
final class GameProjections implements IGameProjections
{
    /**
     * Создаёт чтение.
     *
     * @param IGameMemberships $memberships Строки персонажа.
     * @param GameProjectionAccess $access Доступ читателя.
     * @param GameCharacterProjection $characters Листы персонажей.
     * @param GameNpcProjection $npcs Листы NPC.
     * @param GameBattleProjection $battles Бой и процессы.
     * @param GameWideStrikeProjection $wideStrikes Широкий удар.
     *
     * @return void
     */
    public function __construct(
        private readonly IGameMemberships $memberships,
        private readonly GameProjectionAccess $access,
        private readonly GameCharacterProjection $characters,
        private readonly GameNpcProjection $npcs,
        private readonly GameBattleProjection $battles,
        private readonly GameWideStrikeProjection $wideStrikes,
    ) {
    }

    /**
     * Краткие записи без листа.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     *
     * @return array<string, mixed> Персонажи и NPC.
     *
     * @throws GameNotFoundException Если карточка скрыта или персонажа нет.
     * @throws GameInvalidException Если строка битая.
     */
    public function roster(int $gameId, int $actorUserId, bool $viewAll): array
    {
        $game = $this->access->open($gameId, $actorUserId, $viewAll);

        return [
            'characters' => $this->characterBriefs($gameId),
            'npcs' => $this->npcs->briefs($game, $actorUserId),
        ];
    }

    /**
     * Полный лист названных ключей.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     * @param array<mixed> $keys Список {type, id}.
     *
     * @return array<string, mixed> Листы и пропуски.
     *
     * @throws ActionException INVALID_PARAMS.
     * @throws GameNotFoundException Если карточка скрыта или персонажа нет.
     * @throws GameInvalidException Если ключ повторён или строка битая.
     */
    public function sheets(int $gameId, int $actorUserId, bool $viewAll, array $keys): array
    {
        $game = $this->access->open($gameId, $actorUserId, $viewAll);

        return $this->collect($game, $actorUserId, GameProjectionKeys::read($keys));
    }

    /**
     * Краткие записи и листы состава одного боя.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $battleId Бой.
     * @param bool $editAll Ключ game.edit_all.
     *
     * @return array<string, mixed> Состав и листы.
     *
     * @throws GameNotFoundException Если карточки, сессии или боя нет.
     * @throws GameInvalidException Если строка битая.
     */
    public function battleSheets(
        int $gameId,
        int $actorUserId,
        bool $viewAll,
        int $battleId,
        bool $editAll = false,
    ): array {
        $game = $this->access->open($gameId, $actorUserId, $viewAll);
        $this->battles->require($gameId, $battleId);
        $collected = $this->collect($game, $actorUserId, $this->battles->participants($battleId));

        return ['battleId' => $battleId]
            + $this->battleBriefs($gameId, $collected)
            + $collected
            + $this->battles->processes($game, $battleId)
            + ['openWideStrike' => $this->wideStrikes->of($game, $actorUserId, $editAll, $battleId)];
    }

    /**
     * Листы ключей. Скрытый ключ в missing.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param list<array{type: string, id: int}> $keys Ключи.
     *
     * @return array{sheets: list<array<string, mixed>>, missing: list<array{type: string, id: int}>} Ответ.
     *
     * @throws GameNotFoundException Если actual нет.
     * @throws GameInvalidException Если строка битая.
     */
    private function collect(GameRecord $game, int $actorUserId, array $keys): array
    {
        $rows = $this->liveRows($game->getId());
        $npcs = $this->npcs->index($game->getId());
        $sheets = [];
        $missing = [];
        foreach ($keys as $key) {
            $sheet = $this->sheetOf($game, $actorUserId, $rows, $npcs, $key);
            if ($sheet === null) {
                $missing[] = $key;
                continue;
            }

            $sheets[] = $sheet;
        }

        return ['sheets' => $sheets, 'missing' => $missing];
    }

    /**
     * Краткие записи тех, чей лист вошёл в ответ боя.
     *
     * @param int $gameId Игра.
     * @param array{sheets: list<array<string, mixed>>, missing: list<array{type: string, id: int}>} $collected Листы.
     *
     * @return array{characters: list<array<string, mixed>>, npcs: list<array<string, mixed>>} Состав.
     *
     * @throws GameNotFoundException Если actual нет.
     */
    private function battleBriefs(int $gameId, array $collected): array
    {
        $characters = [];
        $npcs = [];
        $rows = $this->liveRows($gameId);
        foreach ($collected['sheets'] as $sheet) {
            $this->pushBrief($rows, $sheet, $characters, $npcs);
        }

        return ['characters' => $characters, 'npcs' => $npcs];
    }

    /**
     * Кладёт краткую запись по виду листа.
     *
     * @param array<int, GameCharacterRecord> $rows Живые строки.
     * @param array<string, mixed> $sheet Лист.
     * @param list<array<string, mixed>> $characters Персонажи.
     * @param list<array<string, mixed>> $npcs NPC.
     *
     * @return void
     *
     * @throws GameNotFoundException Если actual нет.
     */
    private function pushBrief(array $rows, array $sheet, array &$characters, array &$npcs): void
    {
        $id = is_int($sheet['id'] ?? null) ? $sheet['id'] : 0;
        if ($sheet['type'] === 'npc') {
            $npcs[] = [
                'type' => 'npc',
                'id' => $id,
                'name' => is_string($sheet['name'] ?? null) ? $sheet['name'] : '',
                'actualVersion' => is_int($sheet['actualVersion'] ?? null) ? $sheet['actualVersion'] : 0,
            ];

            return;
        }

        $row = $rows[$id] ?? null;
        if ($row instanceof GameCharacterRecord) {
            $characters[] = $this->characters->brief($row);
        }
    }

    /**
     * Один лист или пропуск.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param array<int, GameCharacterRecord> $rows Живые строки.
     * @param array<int, GameNpcRecord> $npcs Строки NPC.
     * @param array{type: string, id: int} $key Ключ.
     *
     * @return array<string, mixed>|null Лист.
     *
     * @throws GameNotFoundException Если actual нет.
     * @throws GameInvalidException Если строка битая.
     */
    private function sheetOf(GameRecord $game, int $actorUserId, array $rows, array $npcs, array $key): ?array
    {
        if ($key['type'] === 'npc') {
            return $this->npcs->sheet($game, $actorUserId, $npcs, $key['id']);
        }

        $row = $rows[$key['id']] ?? null;
        if (!$row instanceof GameCharacterRecord) {
            return null;
        }

        return $this->characters->sheet($game, $row, $actorUserId);
    }

    /**
     * Живые строки по characterId.
     *
     * @param int $gameId Игра.
     *
     * @return array<int, GameCharacterRecord> Строки.
     *
     * @throws GameNotFoundException Если игры нет.
     * @throws GameInvalidException Если строка битая.
     */
    private function liveRows(int $gameId): array
    {
        $rows = [];
        foreach ($this->memberships->getListByGame($gameId, null) as $row) {
            if ($row->getStatus() !== 'left') {
                $rows[$row->getCharacterId()] = $row;
            }
        }

        return $rows;
    }

    /**
     * Живые персонажи без документа.
     *
     * @param int $gameId Игра.
     *
     * @return list<array<string, mixed>> Записи.
     *
     * @throws GameNotFoundException Если actual нет.
     * @throws GameInvalidException Если строка битая.
     */
    private function characterBriefs(int $gameId): array
    {
        $briefs = [];
        foreach ($this->liveRows($gameId) as $row) {
            $briefs[] = $this->characters->brief($row);
        }

        return $briefs;
    }
}
