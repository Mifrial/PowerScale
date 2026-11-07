<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameProcessRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameProcessRepository;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessStep;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\StrikeModifier;

/**
 * Удар шага процесса: контекст строки и пакет преимуществ.
 */
final class GameProcessStrike
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

        return $stepCode === ''
            ? $this->closingStrike($game, $row, $spec, $abilityLevels)
            : $this->stepStrike($spec, $row, $stepCode, $open);
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
        $package = $this->packageStrike($process);
        if ($package !== null) {
            return [
                'advantage' => is_int($package['advantage'] ?? null) ? $package['advantage'] : 0,
                'extraSuccesses' => is_int($package['extraSuccesses'] ?? null) ? $package['extraSuccesses'] : 0,
            ];
        }

        $step = is_array($process) ? ($process['step'] ?? null) : null;
        $row = is_array($process) ? ($process['row'] ?? null) : null;
        if (!$step instanceof ProcessStep || !$row instanceof GameProcessRecord || $step->getStrike() === null) {
            return ['advantage' => 0, 'extraSuccesses' => 0];
        }

        return $this->strikeOf($step->getStrike(), $row->getCounters());
    }

    /**
     * Пакет закрывающего удара.
     *
     * @param array<string, mixed>|null $process Контекст шага.
     *
     * @return array<string, mixed>|null Пакет или null.
     */
    private function packageStrike(?array $process): ?array
    {
        if (!is_array($process) || !is_array($process['strike'] ?? null)) {
            return null;
        }

        return $process['strike'];
    }

    /**
     * Контекст закрытия процесса чужой атакой.
     *
     * @param GameRecord $game Игра.
     * @param GameProcessRecord $row Строка процесса.
     * @param ProcessSpec $spec Тело.
     * @param array<string, int> $abilityLevels Уровни способностей.
     *
     * @return array<string, mixed> Контекст.
     *
     * @throws GameInvalidException Если спека битая.
     */
    private function closingStrike(
        GameRecord $game,
        GameProcessRecord $row,
        ProcessSpec $spec,
        array $abilityLevels,
    ): array {
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

    /**
     * Контекст обычного шага удара.
     *
     * @param ProcessSpec $spec Тело.
     * @param GameProcessRecord $row Строка процесса.
     * @param string $stepCode Шаг.
     * @param array<string, mixed> $open Строка действия.
     *
     * @return array<string, mixed> Контекст.
     *
     * @throws GameInvalidException Если шаг чужой.
     */
    private function stepStrike(
        ProcessSpec $spec,
        GameProcessRecord $row,
        string $stepCode,
        array $open,
    ): array {
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
            'prior' => $spec->isRepeatWeaponCircumstance() ? $this->priorWeaponUses($row, $weaponKey) : 0,
            'actionPoints' => $this->rules->actionPoints($step),
        ];
    }

    /**
     * Пакет конкретного удара по счётчикам.
     *
     * @param StrikeModifier $strike Удар шага.
     * @param array<string, mixed> $counters Счётчики.
     *
     * @return array{advantage: int, extraSuccesses: int} Пакет.
     */
    private function strikeOf(
        StrikeModifier $strike,
        array $counters,
    ): array {
        return [
            'advantage' => $strike->advantageOf($counters),
            'extraSuccesses' => $strike->extraSuccessesOf($counters),
        ];
    }

    /**
     * Ключ экземпляра оружия.
     *
     * @param array<string, mixed> $choice Выбор удара.
     *
     * @return string Ключ.
     */
    private function weaponKey(array $choice): string
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
     * Число прошлых использований экземпляра.
     *
     * @param GameProcessRecord $row Строка процесса.
     * @param string $weaponKey Экземпляр.
     *
     * @return int Число.
     */
    private function priorWeaponUses(GameProcessRecord $row, string $weaponKey): int
    {
        return $row->getWeaponUseCounts()[$weaponKey] ?? 0;
    }
}
