<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;

/**
 * Снимки листов удара и проверка их версий.
 */
final class GameStrikeSheets
{
    public readonly GameStrikeSheetConflicts $conflicts;

    /**
     * Создаёт чтение листов.
     *
     * @param GameStrikeRules $rules Карточки удара.
     * @param GameNpcRepository $npcs NPC.
     * @param GameCharacterRepository $characters Персонажи игры.
     * @param GameMemberRepository $members Участники.
     * @param ConflictSheetProjection $conflictProjection Проекция конфликтов.
     * @param GameActiveEffectStates $states Состояния цели из наложенных эффектов.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameNpcRepository $npcs,
        private readonly GameCharacterRepository $characters,
        private readonly GameMemberRepository $members,
        private readonly ConflictSheetProjection $conflictProjection,
        private readonly GameActiveEffectStates $states,
    ) {
        $this->conflicts = new GameStrikeSheetConflicts(
            $this,
            $this->characters,
            $this->members,
            $this->npcs,
            $this->conflictProjection,
        );
    }

    /**
     * Авторитетный лист защитника одиночного удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Строка удара.
     *
     * @return array<string, mixed> Лист.
     *
     * @throws GameInvalidException Если строка битая.
     * @throws GameNotFoundException Если цели нет.
     */
    public function getDefenderSheet(GameRecord $game, array $open): array
    {
        $kind = $open['defender_kind'] ?? null;
        $id = $open['defender_id'] ?? null;
        if (!is_string($kind) || !is_int($id)) {
            throw new GameInvalidException('Game strike defender is invalid');
        }

        if ($kind === 'character') {
            $snapshot = $this->rules->data->characterSnapshot($id);

            return $this->withRuntimeStates(
                $game->getId(),
                $game->getSpaceId(),
                $game->getRulesRevision(),
                'character',
                $id,
                $snapshot['sheet'],
                $snapshot['choices'],
            );
        }

        $snapshot = $this->getNpcSnapshot($game->getId(), $id);
        $document = $snapshot['sheet'];
        $sheet = is_array($document['sheet'] ?? null) ? $document['sheet'] : $document;

        return $this->getNpcSheet($game->getId(), $id, $sheet);
    }

    /**
     * Авторитетный лист цели широкого удара.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     *
     * @return array<string, mixed> Лист.
     *
     * @throws GameInvalidException Если документ битый.
     * @throws GameNotFoundException Если цели нет.
     */
    public function getTargetSheet(GameRecord $game, array $target): array
    {
        if ($target['type'] === 'character') {
            $snapshot = $this->rules->data->characterSnapshot($target['id']);

            return $this->withRuntimeStates(
                $game->getId(),
                $game->getSpaceId(),
                $game->getRulesRevision(),
                'character',
                $target['id'],
                $snapshot['sheet'],
                $snapshot['choices'],
            );
        }

        return $this->getNpcSheet($game->getId(), $target['id']);
    }

    /**
     * Лист NPC этой игры с состояниями цели.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     * @param array<string, mixed>|null $sheet Лист из снимка или null.
     *
     * @return array<string, mixed> Лист.
     *
     * @throws GameInvalidException Если строка чужая или снимка нет.
     * @throws GameNotFoundException Если строки нет.
     */
    public function getNpcSheet(int $gameId, int $npcId, ?array $sheet = null): array
    {
        $npc = $this->npcs->getById($npcId);
        $version = $npc->getVersion();
        $sheet ??= $version['sheet'] ?? null;
        if ($npc->getGameId() !== $gameId || !is_array($sheet)) {
            throw new GameInvalidException('Game strike npc sheet is missing');
        }

        $spaceId = $version['spaceId'] ?? null;
        $revision = $version['rulesRevision'] ?? null;
        $choices = $version['choices'] ?? null;
        if (!is_int($spaceId) || !is_int($revision) || !is_array($choices)) {
            return $sheet;
        }

        return $this->withRuntimeStates($gameId, $spaceId, $revision, 'npc', $npcId, $sheet, $choices);
    }

    /**
     * Строки состояний участника из наложенных эффектов.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид участника.
     * @param int $id Идентификатор.
     *
     * @return array<int, array<string, mixed>> Строки состояний.
     */
    public function runtimeStates(GameRecord $game, string $kind, int $id): array
    {
        return $this->states->rowsOf($game, $kind, $id);
    }

