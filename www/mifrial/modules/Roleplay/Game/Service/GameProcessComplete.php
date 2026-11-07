<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameProcessRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameProcessRepository;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessStep;

/**
 * Завершение, закрытие и прерывание сессии процесса.
 */
final class GameProcessComplete
{
    /**
     * Создаёт сценарий.
     *
     * @param GameProcessRepository $processes Строки процесса.
     * @param GameProcessRules $rules Правила процесса.
     * @param GameStrikeRules $strikeRules Срез игры.
     * @param GamePendingActionCosts $pendingCosts Ожидание.
     *
     * @return void
     */
    public function __construct(
        private readonly GameProcessRepository $processes,
        private readonly GameProcessRules $rules,
        private readonly GameStrikeRules $strikeRules,
        private readonly GamePendingActionCosts $pendingCosts,
    ) {
    }

    /**
     * Завершает шаг и двигает сессию.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $actor Актор.
     * @param int $processId Процесс.
     * @param string $stepCode Шаг.
     * @param bool $success Успех шага.
     * @param array<string, mixed> $pending Ожидание.
     * @param string|null $weaponKey Экземпляр оружия.
     * @param array{type: string, id: int}|null $target Цель удара.
     *
     * @return array<string, mixed> Ожидание.
     *
     * @throws GameInvalidException Если строка или спека битые.
     */
    public function complete(
        GameRecord $game,
        array $actor,
        int $processId,
        string $stepCode,
        bool $success,
        array $pending,
        ?string $weaponKey = null,
        ?array $target = null,
    ): array {
        $row = $this->processes->getById($processId);
        if ($row->getStatus() !== 'open' || $row->getProcessRuleCode() === null) {
            return $pending;
        }

        $slice = $this->strikeRules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $spec = $this->rules->spec($slice, $row->getProcessRuleCode());
        $step = $this->rules->step($spec, $stepCode);
        $state = $this->completionState($spec, $step, $success);
        $fields = $this->weaponFields($state['fields'], $row, $spec, $weaponKey);
        $fields = $this->counterFields($fields, $row, $step, $success, $spec);
        $fields = $this->targetFields($fields, $target);
        $this->processes->updateState($processId, $fields);
        if ($state['effects'] !== []) {
            $pending = $this->appendEffects($pending, $actor, $state['effects'], $row->getProcessRuleCode());
        }

        return $pending;
    }

    /**
     * Закрывает сессию чужой атакой.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $actor Актор.
     * @param int $processId Процесс.
     * @param array<string, mixed> $pending Ожидание.
     *
     * @return array<string, mixed> Ожидание.
     *
     * @throws GameInvalidException Если строка или спека битые.
     */
    public function closeByAttack(GameRecord $game, array $actor, int $processId, array $pending): array
    {
        $row = $this->processes->getById($processId);
        if ($row->getStatus() !== 'open' || $row->getProcessRuleCode() === null) {
            return $pending;
        }

        $slice = $this->strikeRules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $spec = $this->rules->spec($slice, $row->getProcessRuleCode());
        $this->processes->updateState($processId, ['status' => 'resolved']);

        $effects = $spec->getCompletionEffects();
        if ($effects === []) {
            return $pending;
        }

        return $this->appendEffects($pending, $actor, $effects, $row->getProcessRuleCode());
    }

    /**
     * Прерывает открытый процесс.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array{type: string, id: int} $actor Актор.
     * @param string $mode Режим normal или emergency.
     * @param array<string, mixed> $pending Ожидание.
     *
     * @return array{pending: array<string, mixed>, processRuleCode: string, stepCode: string} Итог.
     *
     * @throws GameInvalidException Если процесса нет или режим чужой.
     */
    public function abort(
        GameRecord $game,
        GameBattleRecord $battle,
        array $actor,
        string $mode,
        array $pending,
    ): array {
        if ($mode !== 'normal' && $mode !== 'emergency') {
            throw new GameInvalidException('Game process interruption is invalid');
        }

        $row = $this->currentRow($battle, $actor);
        $slice = $this->strikeRules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $spec = $this->rules->spec($slice, $row->getProcessRuleCode());
        $step = $this->rules->step($spec, $row->getCurrentStepCode());
        $effects = $this->abortEffects($spec, $step, $mode);
        $this->processes->updateState($row->getId(), ['status' => 'cancelled']);
        if ($effects !== []) {
            $pending = $this->appendEffects($pending, $actor, $effects, $row->getProcessRuleCode());
        }

        return [
            'pending' => $pending,
            'processRuleCode' => $row->getProcessRuleCode(),
            'stepCode' => $step->getCode(),
        ];
    }

    /**
     * Поля и эффекты завершения шага.
     *
     * @param ProcessSpec $spec Тело.
     * @param ProcessStep $step Шаг.
     * @param bool $success Успех.
     *
     * @return array{fields: array<string, mixed>, effects: array<int, mixed>} Поля и эффекты.
     */
    private function completionState(ProcessSpec $spec, ProcessStep $step, bool $success): array
    {
        if ($success) {
            return $this->successState($spec, $step);
        }

        if ($spec->getFailure() === 'restart_from_first') {
            return [
                'fields' => ['current_step_code' => $spec->getStartStepCode(), 'step_status' => 'pending'],
                'effects' => [],
            ];
        }

        return [
            'fields' => [
                'current_step_code' => $step->getCode(),
                'step_status' => 'pending',
                'status' => 'cancelled',
            ],
            'effects' => $spec->getCompletionEffects(),
        ];
    }

