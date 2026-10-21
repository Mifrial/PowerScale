<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Value\JsonObject;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Game\Dto\GameCharacterRecord;
use Mifrial\Roleplay\Game\Dto\GameNpcRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGameMemberships;
use Mifrial\Roleplay\Game\Interface\Service\IGameProjections;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;
use Mifrial\Roleplay\Game\Repository\GameWideStrikeRepository;
use Mifrial\Roleplay\Game\Repository\GameWideStrikeTargetRepository;

/**
 * Roster и лист по ключам. Запись сессии и боя не делает.
 */
final class GameProjections implements IGameProjections
{
    private const KEY_LIMIT = 32;

    private readonly GameNpcRepository $npcs;

    private readonly GameBattleRepository $battles;

    private readonly GameSessionRepository $sessions;

    private readonly GameNpcVisibility $npcVisibility;

    private readonly GameWideStrikeRepository $wideStrikes;

    private readonly GameWideStrikeTargetRepository $wideTargets;

    private readonly GameWideStrikeAccess $wideAccess;

    /**
     * Создаёт чтение.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     * @param IGames $games Игра.
     * @param GameCardAccess $cardAccess Карточка.
     * @param ICharacters $characters Actual.
     * @param IGameMemberships $memberships Строки персонажа.
     * @param GameCharacterProjectionMask $characterMask Секции строки.
     *
     * @return void
     */
    public function __construct(
        ISmartTableGateway $smartTableGateway,
        private readonly IGames $games,
        private readonly GameCardAccess $cardAccess,
        private readonly ICharacters $characters,
        private readonly IGameMemberships $memberships,
        private readonly GameCharacterProjectionMask $characterMask,
        private readonly ICharacterRuleSlices $ruleSlices,
        private readonly ICharacterSheets $sheets,
    ) {
        $this->npcs = new GameNpcRepository($smartTableGateway);
        $this->battles = new GameBattleRepository($smartTableGateway);
        $this->sessions = new GameSessionRepository($smartTableGateway);
        $this->npcVisibility = new GameNpcVisibility();
        $this->wideStrikes = new GameWideStrikeRepository($smartTableGateway);
        $this->wideTargets = new GameWideStrikeTargetRepository($smartTableGateway);
        $this->wideAccess = new GameWideStrikeAccess(
            new GameMemberRepository($smartTableGateway),
            new GameCharacterRepository($smartTableGateway),
        );
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
        $game = $this->open($gameId, $actorUserId, $viewAll);

        return [
            'characters' => $this->characterBriefs($gameId),
            'npcs' => $this->npcBriefs($game, $actorUserId),
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
        $game = $this->open($gameId, $actorUserId, $viewAll);

        return $this->collect($game, $actorUserId, $this->readKeys($keys));
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
        $game = $this->open($gameId, $actorUserId, $viewAll);
        $this->requireBattle($gameId, $battleId);
        $collected = $this->collect($game, $actorUserId, $this->battles->findParticipants($battleId));

        return ['battleId' => $battleId]
            + $this->battleBriefs($gameId, $collected)
            + $collected
            + ['openWideStrike' => $this->openWideStrike($game, $actorUserId, $viewAll, $editAll, $battleId)];
    }

    /**
     * Открытый широкий удар боя для текущего читателя.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     * @param bool $editAll Ключ game.edit_all.
     * @param int $battleId Бой.
     *
     * @return array<string, mixed>|null JSON или null.
     */
    private function openWideStrike(
        GameRecord $game,
        int $actorUserId,
        bool $viewAll,
        bool $editAll,
        int $battleId,
    ): ?array {
        $row = $this->wideStrikes->findOpen($battleId);
        $strikeId = is_array($row) ? ($row['id'] ?? null) : null;
        $attackerKind = is_array($row) ? ($row['attacker_kind'] ?? null) : null;
        $attackerId = is_array($row) ? ($row['attacker_id'] ?? null) : null;
        if (!is_int($strikeId) || !is_string($attackerKind) || !is_int($attackerId)) {
            return null;
        }

        $writer = $this->wideAccess->isWriter($game, $actorUserId, $editAll);
        $npcs = $this->npcIndex($game->getId());
        if ($attackerKind === 'npc') {
            $attacker = $npcs[$attackerId] ?? null;
            if (!$attacker instanceof GameNpcRecord || !$this->npcAdmitted($game, $attacker, $actorUserId)) {
                return null;
            }
        }

        $targets = [];
        foreach ($this->wideTargets->getList($strikeId) as $targetRow) {
            $kind = $targetRow['defender_kind'] ?? null;
            $targetId = $targetRow['defender_id'] ?? null;
            if (!is_string($kind) || !is_int($targetId)) {
                continue;
            }

            if ($kind === 'npc') {
                $npc = $npcs[$targetId] ?? null;
                if (!$npc instanceof GameNpcRecord || !$this->npcAdmitted($game, $npc, $actorUserId)) {
                    continue;
                }
            } elseif ($kind !== 'character') {
                continue;
            }

            $reaction = $targetRow['reaction'] ?? null;
            $answered = is_string($reaction);
            $target = [
                'type' => $kind,
                'id' => $targetId,
                'answered' => $answered,
            ];
            $canRead = $writer || $this->wideAccess->canAnswer(
                $game,
                $actorUserId,
                $editAll,
                ['type' => $kind, 'id' => $targetId],
            );
            if ($answered && $canRead) {
                $target['reaction'] = $reaction;
                $target['blockItemInventoryId'] = is_int($targetRow['block_item_inventory_id'] ?? null)
                    ? $targetRow['block_item_inventory_id']
                    : null;
                $target['blockItemProfileIndex'] = is_int($targetRow['block_item_profile_index'] ?? null)
                    ? $targetRow['block_item_profile_index']
                    : null;
                $target['blockItemRuleCode'] = is_string($targetRow['block_item_rule_code'] ?? null)
                    ? $targetRow['block_item_rule_code']
                    : null;
            }

            $targets[] = $target;
        }

        return [
            'id' => $strikeId,
            'attacker' => ['type' => $attackerKind, 'id' => $attackerId],
            'actionRuleCode' => is_string($row['action_rule_code'] ?? null) ? $row['action_rule_code'] : '',
            'itemInventoryId' => is_int($row['item_inventory_id'] ?? null) ? $row['item_inventory_id'] : 0,
            'itemRuleCode' => is_string($row['item_rule_code'] ?? null) ? $row['item_rule_code'] : '',
            'profileType' => is_string($row['profile_type'] ?? null) ? $row['profile_type'] : '',
            'profileIndex' => is_int($row['profile_index'] ?? null) ? $row['profile_index'] : 0,
            'targets' => $targets,
        ];
    }

    /**
     * Карточка видна.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     *
     * @return GameRecord Игра.
     *
     * @throws GameNotFoundException Если игры нет или карточка скрыта.
     */
    private function open(int $gameId, int $actorUserId, bool $viewAll): GameRecord
    {
        $game = $this->games->get($gameId);
        if (!$this->cardAccess->isVisible($game, $actorUserId, $viewAll)) {
            throw new GameNotFoundException();
        }

        return $game;
    }

    /**
     * Бой текущей сессии.
     *
     * @param int $gameId Игра.
     * @param int $battleId Бой.
     *
     * @return void
     *
     * @throws GameNotFoundException Если сессии нет или бой чужой.
     */
    private function requireBattle(int $gameId, int $battleId): void
    {
        $sessionId = $this->sessions->findSessionId($gameId);
        $battle = $this->battles->find($battleId);
        if ($sessionId === null || $battle === null || $battle->getSessionId() !== $sessionId) {
            throw new GameNotFoundException();
        }
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
            $actual = $this->actual($row->getCharacterId());
            $briefs[] = $this->characterBrief($row, $actual);
        }

        return $briefs;
    }

