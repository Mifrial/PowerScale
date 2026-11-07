<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Листы, реакции и отказы целей широкого удара.
 */
final class GameStrikeWideChecks
{
    /**
     * Создаёт проверки целей.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeCloseRows $rows Строки удара.
     * @param GameStrikeSheets $sheets Листы.
     * @param GameStrikeTargetChoices $choices Choices целей.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeCloseRows $rows,
        private readonly GameStrikeSheets $sheets,
        private readonly GameStrikeTargetChoices $choices,
    ) {
    }

    /**
     * Проверяет листы всех целей и отдаёт non-CAS отказы.
     *
     * @param GameRecord $game Игра.
     * @param int $battleId Бой.
     * @param list<array<string, mixed>> $rows Цели.
     * @param list<array<string, mixed>> $choices Защиты.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return list<string|null> Отказы.
     */
    public function refusals(
        GameRecord $game,
        int $battleId,
        array $rows,
        array $choices,
        int $actorUserId,
        bool $viewAll,
    ): array {
        $refusals = [];
        foreach ($rows as $index => $row) {
            $refusals[$index] = $this->assertSheet(
                $game,
                $battleId,
                $this->rows->rowTarget($row),
                $choices[$index],
                $actorUserId,
                $viewAll,
            );
        }

        return $refusals;
    }

    /**
     * Разрешает коды block из authoritative inventory целей.
     *
     * @param GameRecord $game Игра.
     * @param list<array<string, mixed>> $rows Цели.
     * @param list<array<string, mixed>> $choices Защиты.
     * @param list<string|null> $refusals Отказы.
     *
     * @return list<array<string, mixed>> Authoritative защиты.
     *
     * @throws GameInvalidException Если строка блока битая.
     */
    public function resolve(GameRecord $game, array $rows, array $choices, array $refusals): array
    {
        foreach ($rows as $index => $row) {
            if (($refusals[$index] ?? null) !== null || ($choices[$index]['reaction'] ?? null) !== 'block') {
                continue;
            }

            $target = $this->rows->rowTarget($row);
            $inventoryId = $choices[$index]['blockItemInventoryId'] ?? null;
            if (!is_int($inventoryId)) {
                throw new GameInvalidException('Game wide strike block item is invalid');
            }

            $choices[$index]['blockItemRuleCode'] = $this->rules->equipment->getBlockItemRuleCode(
                $this->choices->targetChoices($game, $target),
                $inventoryId,
            );
        }

        return $choices;
    }

    /**
     * Проверяет лист одной цели.
     *
     * @param GameRecord $game Игра.
     * @param int $battleId Бой.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return string|null Non-CAS код отказа.
     *
     * @throws GameInvalidException Если лист цели битый.
     */
    public function assertSheet(
        GameRecord $game,
        int $battleId,
        array $target,
        array $choice,
        int $actorUserId,
        bool $viewAll,
    ): ?string {
        $code = $this->refusal($game, $battleId, $target, $choice);
        if ($code !== null) {
            return $code;
        }

        $this->sheets->assertTargetSheet(
            $game,
            $target,
            $choice['expectedSheetVersion'],
            $actorUserId,
            $viewAll,
        );

        return null;
    }

    /**
     * Код отказа или null.
     *
     * @param GameRecord $game Игра.
     * @param int $battleId Бой.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     *
     * @return string|null Код.
     */
    private function refusal(GameRecord $game, int $battleId, array $target, array $choice): ?string
    {
        $code = null;
        try {
            $this->rows->assertDefender($battleId, $target);
            $this->choices->assertReaction($game, $target, $choice);
        } catch (GameNotFoundException) {
            $code = 'GAME_NOT_FOUND';
        } catch (GameInvalidException) {
            $code = 'GAME_INVALID';
        }

        return $code;
    }
}
