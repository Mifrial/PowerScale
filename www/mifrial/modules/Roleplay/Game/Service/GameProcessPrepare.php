<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameProcessRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameProcessRepository;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessExecution;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessStep;

/**
 * Проверка объявления шага процесса.
 */
final class GameProcessPrepare
{
    /**
     * Создаёт сценарий.
     *
     * @param GameProcessRepository $processes Строки процесса.
     * @param GameProcessRules $rules Правила процесса.
     * @param GameStrikeRules $strikeRules Срез игры.
     * @param GameCardAvailability $availability Доступность карточки.
     *
     * @return void
     */
    public function __construct(
        private readonly GameProcessRepository $processes,
        private readonly GameProcessRules $rules,
        private readonly GameStrikeRules $strikeRules,
        private readonly GameCardAvailability $availability,
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
        $stepCode = $this->resolveStep($spec, $open, $processStepCode, $isStart, $processRuleCode);
        $step = $this->rules->step($spec, $stepCode);
        $execution = $step->getExecution();
        $this->assertExecution($spec, $execution, $kind, $target, $open);
        $actionCode = $this->actionCode($execution, $kind, $processRuleCode);
        $this->assertAvailable($game, $actor, $processRuleCode, $actionCode);

        return $this->context($game, $slice, $spec, $step, $execution, $actionCode, $isStart, $open, $processRuleCode, $kind);
    }