    /**
     * Поля успешного шага.
     *
     * @param ProcessSpec $spec Тело.
     * @param ProcessStep $step Шаг.
     *
     * @return array{fields: array<string, mixed>, effects: array<int, mixed>} Поля и эффекты.
     */
    private function successState(ProcessSpec $spec, ProcessStep $step): array
    {
        $exit = in_array($step->getCode(), $spec->getExitStepCodes(), true);
        $fields = [
            'current_step_code' => $step->getCode(),
            'step_status' => 'completed',
        ];
        if ($exit) {
            $fields['status'] = 'resolved';
        }

        return ['fields' => $fields, 'effects' => $exit ? $spec->getCompletionEffects() : []];
    }

    /**
     * Счётчик использования оружия.
     *
     * @param array<string, mixed> $fields Поля.
     * @param GameProcessRecord $row Строка.
     * @param ProcessSpec $spec Тело.
     * @param string|null $weaponKey Экземпляр оружия.
     *
     * @return array<string, mixed> Поля.
     */
    private function weaponFields(
        array $fields,
        GameProcessRecord $row,
        ProcessSpec $spec,
        ?string $weaponKey,
    ): array {
        if (!$spec->isRepeatWeaponCircumstance() || $weaponKey === null) {
            return $fields;
        }

        $counts = $row->getWeaponUseCounts();
        $counts[$weaponKey] = ($counts[$weaponKey] ?? 0) + 1;
        $fields['weapon_use_counts'] = $counts;

        return $fields;
    }

    /**
     * Счётчики после шага.
     *
     * @param array<string, mixed> $fields Поля.
     * @param GameProcessRecord $row Строка.
     * @param ProcessStep $step Шаг.
     * @param bool $success Успех.
     * @param ProcessSpec $spec Тело.
     *
     * @return array<string, mixed> Поля.
     */
    private function counterFields(
        array $fields,
        GameProcessRecord $row,
        ProcessStep $step,
        bool $success,
        ProcessSpec $spec,
    ): array {
        $counters = $this->rules->countersAfterStep($step, $row->getCounters(), $success, $spec->getFailure());
        if ($counters !== $row->getCounters()) {
            $fields['counters'] = $counters;
        }

        return $fields;
    }

    /**
     * Последняя цель шага.
     *
     * @param array<string, mixed> $fields Поля.
     * @param array{type: string, id: int}|null $target Цель.
     *
     * @return array<string, mixed> Поля.
     */
    private function targetFields(array $fields, ?array $target): array
    {
        if ($target !== null) {
            $fields['last_target_type'] = $target['type'];
            $fields['last_target_id'] = $target['id'];
        }

        return $fields;
    }

    /**
     * Дополняет ожидание эффектами.
     *
     * @param array<string, mixed> $pending Ожидание.
     * @param array{type: string, id: int} $actor Актор.
     * @param array<int, mixed> $effects Эффекты.
     * @param string $processRuleCode Карточка процесса.
     *
     * @return array<string, mixed> Ожидание.
     */
    private function appendEffects(
        array $pending,
        array $actor,
        array $effects,
        string $processRuleCode,
    ): array {
        return $this->pendingCosts->appendEffects(
            $pending,
            $actor['type'],
            $actor['id'],
            $effects,
            $processRuleCode,
        );
    }

    /**
     * Открытая строка процесса актора с шагом.
     *
     * @param GameBattleRecord $battle Бой.
     * @param array{type: string, id: int} $actor Актор.
     *
     * @return GameProcessRecord Строка.
     *
     * @throws GameInvalidException Если процесса нет.
     */
    private function currentRow(GameBattleRecord $battle, array $actor): GameProcessRecord
    {
        $row = $this->processes->findOpenForActor($battle->getId(), $actor['type'], $actor['id']);
        if ($row === null || $row->getProcessRuleCode() === null || $row->getCurrentStepCode() === null) {
            throw new GameInvalidException('Game process is missing');
        }

        return $row;
    }

    /**
     * Эффекты прерывания процесса.
     *
     * @param ProcessSpec $spec Тело.
     * @param ProcessStep $step Текущий шаг.
     * @param string $mode normal или emergency.
     *
     * @return array<int, mixed> Эффекты.
     *
     * @throws GameInvalidException Если экстренное прерывание пустое.
     */
    private function abortEffects(ProcessSpec $spec, ProcessStep $step, string $mode): array
    {
        $effects = $mode === 'normal' ? $spec->getCompletionEffects() : $step->getInterruptionEffects();
        if ($mode === 'emergency' && $effects === []) {
            throw new GameInvalidException('Game process emergency interruption is invalid');
        }

        return $effects;
    }
}
