<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessStep;

/**
 * Стоимость объявленного действия: карточка, процесс и пол действий.
 */
final class GameStrikeSpendCosts
{
    /**
     * Создаёт расчёт стоимости действия.
     *
     * @param GameStrikeRules $rules Срез и версия персонажа.
     *
     * @return void
     */
    public function __construct(private readonly GameStrikeRules $rules)
    {
    }

    /**
     * Считает траты до долга и жетона.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор атаки.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $choices Выборы листа.
     * @param array<string, mixed> $sheet Лист.
     * @param string $kind Вид атакующего.
     * @param int $id Атакующий.
     *
     * @return array{spends: array<int, ResourceSpend>, pending: array<string, mixed>,
     *               slice: CharacterRuleSlice} Итог.
     */
    public function resolve(
        GameRecord $game,
        array $choice,
        GameBattleRecord $battle,
        array $choices,
        array $sheet,
        string $kind,
        int $id,
    ): array {
        $spends = $this->base($game, $choice, $choices, $sheet);
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $adjusted = (new GamePendingActionCosts())->adjustSpends(
            $slice,
            $choices,
            $choice['actionRuleCode'],
            $kind,
            $id,
            $battle->getPendingActionEffects(),
            $spends,
        );

        return [
            'spends' => $this->floor($slice, $choice, $adjusted['spends']),
            'pending' => $adjusted['pending'],
            'slice' => $slice,
        ];
    }

    /**
     * Траты карточки с поправкой шага процесса.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор атаки.
     * @param array<string, mixed> $choices Выборы листа.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array<int, ResourceSpend> Траты.
     */
    private function base(GameRecord $game, array $choice, array $choices, array $sheet): array
    {
        $spends = $this->rules->spends->resolveResourceSpends(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['actionRuleCode'],
            $choice['itemInventoryId'],
            $choice['itemRuleCode'],
            $choices,
            $sheet,
            $choice['chosenAmounts'],
        );
        $step = $choice['processContext']['step'] ?? null;
        if (!$step instanceof ProcessStep) {
            return $spends;
        }

        return (new GameProcessRules())->replaceCosts($spends, $step);
    }

    /**
     * Пол стоимости для объявления без шага процесса.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choice Выбор атаки.
     * @param array<int, ResourceSpend> $adjusted Траты после поправок.
     *
     * @return array<int, ResourceSpend> Траты.
     */
    private function floor(CharacterRuleSlice $slice, array $choice, array $adjusted): array
    {
        $step = $choice['processContext']['step'] ?? null;
        if ($step instanceof ProcessStep) {
            return $adjusted;
        }

        return (new GameActionFloor())->apply($slice, $choice['actionRuleCode'], $adjusted, true);
    }
}
