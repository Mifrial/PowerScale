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
 * Сессия процесса: шаг, переход и завершение.
 */
final class GameProcessFlow
{
    /**
     * Создаёт сценарий.
     *
     * @param GameProcessRepository $processes Строки процесса.
     * @param GameProcessRules $rules Правила процесса.
     * @param GameStrikeRules $strikeRules Срез игры.
     * @param GameCardAvailability $availability Доступность карточки.
     * @param GamePendingActionCosts $pendingCosts Ожидание.
     *
     * @return void
     */
    public function __construct(
        private readonly GameProcessRepository $processes,
        private readonly GameProcessRules $rules,
        private readonly GameStrikeRules $strikeRules,
        private readonly GameCardAvailability $availability,
        private readonly GamePendingActionCosts $pendingCosts,
    ) {
    }

    /**
     * Проверяет объявление шага и возвращает его контекст.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array{type: string, id: int} $actor Актор.
     * @param string|null $processRuleCode Карточка процесса.
     * @param string|null $processStepCode Шаг.
     * @param string $kind Вид strike или action.
     * @param array{type: string, id: int}|null $target Цель удара.
     *
     * @return array<string, mixed>|null Контекст или null без процесса.
     *
     * @throws GameInvalidException Если шаг недоступен.
     */
    public function prepare(
        GameRecord $game,
        GameBattleRecord $battle,
        array $actor,
        ?string $processRuleCode,
        ?string $processStepCode,
        string $kind,
        ?array $target = null,
    ): ?array {
        if ($processRuleCode === null || $processRuleCode === '') {
            return null;
        }

        if ($kind !== 'strike' && $kind !== 'action') {
            throw new GameInvalidException('Game process step is invalid');
        }

        $slice = $this->strikeRules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $spec = $this->rules->spec($slice, $processRuleCode);
        $open = $this->processes->findOpenForActor($battle->getId(), $actor['type'], $actor['id']);
        $isStart = $open === null;
        if ($isStart) {
            $stepCode = $processStepCode === null || $processStepCode === ''
                ? $spec->getStartStepCode()
                : $processStepCode;
            if ($stepCode !== $spec->getStartStepCode()) {
                throw new GameInvalidException('Game process step is invalid');
            }

            $available = $this->rules->availableSteps(
                $spec,
                null,
                null,
                $this->rules->initialCounters($spec),
            );
            if (!in_array($stepCode, $available, true)) {
                throw new GameInvalidException('Game process step is not available');
            }
        } else {
            if ($open->getProcessRuleCode() !== $processRuleCode) {
                throw new GameInvalidException('Game process is already open');
            }

            if ($processStepCode === null || $processStepCode === '') {
                throw new GameInvalidException('Game process step is missing');
            }

            $available = $this->rules->availableSteps(
                $spec,
                $open->getCurrentStepCode(),
                $open->getStepStatus(),
                $open->getCounters(),
            );
            if (!in_array($processStepCode, $available, true)) {
                throw new GameInvalidException('Game process step is not available');
            }

            $stepCode = $processStepCode;
        }

        $step = $this->rules->step($spec, $stepCode);
        $execution = $step->getExecution();
        if ($execution->isNone() && $kind === 'strike') {
            throw new GameInvalidException('Game process step has no action');
        }

        if (
            $spec->isSingleTarget()
            && $target !== null
            && $open !== null
            && $open->getLastTargetType() !== null
            && $open->getLastTargetId() !== null
            && ($open->getLastTargetType() !== $target['type'] || $open->getLastTargetId() !== $target['id'])
        ) {
            throw new GameInvalidException('Game process target is locked');
        }

        if ($kind === 'strike' && !$execution->isStrike()) {
            throw new GameInvalidException('Game process step is not a strike');
        }

        if ($kind === 'action' && !$execution->isAction() && !$execution->isNone()) {
            throw new GameInvalidException('Game process step is not an action');
        }

        $actionCode = $execution->getActionCode();
        if ($execution->isNone()) {
            $actionCode = null;
        } elseif ($actionCode === null || $actionCode === '') {
            if ($kind === 'action') {
                throw new GameInvalidException('Game process action is missing');
            }

            $actionCode = $processRuleCode;
        }

        $this->availability->assertAvailable($game, $actor['type'], $actor['id'], $processRuleCode);
        if ($actionCode !== null && $actionCode !== $processRuleCode) {
            $this->availability->assertAvailable($game, $actor['type'], $actor['id'], $actionCode);
        }

        if (
            $kind === 'strike'
            && $this->strikeRules->cards->isWideAttack(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $actionCode,
            )
        ) {
            throw new GameInvalidException('Game process wide strike is not supported');
        }

        return [
            'processRuleCode' => $processRuleCode,
            'processName' => $slice->findLive($processRuleCode)?->getName() ?? $processRuleCode,
            'processStepCode' => $step->getCode(),
            'actionCode' => $actionCode,
            'isStart' => $isStart,
            'processId' => $isStart ? null : $open->getId(),
            'session' => $open,
            'spec' => $spec,
            'step' => $step,
            'execution' => $execution,
            'costs' => $this->rules->costs($step),
        ];
    }

