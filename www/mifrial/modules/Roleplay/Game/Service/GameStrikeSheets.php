<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;

/**
 * Снимки листов, проверка версии и проекция конфликтов удара.
 */
final class GameStrikeSheets
{
    /**
     * Создаёт чтение листов.
     *
     * @param GameStrikeRules $rules Карточки удара.
     * @param GameNpcRepository $npcs NPC.
     * @param GameCharacterRepository $characters Персонажи игры.
     * @param GameMemberRepository $members Участники.
     * @param ConflictSheetProjection $conflictProjection Проекция конфликтов.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameNpcRepository $npcs,
        private readonly GameCharacterRepository $characters,
        private readonly GameMemberRepository $members,
        private readonly ConflictSheetProjection $conflictProjection,
    ) {
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
            return $this->rules->characterSnapshot($id)['sheet'];
        }

        $wrapped = $this->getNpcSnapshot($game->getId(), $id)['sheet'];
        $inner = is_array($wrapped) ? ($wrapped['sheet'] ?? null) : null;

        return is_array($inner) ? $inner : $wrapped;
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
            return $this->rules->characterSnapshot($target['id'])['sheet'];
        }

        return $this->getNpcSheet($game->getId(), $target['id']);
    }

    /**
     * Лист NPC этой игры.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return array<string, mixed> Лист.
     *
     * @throws GameInvalidException Если строка чужая или снимка нет.
     * @throws GameNotFoundException Если строки нет.
     */
    public function getNpcSheet(int $gameId, int $npcId): array
    {
        $npc = $this->npcs->getById($npcId);
        $sheet = $npc->getGameId() === $gameId ? ($npc->getVersion()['sheet'] ?? null) : null;
        if (!is_array($sheet)) {
            throw new GameInvalidException('Game strike npc sheet is missing');
        }

        return $sheet;
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
        $snapshot = $this->rules->characterSnapshot($characterId);

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

    /**
     * Версия листа NPC широкого удара.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return int Версия.
     *
     * @throws GameInvalidException Если строка чужая.
     * @throws GameNotFoundException Если строки нет.
     */
    public function getTargetNpcVersion(int $gameId, int $npcId): int
    {
        $npc = $this->npcs->getById($npcId);
        if ($npc->getGameId() !== $gameId) {
            throw new GameInvalidException('Game wide strike npc sheet is missing');
        }

        return $npc->getActualVersion();
    }

    /**
     * Версия листа защитника одиночного удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Строка удара.
     * @param int $expectedSheetVersion Ожидание.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return void
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameInvalidException Если строка битая.
     * @throws GameNotFoundException Если листа нет.
     */
    public function assertDefenderSheet(
        GameRecord $game,
        array $open,
        int $expectedSheetVersion,
        int $actorUserId,
        bool $viewAll,
    ): void {
        $kind = $open['defender_kind'] ?? null;
        $id = $open['defender_id'] ?? null;
        if (($kind !== 'character' && $kind !== 'npc') || !is_int($id)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        $snapshot = $kind === 'character'
            ? $this->getCharacterSnapshot($id)
            : $this->getNpcSnapshot($game->getId(), $id);
        if ($snapshot['actualVersion'] === $expectedSheetVersion) {
            return;
        }

        $sheet = $this->projectTargetSheet(
            $game,
            ['type' => $kind, 'id' => $id],
            $snapshot['sheet'],
            $actorUserId,
            $viewAll,
        );
        throw new GameBattleConflictException($snapshot['actualVersion'], currentSheet: $sheet);
    }

    /**
     * Версия листа цели широкого удара.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param int $expectedSheetVersion Ожидание.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return void
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameInvalidException Если документ битый.
     * @throws GameNotFoundException Если цели нет.
     */
    public function assertTargetSheet(
        GameRecord $game,
        array $target,
        int $expectedSheetVersion,
        int $actorUserId,
        bool $viewAll,
    ): void {
        $snapshot = $target['type'] === 'character'
            ? $this->getCharacterSnapshot($target['id'])
            : $this->getNpcSnapshot($game->getId(), $target['id']);
        $sheet = $this->projectTargetSheet($game, $target, $snapshot['sheet'], $actorUserId, $viewAll);
        if ($snapshot['actualVersion'] === $expectedSheetVersion) {
            return;
        }

        throw new GameBattleConflictException(
            $snapshot['actualVersion'],
            currentSheet: $sheet,
            target: ['type' => $target['type'], 'id' => $target['id']],
        );
    }

    /**
     * Проецирует race-конфликт записи цели.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param CharacterConflictException|GameEconomyConflictException $exception Исходный конфликт.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array{choices: array<string, mixed>, sheet: array<string, mixed>} Проекция.
     *
     * @throws GameInvalidException Если снимок листа битый.
     * @throws GameNotFoundException Если цель или membership отсутствуют.
     */
    public function projectWriteConflict(
        GameRecord $game,
        array $target,
        CharacterConflictException|GameEconomyConflictException $exception,
        int $actorUserId,
        bool $viewAll,
    ): array {
        $current = $exception->getCurrentSheet();
        if ($current === null) {
            throw new GameInvalidException('Game strike conflict sheet is missing');
        }

        return $this->projectTargetSheet($game, $target, $current, $actorUserId, $viewAll);
    }

    /**
     * Проецирует снимок листа цели.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $currentSheet Снимок.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array{choices: array<string, mixed>, sheet: array<string, mixed>} Проекция.
     *
     * @throws GameInvalidException Если снимок битый.
     * @throws GameNotFoundException Если цель или membership отсутствуют.
     */
    private function projectTargetSheet(
        GameRecord $game,
        array $target,
        array $currentSheet,
        int $actorUserId,
        bool $viewAll,
    ): array {
        $choices = $currentSheet['choices'] ?? null;
        $sheet = $currentSheet['sheet'] ?? null;
        if (!is_array($choices) || !is_array($sheet)) {
            throw new GameInvalidException('Game strike sheet is invalid');
        }

        if ($target['type'] === 'character') {
            $membership = $this->characters->getByPair($game->getId(), $target['id']);
            $full = $viewAll
                || $game->getOwnerId() === $actorUserId
                || $membership->getCharacterOwnerId() === $actorUserId
                || $this->isGm($game->getId(), $actorUserId);

            return $this->conflictProjection->projectGameCharacter(
                $choices,
                $sheet,
                $membership->getSectionVisibility(),
                $full,
            );
        }

        $npc = $this->npcs->getById($target['id']);
        if ($npc->getGameId() !== $game->getId()) {
            throw new GameNotFoundException('Game strike npc was not found');
        }

        $full = $viewAll
            || $game->getOwnerId() === $actorUserId
            || $this->isGm($game->getId(), $actorUserId);
        $version = $this->conflictProjection->projectNpc(
            ['choices' => $choices, 'sheet' => $sheet],
            $npc->getVisibility(),
            $full,
        );

        return [
            'choices' => is_array($version['choices'] ?? null) ? $version['choices'] : [],
            'sheet' => is_array($version['sheet'] ?? null) ? $version['sheet'] : [],
        ];
    }

    /**
     * Роль gm.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     *
     * @return bool Да, если gm.
     */
    private function isGm(int $gameId, int $actorUserId): bool
    {
        try {
            return $this->members->getByPair($gameId, $actorUserId)->getRole() === 'gm';
        } catch (GameNotFoundException) {
            return false;
        }
    }
}