    /**
     * NPC, которых scope пускает.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     *
     * @return list<array<string, mixed>> Записи.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function npcBriefs(GameRecord $game, int $actorUserId): array
    {
        $briefs = [];
        foreach ($this->npcIndex($game->getId()) as $record) {
            if ($this->npcAdmitted($game, $record, $actorUserId)) {
                $briefs[] = $this->npcBrief($record);
            }
        }

        return $briefs;
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
        $npcs = $this->npcIndex($game->getId());
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
            $characters[] = $this->characterBrief($row, $this->actual($id));
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
            return $this->npcSheet($game, $actorUserId, $npcs, $key['id']);
        }

        $row = $rows[$key['id']] ?? null;
        if (!$row instanceof GameCharacterRecord) {
            return null;
        }

        return $this->characterSheet($game, $actorUserId, $row);
    }

    /**
     * Лист NPC после маски.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param array<int, GameNpcRecord> $npcs Строки NPC.
     * @param int $npcId NPC.
     *
     * @return array<string, mixed>|null Лист.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function npcSheet(GameRecord $game, int $actorUserId, array $npcs, int $npcId): ?array
    {
        $record = $npcs[$npcId] ?? null;
        if (!$record instanceof GameNpcRecord || !$this->npcAdmitted($game, $record, $actorUserId)) {
            return null;
        }

        $full = $game->getOwnerId() === $actorUserId || $this->roleOf($game->getId(), $actorUserId) === 'gm';
        $version = $this->npcVisibility->mask($record->getVersion(), $record->getVisibility(), $full);

        return $this->npcDocument($record, $version);
    }

    /**
     * Лист персонажа после маски.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param GameCharacterRecord $row Строка.
     *
     * @return array<string, mixed> Лист.
     *
     * @throws GameNotFoundException Если actual нет.
     */
    private function characterSheet(GameRecord $game, int $actorUserId, GameCharacterRecord $row): array
    {
        $actual = $this->actual($row->getCharacterId());
        $full = $this->characterOpen($game, $row, $actorUserId);
        $sheet = $this->sheets->compute(
            $this->ruleSlices->get($actual->getSpaceId(), $actual->getRulesRevision()),
            $actual->getChoices(),
            $actual->getSheet(),
        );
        $masked = $this->characterMask->apply(
            $actual->getChoices(),
            $sheet,
            $row->getSectionVisibility(),
            $full,
        );

        return [
            'type' => 'character',
            'id' => $row->getCharacterId(),
            'name' => $actual->getName(),
            'actualVersion' => $actual->getActualVersion(),
            'spaceId' => $actual->getSpaceId(),
            'rulesRevision' => $actual->getRulesRevision(),
            'choices' => $masked['choices'],
            'sheet' => $masked['sheet'],
        ];
    }