    /**
     * Открывает сессию шага или возвращает существующую.
     *
     * @param GameBattleRecord $battle Бой.
     * @param array{type: string, id: int} $actor Актор.
     * @param array<string, mixed> $context Контекст шага.
     *
     * @return int Id процесса.
     *
     * @throws GameInvalidException Если шаг битый.
     */
    public function begin(GameBattleRecord $battle, array $actor, array $context): int
    {
        if (($context['isStart'] ?? false) !== true) {
            $processId = $context['processId'] ?? null;
            if (!is_int($processId)) {
                throw new GameInvalidException('Game process is invalid');
            }

            return $processId;
        }

        $step = $context['step'] ?? null;
        if (!$step instanceof ProcessStep) {
            throw new GameInvalidException('Game process step is invalid');
        }

        $spec = $context['spec'] ?? null;
        if (!$spec instanceof ProcessSpec) {
            throw new GameInvalidException('Game process is invalid');
        }

        $row = $this->processes->add(
            $battle->getSessionId(),
            $battle->getId(),
            $actor['type'],
            $actor['id'],
            [
                'process_rule_code' => $context['processRuleCode'],
                'current_step_code' => $step->getCode(),
                'step_status' => 'pending',
                'counters' => $this->rules->initialCounters($spec),
                'weapon_use_counts' => [],
            ],
        );

        return $row->getId();
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
        $fields = [];
        $effects = [];
        if ($success) {
            $fields = [
                'current_step_code' => $step->getCode(),
                'step_status' => 'completed',
            ];
            if (in_array($step->getCode(), $spec->getExitStepCodes(), true)) {
                $fields['status'] = 'resolved';
                $effects = $spec->getCompletionEffects();
            }
        } elseif ($spec->getFailure() === 'restart_from_first') {
            $fields = [
                'current_step_code' => $spec->getStartStepCode(),
                'step_status' => 'pending',
            ];
        } else {
            $fields = [
                'current_step_code' => $step->getCode(),
                'step_status' => 'pending',
                'status' => 'cancelled',
            ];
            $effects = $spec->getCompletionEffects();
        }

        if ($spec->isRepeatWeaponCircumstance() && $weaponKey !== null) {
            $counts = $row->getWeaponUseCounts();
            $counts[$weaponKey] = ($counts[$weaponKey] ?? 0) + 1;
            $fields['weapon_use_counts'] = $counts;
        }

        $counters = $this->rules->countersAfterStep($step, $row->getCounters(), $success, $spec->getFailure());
        if ($counters !== $row->getCounters()) {
            $fields['counters'] = $counters;
        }

        if ($target !== null) {
            $fields['last_target_type'] = $target['type'];
            $fields['last_target_id'] = $target['id'];
        }

        $this->processes->updateState($processId, $fields);
        if ($effects !== []) {
            $pending = $this->pendingCosts->appendEffects(
                $pending,
                $actor['type'],
                $actor['id'],
                $effects,
                $row->getProcessRuleCode(),
            );
        }

        return $pending;
    }

