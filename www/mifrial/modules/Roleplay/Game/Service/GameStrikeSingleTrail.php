<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameStrikeSingleRun;

/**
 * Завершение действия после удара: модификаторы и ожидания.
 */
final class GameStrikeSingleTrail
{
    /**
     * Создаёт завершение действия.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameActionModifierRunner $modifiers Модификаторы действия.
     * @param GameProcessFlow $processes Сессия процесса.
     * @param GameStrikeSpend $spend Траты.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameActionModifierRunner $modifiers,
        private readonly GameProcessFlow $processes,
        private readonly GameStrikeSpend $spend,
    ) {
    }

    /**
     * Досчитывает действие и возвращает обновлённое ожидание.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param array<string, mixed> $pending Ожидание боя.
     *
     * @return array{pending: array<string, mixed>, modifiers: array<string, mixed>|null} Итог.
     */
    public function apply(GameStrikeSingleRun $run, array $pending): array
    {
        $modifierCodes = $this->modifierCodes($run->row);
        $modifiers = null;
        $skipAfterAction = false;
        if ($modifierCodes !== []) {
            $modifiers = $this->modifiers->perform(new GameActionModifierInput(
                $run->input->game,
                $run->battle,
                $run->open,
                $run->strikeId,
                $run->actionRuleCode,
                $run->attackerKind,
                $run->attackerId,
                $modifierCodes,
                $run->modifier,
                $this->rules->equipment->gripBonus($run->attackContext['occupyHands']),
                $run->scope->actorUserId,
            ));
            $skipAfterAction = ($modifiers['skipped'] ?? false) === true;
        }

        $pending = $this->completeAttacker($run, $pending, $skipAfterAction);
        $pending = $this->completeProcess($run, $pending);
        $pending = $this->completeDefender($run, $pending);

        return ['pending' => $pending, 'modifiers' => $modifiers];
    }

    /**
     * Закрывает ожидание действия атакующего.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param array<string, mixed> $pending Ожидание.
     * @param bool $skipAfterAction Пропуск последействия.
     *
     * @return array<string, mixed> Ожидание.
     */
    private function completeAttacker(GameStrikeSingleRun $run, array $pending, bool $skipAfterAction): array
    {
        $process = $run->process;
        $actionPoints = is_array($process) && is_int($process['actionPoints'] ?? null)
            ? $process['actionPoints']
            : $this->spend->getActionAp($run->slice, $run->actionRuleCode, true);

        return (new GamePendingActionCosts())->complete(
            $run->slice,
            $run->attackerChoices,
            $run->actionRuleCode,
            $run->attackerKind,
            $run->attackerId,
            $pending,
            ['action-points' => $actionPoints],
            [$run->targetKey],
            $skipAfterAction,
        );
    }

    /**
     * Закрывает шаг процесса атаки.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param array<string, mixed> $pending Ожидание.
     *
     * @return array<string, mixed> Ожидание.
     */
    private function completeProcess(GameStrikeSingleRun $run, array $pending): array
    {
        $process = $run->process;
        if (!is_array($process)) {
            return $pending;
        }

        $actor = ['type' => $run->attackerKind, 'id' => $run->attackerId];
        if (($process['closing'] ?? false) === true) {
            return $this->processes->closeByAttack($run->input->game, $actor, $process['row']->getId(), $pending);
        }

        $hitSuccess = $run->isPush ? !$run->autoFail : (!$run->autoFail && $run->success > 0);

        return $this->processes->complete(
            $run->input->game,
            $actor,
            $process['row']->getId(),
            $process['step']->getCode(),
            $hitSuccess,
            $pending,
            $process['weaponKey'],
            $run->target,
        );
    }

    /**
     * Закрывает ожидание реакции защитника.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param array<string, mixed> $pending Ожидание.
     *
     * @return array<string, mixed> Ожидание.
     */
    private function completeDefender(GameStrikeSingleRun $run, array $pending): array
    {
        if ($run->reactionRuleCode === null) {
            return $pending;
        }

        return (new GamePendingActionCosts())->complete(
            $run->slice,
            $run->defenderChoices,
            $run->reactionRuleCode,
            $run->target['type'],
            $run->target['id'],
            $pending,
            $this->spend->getSpendAmounts($run->spends),
        );
    }

    /**
     * Объявленные модификаторы действия из строки.
     *
     * @param array<string, mixed> $row Строка удара.
     *
     * @return list<string> Коды.
     */
    private function modifierCodes(array $row): array
    {
        $raw = $row['action_modifiers'] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (!is_array($raw)) {
            return [];
        }

        $codes = [];
        foreach ($raw as $code) {
            if (is_string($code) && $code !== '') {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