    /**
     * Лист с наложенными состояниями цели.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид цели.
     * @param int $id Идентификатор цели.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы.
     *
     * @return array<string, mixed> Лист с состояниями.
     */
    public function project(GameRecord $game, string $kind, int $id, array $sheet, array $choices): array
    {
        return $this->withRuntimeStates(
            $game->getId(),
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $kind,
            $id,
            $sheet,
            $choices,
        );
    }

    /**
     * Добавляет состояния цели из наложенных эффектов.
     *
     * @param int $gameId Игра.
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $kind Вид цели.
     * @param int $id Идентификатор цели.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы.
     *
     * @return array<string, mixed> Лист.
     */
    private function withRuntimeStates(
        int $gameId,
        int $spaceId,
        int $rulesRevision,
        string $kind,
        int $id,
        array $sheet,
        array $choices,
    ): array {
        $rows = $this->states->rows($gameId, $spaceId, $rulesRevision, $kind, $id);
        if ($rows === []) {
            return $sheet;
        }

        return $this->rules->participants->applyRuntimeStates(
            $this->rules->data->ruleSlice($spaceId, $rulesRevision),
            $sheet,
            $choices,
            $rows,
        );
    }

    /**
     * Выборы NPC этой игры.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return array<string, mixed> Выборы.
     *
     * @throws GameInvalidException Если снимка нет.
     * @throws GameNotFoundException Если строки нет.
     */
    public function getNpcChoices(int $gameId, int $npcId): array
    {
        $npc = $this->npcs->getById($npcId);
        $choices = $npc->getGameId() === $gameId ? ($npc->getVersion()['choices'] ?? null) : null;
        if (!is_array($choices)) {
            throw new GameInvalidException('Game strike npc choices are missing');
        }

        return $choices;
    }

    /**
     * Снимок листа персонажа.
     *
     * @param int $characterId Персонаж.
     *
     * @return array<string, mixed> Снимок.
     *
     * @throws GameNotFoundException Если персонажа нет.
     */
    public function getCharacterSnapshot(int $characterId): array
    {
        $snapshot = $this->rules->data->characterSnapshot($characterId);

        return [
            'actualVersion' => $snapshot['actualVersion'],
            'sheet' => [
                'choices' => $snapshot['choices'],
                'sheet' => $snapshot['sheet'],
            ],
        ];
    }

    /**
     * Снимок листа NPC этой игры.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return array<string, mixed> Снимок.
     *
     * @throws GameNotFoundException Если NPC чужой или отсутствует.
     */
    public function getNpcSnapshot(int $gameId, int $npcId): array
    {
        $npc = $this->npcs->getById($npcId);
        if ($npc->getGameId() !== $gameId) {
            throw new GameNotFoundException('Game strike npc was not found');
        }

        $document = $npc->getVersion();
        $choices = $document['choices'] ?? [];
        $sheet = $document['sheet'] ?? [];

        return [
            'actualVersion' => $npc->getActualVersion(),
            'sheet' => [
                'choices' => is_array($choices) ? $choices : [],
                'sheet' => is_array($sheet) ? $sheet : [],
            ],
        ];
    }

    /**
     * Снимок листа участника без различия вида.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид участника: character или npc.
     * @param int $id Идентификатор.
     *
     * @return array<string, mixed> Снимок.
     *
     * @throws GameInvalidException Если вид чужой.
     * @throws GameNotFoundException Если строки нет.
     */
    public function snapshot(GameRecord $game, string $kind, int $id): array
    {
        if ($kind === 'character') {
            return $this->getCharacterSnapshot($id);
        }

        if ($kind !== 'npc') {
            throw new GameInvalidException('Game strike participant is invalid');
        }

        return $this->getNpcSnapshot($game->getId(), $id);
    }

    /**
     * Уровни способностей участника.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид участника: character или npc.
     * @param int $id Идентификатор.
     *
     * @return array<string, int> Уровни.
     *
     * @throws GameInvalidException Если вид чужой.
     * @throws GameNotFoundException Если строки нет.
     */
    public function abilityLevels(GameRecord $game, string $kind, int $id): array
    {
        $levels = $this->snapshot($game, $kind, $id)['sheet']['sheet']['abilityLevels'] ?? null;

        return is_array($levels) ? $levels : [];
    }

    /**
     * Версия листа NPC одиночного удара.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return int Версия.
     *
     * @throws GameNotFoundException Если строки нет или она чужая.
     */
    public function getNpcVersion(int $gameId, int $npcId): int
    {
        $npc = $this->npcs->getById($npcId);
        if ($npc->getGameId() !== $gameId) {
            throw new GameNotFoundException('Game strike npc was not found');
        }

        return $npc->getActualVersion();
    }
}