    /**
     * Закрывает сессию чужой атакой: resolved и эффекты завершения, без шага.
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
        if ($effects !== []) {
            $pending = $this->pendingCosts->appendEffects(
                $pending,
                $actor['type'],
                $actor['id'],
                $effects,
                $row->getProcessRuleCode(),
            );
        }

        return $pending;
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

        $row = $this->processes->findOpenForActor($battle->getId(), $actor['type'], $actor['id']);
        if ($row === null || $row->getProcessRuleCode() === null || $row->getCurrentStepCode() === null) {
            throw new GameInvalidException('Game process is missing');
        }

        $slice = $this->strikeRules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $spec = $this->rules->spec($slice, $row->getProcessRuleCode());
        $step = $this->rules->step($spec, $row->getCurrentStepCode());
        $effects = $mode === 'normal' ? $spec->getCompletionEffects() : $step->getInterruptionEffects();
        if ($mode === 'emergency' && $effects === []) {
            throw new GameInvalidException('Game process emergency interruption is invalid');
        }

        $this->processes->updateState($row->getId(), ['status' => 'cancelled']);
        if ($effects !== []) {
            $pending = $this->pendingCosts->appendEffects(
                $pending,
                $actor['type'],
                $actor['id'],
                $effects,
                $row->getProcessRuleCode(),
            );
        }

        return [
            'pending' => $pending,
            'processRuleCode' => $row->getProcessRuleCode(),
            'stepCode' => $step->getCode(),
        ];
    }

    /**
     * Счётчик прошлых использований экземпляра оружия.
     *
     * @param GameProcessRecord|null $row Строка процесса.
     * @param ProcessSpec $spec Тело.
     * @param string $weaponKey Экземпляр.
     *
     * @return int Число.
     */
    public function priorWeaponUses(?GameProcessRecord $row, ProcessSpec $spec, string $weaponKey): int
    {
        if ($row === null || !$spec->isRepeatWeaponCircumstance()) {
            return 0;
        }

        return $row->getWeaponUseCounts()[$weaponKey] ?? 0;
    }

    /**
     * Ключ экземпляра оружия.
     *
     * @param array<string, mixed> $choice Выбор удара.
     *
     * @return string Ключ.
     */
    public function weaponKey(array $choice): string
    {
        $itemInventoryId = is_int($choice['itemInventoryId'] ?? null) ? $choice['itemInventoryId'] : 0;
        $itemRuleCode = is_string($choice['itemRuleCode'] ?? null) ? $choice['itemRuleCode'] : '';
        $instanceIndex = is_int($choice['instanceIndex'] ?? null) ? $choice['instanceIndex'] : 0;
        if ($itemInventoryId > 0) {
            return 'id:' . $itemInventoryId . ':' . $instanceIndex;
        }

        return 'code:' . $itemRuleCode . ':' . $instanceIndex;
    }

