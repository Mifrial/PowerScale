<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameProcessRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameProcessRepository;

/**
 * Панель процесса: доступные шаги и закрытие чужой атакой.
 */
final class GameProcessView
{
    /**
     * Создаёт сценарий.
     *
     * @param GameProcessRepository $processes Строки процесса.
     * @param GameProcessRules $rules Правила процесса.
     * @param GameStrikeRules $strikeRules Срез игры.
     *
     * @return void
     */
    public function __construct(
        private readonly GameProcessRepository $processes,
        private readonly GameProcessRules $rules,
        private readonly GameStrikeRules $strikeRules,
    ) {
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

        $spec = $this->spec($game, $row);
        $steps = $this->steps($spec, $row);

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
     * Открытая сессия актора, которую может закрыть эта атака.
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
        if (!$this->closable($row, $target)) {
            return null;
        }

        $closer = $this->closer($game, $row, $abilityLevels);
        if ($closer === null) {
            return null;
        }

        $this->assertAttack($game, $actionCode);

        return $row->getId();
    }

    /**
     * Строка открыта и её прошлая цель совпадает.
     *
     * @param GameProcessRecord|null $row Строка процесса.
     * @param array{type: string, id: int} $target Цель атаки.
     *
     * @return bool Истина, если закрытие возможно.
     */
    private function closable(?GameProcessRecord $row, array $target): bool
    {
        if ($row === null || $row->getProcessRuleCode() === null) {
            return false;
        }

        return $row->getLastTargetType() === $target['type'] && $row->getLastTargetId() === $target['id'];
    }

    /**
     * Тело процесса строки.
     *
     * @param GameRecord $game Игра.
     * @param GameProcessRecord $row Строка процесса.
     *
     * @return \Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessSpec Тело.
     *
     * @throws GameInvalidException Если спека битая.
     */
    private function spec(GameRecord $game, GameProcessRecord $row): \Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessSpec
    {
        $slice = $this->strikeRules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());

        return $this->rules->spec($slice, $row->getProcessRuleCode());
    }

    /**
     * Доступные шаги строки.
     *
     * @param \Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessSpec $spec Тело.
     * @param GameProcessRecord $row Строка процесса.
     *
     * @return array<int, array<string, mixed>> Шаги.
     *
     * @throws GameInvalidException Если шаг чужой.
     */
    private function steps(
        \Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessSpec $spec,
        GameProcessRecord $row,
    ): array {
        $available = $this->rules->availableSteps(
            $spec,
            $row->getCurrentStepCode(),
            $row->getStepStatus(),
            $row->getCounters(),
        );
        $counters = $row->getCounters();
        $steps = [];
        foreach ($available as $code) {
            $steps[] = $this->step($spec, $code, $counters);
        }

        return $steps;
    }

    /**
     * Пункт доступного шага.
     *
     * @param \Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessSpec $spec Тело.
     * @param string $code Код шага.
     * @param array<string, mixed> $counters Счётчики.
     *
     * @return array<string, mixed> Пункт.
     *
     * @throws GameInvalidException Если шаг чужой.
     */
    private function step(
        \Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessSpec $spec,
        string $code,
        array $counters,
    ): array {
        $step = $this->rules->step($spec, $code);
        $strike = $step->getStrike();

        return [
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

    /**
     * Доступное закрытие процесса чужой атакой.
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
        if ($row->getLastTargetType() === null || $row->getLastTargetId() === null) {
            return null;
        }

        $counters = $row->getCounters();
        $closer = $this->closer($game, $row, $abilityLevels);
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

    /**
     * Карточка закрытия процесса.
     *
     * @param GameRecord $game Игра.
     * @param GameProcessRecord $row Строка процесса.
     * @param array<string, int> $abilityLevels Уровни способностей актора.
     *
     * @return array<string, mixed>|null Карточка или null.
     */
    private function closer(GameRecord $game, GameProcessRecord $row, array $abilityLevels): ?array
    {
        return $this->strikeRules->cards->closingAbility(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $row->getProcessRuleCode(),
            $row->getCounters(),
            $abilityLevels,
        );
    }

    /**
     * Атака закрытия не широкая и не толчок.
     *
     * @param GameRecord $game Игра.
     * @param string $actionCode Карточка атаки.
     *
     * @return void
     *
     * @throws GameInvalidException Если атака недопустима.
     */
    private function assertAttack(GameRecord $game, string $actionCode): void
    {
        if ($this->strikeRules->cards->pushOf($game->getSpaceId(), $game->getRulesRevision(), $actionCode) !== null) {
            throw new GameInvalidException('Game process closing push is not supported');
        }

        if ($this->strikeRules->cards->isWideAttack($game->getSpaceId(), $game->getRulesRevision(), $actionCode)) {
            throw new GameInvalidException('Game process closing wide strike is not supported');
        }
    }
}
