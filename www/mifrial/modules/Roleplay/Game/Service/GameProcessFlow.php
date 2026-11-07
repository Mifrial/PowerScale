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
    public readonly GameProcessStrike $strike;

    public readonly GameProcessView $view;

    private readonly GameProcessPrepare $preparation;

    private readonly GameProcessComplete $completion;

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
        $this->strike = new GameProcessStrike($processes, $rules, $strikeRules);
        $this->view = new GameProcessView($processes, $rules, $strikeRules);
        $this->preparation = new GameProcessPrepare($processes, $rules, $strikeRules, $availability);
        $this->completion = new GameProcessComplete($processes, $rules, $strikeRules, $pendingCosts);
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
        return $this->preparation->prepare(
            $game,
            $battle,
            $actor,
            $processRuleCode,
            $processStepCode,
            $kind,
            $target,
        );
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
            return $this->openId($context);
        }

        $step = $this->contextStep($context);
        $spec = $this->contextSpec($context);
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
        return $this->completion->complete(
            $game,
            $actor,
            $processId,
            $stepCode,
            $success,
            $pending,
            $weaponKey,
            $target,
        );
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
        return $this->completion->closeByAttack($game, $actor, $processId, $pending);
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
        return $this->completion->abort($game, $battle, $actor, $mode, $pending);
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
     * Id уже открытого процесса.
     *
     * @param array<string, mixed> $context Контекст шага.
     *
     * @return int Id процесса.
     *
     * @throws GameInvalidException Если шаг битый.
     */
    private function openId(array $context): int
    {
        $processId = $context['processId'] ?? null;
        if (!is_int($processId)) {
            throw new GameInvalidException('Game process is invalid');
        }

        return $processId;
    }

    /**
     * Шаг контекста.
     *
     * @param array<string, mixed> $context Контекст шага.
     *
     * @return ProcessStep Шаг.
     *
     * @throws GameInvalidException Если шаг битый.
     */
    private function contextStep(array $context): ProcessStep
    {
        $step = $context['step'] ?? null;
        if (!$step instanceof ProcessStep) {
            throw new GameInvalidException('Game process step is invalid');
        }

        return $step;
    }

    /**
     * Тело контекста.
     *
     * @param array<string, mixed> $context Контекст шага.
     *
     * @return ProcessSpec Тело.
     *
     * @throws GameInvalidException Если тело битое.
     */
    private function contextSpec(array $context): ProcessSpec
    {
        $spec = $context['spec'] ?? null;
        if (!$spec instanceof ProcessSpec) {
            throw new GameInvalidException('Game process is invalid');
        }

        return $spec;
    }

}