    /**
     * Контекст шага-удара по строке действия.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Строка действия.
     * @param array<string, int> $abilityLevels Уровни способностей актора.
     *
     * @return array<string, mixed>|null Контекст или null.
     *
     * @throws GameInvalidException Если строка или спека битые.
     */
    public function strike(GameRecord $game, array $open, array $abilityLevels = []): ?array
    {
        $processId = $open['process_id'] ?? null;
        if (!is_int($processId)) {
            return null;
        }

        $row = $this->processes->getById($processId);
        if ($row->getStatus() !== 'open' || $row->getProcessRuleCode() === null) {
            return null;
        }

        $slice = $this->strikeRules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $spec = $this->rules->spec($slice, $row->getProcessRuleCode());
        $stepCode = is_string($open['process_step_code'] ?? null) ? $open['process_step_code'] : '';
        if ($stepCode === '') {
            $counters = $row->getCounters();
            $closer = $this->strikeRules->cards->closingAbility(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $row->getProcessRuleCode(),
                $counters,
                $abilityLevels,
            );
            $strike = $closer === null
                ? null
                : $closer['spec']->getBase()->getClosesParentProcess()?->getStrike();

            return [
                'row' => $row,
                'spec' => $spec,
                'closing' => true,
                'strike' => [
                    'advantage' => $strike === null ? 0 : $strike->advantageOf($counters),
                    'extraSuccesses' => $strike === null ? 0 : $strike->extraSuccessesOf($counters),
                ],
            ];
        }

        $step = $this->rules->step($spec, $stepCode);
        $weaponKey = $this->weaponKey([
            'itemInventoryId' => is_int($open['item_inventory_id'] ?? null) ? $open['item_inventory_id'] : 0,
            'itemRuleCode' => is_string($open['item_rule_code'] ?? null) ? $open['item_rule_code'] : '',
            'instanceIndex' => is_int($open['instance_index'] ?? null) ? $open['instance_index'] : 0,
        ]);

        return [
            'row' => $row,
            'spec' => $spec,
            'step' => $step,
            'weaponKey' => $weaponKey,
            'prior' => $this->priorWeaponUses($row, $spec, $weaponKey),
            'actionPoints' => $this->rules->actionPoints($step),
        ];
    }

    /**
     * Открытая сессия актора, которую может закрыть эта атака.
     *
     * Нет сессии, другой цели, порога или уровня — null: атака обычная.
     * Широкая атака и толчок под подходящим закрытием — отказ.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array{type: string, id: int} $actor Актор.
     * @param array{type: string, id: int} $target Цель атаки.
     * @param string $actionCode Карточка атаки.
     * @param array<string, int> $abilityLevels Уровни способностей актора.
     *
     * @return int|null Id процесса или null.
     *
     * @throws GameInvalidException Если закрытие широкое или толчок.
     */
    public function closingFor(
        GameRecord $game,
        GameBattleRecord $battle,
        array $actor,
        array $target,
        string $actionCode,
        array $abilityLevels,
    ): ?int {
        $row = $this->processes->findOpenForActor($battle->getId(), $actor['type'], $actor['id']);
        if ($row === null || $row->getProcessRuleCode() === null) {
            return null;
        }

        if ($row->getLastTargetType() !== $target['type'] || $row->getLastTargetId() !== $target['id']) {
            return null;
        }

        $closer = $this->strikeRules->cards->closingAbility(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $row->getProcessRuleCode(),
            $row->getCounters(),
            $abilityLevels,
        );
        if ($closer === null) {
            return null;
        }

        if ($this->strikeRules->cards->pushOf($game->getSpaceId(), $game->getRulesRevision(), $actionCode) !== null) {
            throw new GameInvalidException('Game process closing push is not supported');
        }

        if ($this->strikeRules->cards->isWideAttack($game->getSpaceId(), $game->getRulesRevision(), $actionCode)) {
            throw new GameInvalidException('Game process closing wide strike is not supported');
        }

        return $row->getId();
    }

    /**
     * Вычисленный пакет удара шага процесса.
     *
     * @param array<string, mixed>|null $process Контекст шага.
     *
     * @return array{advantage: int, extraSuccesses: int} Пакет; нули без шага.
     */
    public function strikePackage(?array $process): array
    {
        $empty = ['advantage' => 0, 'extraSuccesses' => 0];
        if (is_array($process) && is_array($process['strike'] ?? null)) {
            return [
                'advantage' => is_int($process['strike']['advantage'] ?? null)
                    ? $process['strike']['advantage']
                    : 0,
                'extraSuccesses' => is_int($process['strike']['extraSuccesses'] ?? null)
                    ? $process['strike']['extraSuccesses']
                    : 0,
            ];
        }

        $step = is_array($process) ? ($process['step'] ?? null) : null;
        $row = is_array($process) ? ($process['row'] ?? null) : null;
        if (!$step instanceof ProcessStep || !$row instanceof GameProcessRecord) {
            return $empty;
        }

        $strike = $step->getStrike();
        if ($strike === null) {
            return $empty;
        }

        $counters = $row->getCounters();

        return [
            'advantage' => $strike->advantageOf($counters),
            'extraSuccesses' => $strike->extraSuccessesOf($counters),
        ];
    }

