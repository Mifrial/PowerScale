<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Авторитетные choices целей и реакции.
 */
final class GameStrikeTargetChoices
{
    /**
     * Создаёт чтение choices.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeSheets $sheets Листы.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheets $sheets,
    ) {
    }

    /**
     * Авторитетные choices цели.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     *
     * @return array<string, mixed> Choices.
     */
    public function targetChoices(GameRecord $game, array $target): array
    {
        if ($target['type'] === 'character') {
            return $this->rules->characterSnapshot($target['id'])['choices'];
        }

        return $this->defenderChoices($game->getId(), $target['id']);
    }

    /**
     * Авторитетные choices атакующего.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     *
     * @return array<string, mixed> Choices.
     */
    public function attackerChoices(GameRecord $game, array $open): array
    {
        $kind = $open['attacker_kind'] ?? null;
        $id = $open['attacker_id'] ?? null;
        if (!is_string($kind) || !is_int($id)) {
            throw new GameInvalidException('Game wide strike attacker is invalid');
        }

        if ($kind === 'character') {
            return $this->rules->characterSnapshot($id)['choices'];
        }

        $snapshot = $this->sheets->getNpcSnapshot($game->getId(), $id);
        $choices = $snapshot['sheet']['choices'] ?? null;
        if (!is_array($choices)) {
            throw new GameInvalidException('Game wide strike npc choices are invalid');
        }

        return $choices;
    }

    /**
     * Документ choices NPC этой игры.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return array<string, mixed> Документ.
     */
    private function defenderChoices(int $gameId, int $npcId): array
    {
        $snapshot = $this->sheets->getNpcSnapshot($gameId, $npcId);
        $choices = $snapshot['sheet']['choices'] ?? null;
        if (!is_array($choices)) {
            throw new GameInvalidException('Game wide strike npc choices are missing');
        }

        return $choices;
    }

    /**
     * Реакция и предмет блока этой цели.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     *
     * @return void
     */
    public function assertReaction(GameRecord $game, array $target, array $choice): void
    {
        if (!in_array($choice['reaction'], ['ignore', 'dodge', 'block'], true)) {
            throw new GameInvalidException('Game wide strike reaction is invalid');
        }

        if (
            $choice['reaction'] === 'block'
            && (!is_int($choice['blockItemInventoryId'])
                || !is_int($choice['blockItemProfileIndex']))
        ) {
            throw new GameInvalidException('Game wide strike block item is invalid');
        }

        if ($choice['reaction'] === 'block') {
            $this->rules->assertBlockSelection(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $this->targetChoices($game, $target),
                $choice['blockItemInventoryId'],
                $choice['blockItemProfileIndex'],
                $choice['blockItemRuleCode'],
            );
        }
    }
}