    /**
     * Разбор ключей. Пустой и длинный список отвергаются.
     *
     * @param array<mixed> $keys JSON.
     *
     * @return list<array{type: string, id: int}> Ключи.
     *
     * @throws ActionException INVALID_PARAMS.
     * @throws GameInvalidException Если повтор.
     */
    private function readKeys(array $keys): array
    {
        if (!array_is_list($keys) || $keys === [] || count($keys) > self::KEY_LIMIT) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: keys');
        }

        $parsed = [];
        $seen = [];
        foreach ($keys as $key) {
            $parsed[] = $this->oneKey($key, $seen);
        }

        return $parsed;
    }

    /**
     * Один ключ.
     *
     * @param mixed $key Элемент.
     * @param array<string, true> $seen Уже принятые.
     *
     * @return array{type: string, id: int} Ключ.
     *
     * @throws ActionException INVALID_PARAMS.
     * @throws GameInvalidException Если повтор.
     */
    private function oneKey(mixed $key, array &$seen): array
    {
        if ($key instanceof JsonObject) {
            $key = $key->getMembers();
        }

        $names = is_array($key) ? array_keys($key) : [];
        sort($names);
        if ($names !== ['id', 'type']) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: keys');
        }

        $type = $key['type'];
        $id = $key['id'];
        if (!is_string($type) || !in_array($type, ['character', 'npc'], true) || !is_int($id)) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: keys');
        }

        $mark = $type . ':' . $id;
        if (isset($seen[$mark])) {
            throw new GameInvalidException('Projection key is repeated');
        }

        $seen[$mark] = true;

        return ['type' => $type, 'id' => $id];
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
     * Actual или отказ всего вызова.
     *
     * @param int $characterId Персонаж.
     *
     * @return CharacterRecord Строка.
     *
     * @throws GameNotFoundException Если строки нет.
     */
    private function actual(int $characterId): CharacterRecord
    {
        try {
            return $this->characters->get($characterId);
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game character was not found', $exception);
        }
    }

    /**
     * NPC игры по id.
     *
     * @param int $gameId Игра.
     *
     * @return array<int, GameNpcRecord> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function npcIndex(int $gameId): array
    {
        $records = [];
        foreach ($this->npcs->getListByGame($gameId) as $record) {
            $records[$record->getId()] = $record;
        }

        return $records;
    }

    /**
     * Scope пускает к NPC.
     *
     * @param GameRecord $game Игра.
     * @param GameNpcRecord $record Строка.
     * @param int $actorUserId Актор.
     *
     * @return bool true, если NPC виден.
     */
    private function npcAdmitted(GameRecord $game, GameNpcRecord $record, int $actorUserId): bool
    {
        if ($game->getOwnerId() === $actorUserId || $this->roleOf($game->getId(), $actorUserId) === 'gm') {
            return true;
        }

        return $this->scopeAllows($record->getVisibility(), $game->getId(), $actorUserId);
    }

    /**
     * Scope all или users. gm сюда не входит.
     *
     * @param array<string, mixed> $visibility Объект.
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     *
     * @return bool true, если секции можно показать.
     */
    private function scopeAllows(array $visibility, int $gameId, int $actorUserId): bool
    {
        $scope = $visibility['scope'] ?? null;
        if ($scope === 'all') {
            return $this->roleOf($gameId, $actorUserId) !== null;
        }

        if ($scope !== 'users' || !is_array($visibility['userIds'] ?? null)) {
            return false;
        }

        return in_array($actorUserId, $visibility['userIds'], true);
    }

    /**
     * Полный лист персонажа.
     *
     * @param GameRecord $game Игра.
     * @param GameCharacterRecord $row Строка.
     * @param int $actorUserId Актор.
     *
     * @return bool true, если секции не режутся.
     */
    private function characterOpen(GameRecord $game, GameCharacterRecord $row, int $actorUserId): bool
    {
        return $row->getCharacterOwnerId() === $actorUserId
            || $game->getOwnerId() === $actorUserId
            || $this->roleOf($game->getId(), $actorUserId) === 'gm';
    }

    /**
     * Роль участника или null.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     *
     * @return string|null gm, player или null.
     */
    private function roleOf(int $gameId, int $actorUserId): ?string
    {
        try {
            return $this->games->getMember($gameId, $actorUserId)->getRole();
        } catch (GameNotFoundException) {
            return null;
        }
    }

    /**
     * Краткая запись персонажа.
     *
     * @param GameCharacterRecord $row Строка.
     * @param CharacterRecord $actual Actual.
     *
     * @return array<string, mixed> JSON.
     */
    private function characterBrief(GameCharacterRecord $row, CharacterRecord $actual): array
    {
        return [
            'type' => 'character',
            'id' => $row->getCharacterId(),
            'name' => $actual->getName(),
            'status' => $row->getStatus(),
            'actualVersion' => $actual->getActualVersion(),
        ];
    }

    /**
     * Краткая запись NPC.
     *
     * @param GameNpcRecord $record Строка.
     *
     * @return array<string, mixed> JSON.
     */
    private function npcBrief(GameNpcRecord $record): array
    {
        return [
            'type' => 'npc',
            'id' => $record->getId(),
            'name' => $record->getName(),
            'actualVersion' => $record->getActualVersion(),
        ];
    }

    /**
     * Лист NPC с миром на корне version.
     *
     * @param GameNpcRecord $record Строка.
     * @param array<string, mixed> $version После маски.
     *
     * @return array<string, mixed> JSON.
     */
    private function npcDocument(GameNpcRecord $record, array $version): array
    {
        $choices = is_array($version['choices'] ?? null) ? $version['choices'] : [];
        $sheet = is_array($version['sheet'] ?? null) ? $version['sheet'] : [];
        $document = [
            'type' => 'npc',
            'id' => $record->getId(),
            'name' => $record->getName(),
            'actualVersion' => $record->getActualVersion(),
            'spaceId' => $version['spaceId'] ?? null,
            'rulesRevision' => $version['rulesRevision'] ?? null,
            'choices' => $choices,
            'sheet' => $sheet,
        ];
        if (is_string($version['spaceCode'] ?? null)) {
            $document['spaceCode'] = $version['spaceCode'];
        }

        return $document;
    }
}