    /**
     * Состояние процесса для панели боя.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array{type: string, id: int} $actor Актор.
     * @param array<string, int> $abilityLevels Уровни способностей актора.
     *
     * @return array<string, mixed>|null Состояние или null.
     *
     * @throws GameInvalidException Если спека битая.
     */
    public function view(
        GameRecord $game,
        GameBattleRecord $battle,
        array $actor,
        array $abilityLevels = [],
    ): ?array {
        $row = $this->processes->findOpenForActor($battle->getId(), $actor['type'], $actor['id']);
        if ($row === null || $row->getProcessRuleCode() === null) {
            return null;
        }

        $slice = $this->strikeRules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $spec = $this->rules->spec($slice, $row->getProcessRuleCode());
        $available = $this->rules->availableSteps(
            $spec,
            $row->getCurrentStepCode(),
            $row->getStepStatus(),
            $row->getCounters(),
        );
        $steps = [];
        $counters = $row->getCounters();
        foreach ($available as $code) {
            $step = $this->rules->step($spec, $code);
            $strike = $step->getStrike();
            $steps[] = [
                'code' => $step->getCode(),
                'name' => $step->getName(),
                'description' => $step->getDescription(),
                'actionPoints' => $this->rules->actionPoints($step),
                'execution' => $step->getExecution()->getType(),
                'actionCode' => $step->getExecution()->getActionCode(),
                'strike' => $strike === null ? null : [
                    'advantage' => $strike->advantageOf($counters),
                    'extraSuccesses' => $strike->extraSuccessesOf($counters),
                ],
            ];
        }

        return [
            'processId' => $row->getId(),
            'processRuleCode' => $row->getProcessRuleCode(),
            'currentStepCode' => $row->getCurrentStepCode(),
            'stepStatus' => $row->getStepStatus(),
            'status' => $row->getStatus(),
            'counters' => $row->getCounters(),
            'weaponUseCounts' => $row->getWeaponUseCounts(),
            'lastTargetType' => $row->getLastTargetType(),
            'lastTargetId' => $row->getLastTargetId(),
            'availableSteps' => $steps,
            'closing' => $this->closingView($game, $row, $abilityLevels),
        ];
    }

    /**
     * Доступное закрытие процесса чужой атакой для панели боя.
     *
     * @param GameRecord $game Игра.
     * @param GameProcessRecord $row Строка процесса.
     * @param array<string, int> $abilityLevels Уровни способностей актора.
     *
     * @return array{cardCode: string, strike: array{advantage: int, extraSuccesses: int}}|null Закрытие или null.
     *
     * @throws GameInvalidException Если спека битая.
     */
    private function closingView(GameRecord $game, GameProcessRecord $row, array $abilityLevels): ?array
    {
        if ($row->getProcessRuleCode() === null) {
            return null;
        }

        if ($row->getLastTargetType() === null || $row->getLastTargetId() === null) {
            return null;
        }

        $counters = $row->getCounters();
        $closer = $this->strikeRules->cards->closingAbility(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $row->getProcessRuleCode(),
            $counters,
            $abilityLevels,
        );
        if ($closer === null) {
            return null;
        }

        $strike = $closer['spec']->getBase()->getClosesParentProcess()?->getStrike();

        return [
            'cardCode' => $closer['code'],
            'strike' => [
                'advantage' => $strike === null ? 0 : $strike->advantageOf($counters),
                'extraSuccesses' => $strike === null ? 0 : $strike->extraSuccessesOf($counters),
            ],
        ];
    }
}