    /**
     * Собирает контекст шага.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез.
     * @param ProcessSpec $spec Тело.
     * @param ProcessStep $step Шаг.
     * @param ProcessExecution $execution Исполнение.
     * @param string|null $actionCode Карточка действия.
     * @param bool $isStart Новая сессия.
     * @param GameProcessRecord|null $open Открытый процесс.
     * @param string $processRuleCode Карточка процесса.
     * @param string $kind Вид.
     *
     * @return array<string, mixed> Контекст.
     *
     * @throws GameInvalidException Если удар широкий.
     */
    private function context(
        GameRecord $game,
        CharacterRuleSlice $slice,
        ProcessSpec $spec,
        ProcessStep $step,
        ProcessExecution $execution,
        ?string $actionCode,
        bool $isStart,
        ?GameProcessRecord $open,
        string $processRuleCode,
        string $kind,
    ): array {
        $this->assertNotWide($game, $kind, $actionCode);

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
     * Код шага объявления.
     *
     * @param ProcessSpec $spec Тело.
     * @param GameProcessRecord|null $open Открытый процесс.
     * @param string|null $stepCode Запрошенный шаг.
     * @param bool $isStart Новая сессия.
     * @param string $processRuleCode Карточка процесса.
     *
     * @return string Код шага.
     *
     * @throws GameInvalidException Если шаг недоступен.
     */
    private function resolveStep(
        ProcessSpec $spec,
        ?GameProcessRecord $open,
        ?string $stepCode,
        bool $isStart,
        string $processRuleCode,
    ): string {
        if ($isStart || $open === null) {
            return $this->startStep($spec, $stepCode);
        }

        return $this->continueStep($open, $spec, $stepCode, $processRuleCode);
    }

    /**
     * Первый шаг процесса.
     *
     * @param ProcessSpec $spec Тело.
     * @param string|null $stepCode Запрошенный шаг.
     *
     * @return string Код шага.
     *
     * @throws GameInvalidException Если шаг не стартовый или недоступен.
     */
    private function startStep(ProcessSpec $spec, ?string $stepCode): string
    {
        $code = $stepCode === null || $stepCode === '' ? $spec->getStartStepCode() : $stepCode;
        if ($code !== $spec->getStartStepCode()) {
            throw new GameInvalidException('Game process step is invalid');
        }

        $available = $this->rules->availableSteps($spec, null, null, $this->rules->initialCounters($spec));
        if (!in_array($code, $available, true)) {
            throw new GameInvalidException('Game process step is not available');
        }

        return $code;
    }

    /**
     * Следующий шаг открытого процесса.
     *
     * @param GameProcessRecord $open Открытый процесс.
     * @param ProcessSpec $spec Тело.
     * @param string|null $stepCode Запрошенный шаг.
     * @param string $processRuleCode Карточка процесса.
     *
     * @return string Код шага.
     *
     * @throws GameInvalidException Если процесс уже открыт или шаг недоступен.
     */
    private function continueStep(
        GameProcessRecord $open,
        ProcessSpec $spec,
        ?string $stepCode,
        string $processRuleCode,
    ): string {
        if ($open->getProcessRuleCode() !== $processRuleCode) {
            throw new GameInvalidException('Game process is already open');
        }

        if ($stepCode === null || $stepCode === '') {
            throw new GameInvalidException('Game process step is missing');
        }

        $available = $this->rules->availableSteps(
            $spec,
            $open->getCurrentStepCode(),
            $open->getStepStatus(),
            $open->getCounters(),
        );
        if (!in_array($stepCode, $available, true)) {
            throw new GameInvalidException('Game process step is not available');
        }

        return $stepCode;
    }

    /**
     * Вид шага подходит объявлению.
     *
     * @param ProcessSpec $spec Тело.
     * @param ProcessExecution $execution Исполнение.
     * @param string $kind strike или action.
     * @param array{type: string, id: int}|null $target Цель удара.
     * @param GameProcessRecord|null $open Открытый процесс.
     *
     * @return void
     *
     * @throws GameInvalidException Если вид или цель чужие.
     */
    private function assertExecution(
        ProcessSpec $spec,
        ProcessExecution $execution,
        string $kind,
        ?array $target,
        ?GameProcessRecord $open,
    ): void {
        if ($execution->isNone() && $kind === 'strike') {
            throw new GameInvalidException('Game process step has no action');
        }

        $this->assertTarget($spec, $target, $open);
        $this->assertKind($execution, $kind);
    }

    /**
     * Цель удара не нарушает замок сессии.
     *
     * @param ProcessSpec $spec Тело.
     * @param array{type: string, id: int}|null $target Цель удара.
     * @param GameProcessRecord|null $open Открытый процесс.
     *
     * @return void
     *
     * @throws GameInvalidException Если цель другая.
     */
    private function assertTarget(ProcessSpec $spec, ?array $target, ?GameProcessRecord $open): void
    {
        if ($spec->isSingleTarget() && $target !== null && $this->targetChanged($open, $target)) {
            throw new GameInvalidException('Game process target is locked');
        }
    }

    /**
     * Исполнение шага подходит виду объявления.
     *
     * @param ProcessExecution $execution Исполнение.
     * @param string $kind strike или action.
     *
     * @return void
     *
     * @throws GameInvalidException Если вид чужой.
     */
    private function assertKind(ProcessExecution $execution, string $kind): void
    {
        if ($kind === 'strike' && !$execution->isStrike()) {
            throw new GameInvalidException('Game process step is not a strike');
        }

        if ($kind === 'action' && !$execution->isAction() && !$execution->isNone()) {
            throw new GameInvalidException('Game process step is not an action');
        }
    }

    /**
     * Шаг процесса не широкий удар.
     *
     * @param GameRecord $game Игра.
     * @param string $kind strike или action.
     * @param string|null $actionCode Карточка действия.
     *
     * @return void
     *
     * @throws GameInvalidException Если удар широкий.
     */
    private function assertNotWide(GameRecord $game, string $kind, ?string $actionCode): void
    {
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
    }

    /**
     * Цель отличается от прошлой цели сессии.
     *
     * @param GameProcessRecord|null $open Открытый процесс.
     * @param array{type: string, id: int} $target Цель.
     *
     * @return bool Истина, если цель другая.
     */
    private function targetChanged(?GameProcessRecord $open, array $target): bool
    {
        if ($open === null || $open->getLastTargetType() === null || $open->getLastTargetId() === null) {
            return false;
        }

        return $open->getLastTargetType() !== $target['type'] || $open->getLastTargetId() !== $target['id'];
    }

    /**
     * Код действия шага.
     *
     * @param ProcessExecution $execution Исполнение.
     * @param string $kind strike или action.
     * @param string $processRuleCode Карточка процесса.
     *
     * @return string|null Код действия или null.
     *
     * @throws GameInvalidException Если действие отсутствует.
     */
    private function actionCode(ProcessExecution $execution, string $kind, string $processRuleCode): ?string
    {
        if ($execution->isNone()) {
            return null;
        }

        $actionCode = $execution->getActionCode();
        if ($actionCode === null || $actionCode === '') {
            if ($kind === 'action') {
                throw new GameInvalidException('Game process action is missing');
            }

            return $processRuleCode;
        }

        return $actionCode;
    }

    /**
     * Карточки процесса и действия доступны.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $actor Актор.
     * @param string $processRuleCode Карточка процесса.
     * @param string|null $actionCode Карточка действия.
     *
     * @return void
     *
     * @throws GameInvalidException Если карточка недоступна.
     */
    private function assertAvailable(
        GameRecord $game,
        array $actor,
        string $processRuleCode,
        ?string $actionCode,
    ): void {
        $this->availability->assertAvailable($game, $actor['type'], $actor['id'], $processRuleCode);
        if ($actionCode !== null && $actionCode !== $processRuleCode) {
            $this->availability->assertAvailable($game, $actor['type'], $actor['id'], $actionCode);
        }
    }
}
