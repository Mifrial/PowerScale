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
 * Проверка версий листов и проекция конфликтов записи удара.
 */
final class GameStrikeSheetConflicts
{
    /**
     * Создаёт проверку конфликтов.
     *
     * @param GameStrikeSheets $sheets Снимки листов.
     * @param GameCharacterRepository $characters Персонажи игры.
     * @param GameMemberRepository $members Участники.
     * @param GameNpcRepository $npcs NPC.
     * @param ConflictSheetProjection $projection Проекция конфликтов.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeSheets $sheets,
        private readonly GameCharacterRepository $characters,
        private readonly GameMemberRepository $members,
        private readonly GameNpcRepository $npcs,
        private readonly ConflictSheetProjection $projection,
    ) {
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
            ? $this->sheets->getCharacterSnapshot($id)
            : $this->sheets->getNpcSnapshot($game->getId(), $id);
        if ($snapshot['actualVersion'] === $expectedSheetVersion) {
            return;
        }

        $sheet = $this->project(
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
            ? $this->sheets->getCharacterSnapshot($target['id'])
            : $this->sheets->getNpcSnapshot($game->getId(), $target['id']);
        $sheet = $this->project($game, $target, $snapshot['sheet'], $actorUserId, $viewAll);
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

        return $this->project($game, $target, $current, $actorUserId, $viewAll);
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
    private function project(
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

        return $target['type'] === 'character'
            ? $this->character($game, $target['id'], $choices, $sheet, $actorUserId, $viewAll)
            : $this->npc($game, $target['id'], $choices, $sheet, $actorUserId, $viewAll);
    }

    /**
     * Проекция листа персонажа игры.
     *
     * @param GameRecord $game Игра.
     * @param int $characterId Персонаж.
     * @param array<string, mixed> $choices Выборы.
     * @param array<string, mixed> $sheet Лист.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array{choices: array<string, mixed>, sheet: array<string, mixed>} Проекция.
     *
     * @throws GameNotFoundException Если членства нет.
     */
    private function character(
        GameRecord $game,
        int $characterId,
        array $choices,
        array $sheet,
        int $actorUserId,
        bool $viewAll,
    ): array {
        $membership = $this->characters->getByPair($game->getId(), $characterId);
        $full = $viewAll
            || $game->getOwnerId() === $actorUserId
            || $membership->getCharacterOwnerId() === $actorUserId
            || $this->isGm($game->getId(), $actorUserId);

        return $this->projection->projectGameCharacter(
            $choices,
            $sheet,
            $membership->getSectionVisibility(),
            $full,
        );
    }

    /**
     * Проекция листа NPC этой игры.
     *
     * @param GameRecord $game Игра.
     * @param int $npcId NPC.
     * @param array<string, mixed> $choices Выборы.
     * @param array<string, mixed> $sheet Лист.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array{choices: array<string, mixed>, sheet: array<string, mixed>} Проекция.
     *
     * @throws GameNotFoundException Если NPC чужой.
     */
    private function npc(
        GameRecord $game,
        int $npcId,
        array $choices,
        array $sheet,
        int $actorUserId,
        bool $viewAll,
    ): array {
        $npc = $this->npcs->getById($npcId);
        if ($npc->getGameId() !== $game->getId()) {
            throw new GameNotFoundException('Game strike npc was not found');
        }

        $full = $viewAll
            || $game->getOwnerId() === $actorUserId
            || $this->isGm($game->getId(), $actorUserId);
        $version = $this->projection->projectNpc(
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
