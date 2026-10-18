<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityPlainSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityProcessSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\NextActionAttackCostEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\NextActionCostEffect;

/**
 * Читает, снимает и ставит карту ожидания следующего действия.
 */
final class GamePendingActionCosts
{
    /**
     * Сдвигает целые траты удара и возвращает новую карту. Пол 2 не ставит.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы атакующего.
     * @param string $actionRuleCode Карточка.
     * @param string $actorKind Вид.
     * @param int $actorId Id.
     * @param array<string, mixed> $pending Карта.
     * @param array<int, ResourceSpend> $spends Траты карточки.
     *
     * @return array{spends: array<int, ResourceSpend>, pending: array<string, mixed>} Итог.
     */
    public function adjustSpends(
        CharacterRuleSlice $slice,
        array $choices,
        string $actionRuleCode,
        string $actorKind,
        int $actorId,
        array $pending,
        array $spends,
    ): array {
        $amounts = [];
        $kept = [];
        foreach ($spends as $spend) {
            $native = $spend->getAmount()->toNative();
            if (!is_int($native)) {
                $kept[] = $spend;
                continue;
            }

            $amounts[$spend->getResourceCode()] = ($amounts[$spend->getResourceCode()] ?? 0) + $native;
        }

        $turned = $this->turn($slice, $choices, $actionRuleCode, $actorKind, $actorId, $pending, $amounts, true);
        foreach ($turned['amounts'] as $code => $amount) {
            $kept[] = new ResourceSpend($code, new ScalarResourceValue($amount));
        }

        return ['spends' => $kept, 'pending' => $turned['pending']];
    }

    /**
     * Применяет совпавшие сдвиги и заменяет список актора эффектами этой команды.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы актора.
     * @param string $actionRuleCode Карточка команды.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     * @param array<string, mixed> $pending Карта боя.
     * @param array<string, int> $amounts Цены по ресурсам.
     * @param bool $forAttack Команда — атака: снять next_action_attack_cost.
     *
     * @return array{amounts: array<string, int>, pending: array<string, mixed>, touched: array<string, bool>} Итог.
     */
    public function turn(
        CharacterRuleSlice $slice,
        array $choices,
        string $actionRuleCode,
        string $actorKind,
        int $actorId,
        array $pending,
        array $amounts,
        bool $forAttack = false,
    ): array {
        $key = $actorKind . ':' . $actorId;
        $waiting = is_array($pending[$key] ?? null) ? $pending[$key] : [];
        $touched = [];
        foreach ($waiting as $entry) {
            $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
            if (!is_array($effect)) {
                continue;
            }

            $attackCost = ($effect['type'] ?? null) === 'next_action_attack_cost';
            if ($attackCost && !$forAttack) {
                continue;
            }

            if (!$attackCost && !$this->matches($slice, $actionRuleCode, $effect)) {
                continue;
            }

            $resource = $effect['resource_code'] ?? null;
            $delta = $effect['delta'] ?? null;

            if (!is_string($resource) || !is_int($delta) || !array_key_exists($resource, $amounts)) {
                continue;
            }

            $amounts[$resource] += $delta;
            $touched[$resource] = true;
        }

        $pending[$key] = array_merge(
            $this->queued($slice, $choices, $actionRuleCode),
            $this->attackCostOf($slice, $actionRuleCode),
        );
        if ($pending[$key] === []) {
            unset($pending[$key]);
        }

        return ['amounts' => $amounts, 'pending' => $pending, 'touched' => $touched];
    }

    /**
     * Фильтр эффекта совпал с карточкой.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param array<string, mixed> $effect Эффект.
     *
     * @return bool true, если совпал.
     */
    public function matches(CharacterRuleSlice $slice, string $actionRuleCode, array $effect): bool
    {
        $keywords = $effect['keywords'] ?? null;
        if (is_array($keywords) && $keywords !== []) {
            $rule = $slice->findLive($actionRuleCode);

            return $rule !== null && array_diff($keywords, $rule->getKeywordCodes()) === [];
        }

        return ($effect['ruleCode'] ?? null) === $actionRuleCode;
    }

    /**
     * Эффекты купленных навыков, чей фильтр совпал с этой командой.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы.
     * @param string $actionRuleCode Карточка.
     *
     * @return array<int, array<string, mixed>> Список.
     */
    private function queued(CharacterRuleSlice $slice, array $choices, string $actionRuleCode): array
    {
        $queued = [];
        $abilities = $choices['abilities'] ?? [];
        if (!is_array($abilities)) {
            return [];
        }

        foreach ($abilities as $choice) {
            $code = is_array($choice) ? ($choice['ruleCode'] ?? null) : null;
            $level = is_array($choice) ? ($choice['level'] ?? null) : null;
            if (!is_string($code) || !is_int($level) || $level < 1) {
                continue;
            }

            array_push($queued, ...$this->skillEffects($slice, $code, $actionRuleCode));
        }

        return $queued;
    }

    /**
     * next_action_cost одной способности.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $skillCode Навык.
     * @param string $actionRuleCode Карточка команды.
     *
     * @return array<int, array<string, mixed>> Строки карты.
     */
    private function skillEffects(CharacterRuleSlice $slice, string $skillCode, string $actionRuleCode): array
    {
        $spec = $slice->findLive($skillCode)?->getSpec();
        $base = null;
        $supported = $spec instanceof AbilityActionSpec
            || $spec instanceof AbilityPlainSpec
            || $spec instanceof AbilityProcessSpec
            || $spec instanceof AbilitySpellSpec;
        if ($supported) {
            $base = $spec->getBase();
        }

        if ($base === null) {
            return [];
        }

        $rows = [];
        foreach ($base->getActionEffects() as $effect) {
            if (!$effect instanceof NextActionCostEffect) {
                continue;
            }

            $stored = [
                'type' => 'next_action_cost',
                'resource_code' => $effect->getResourceCode(),
                'delta' => $effect->getDelta(),
            ];
            if ($effect->getRuleCode() !== null) {
                $stored['ruleCode'] = $effect->getRuleCode();
            }

            if ($effect->getKeywords() !== []) {
                $stored['keywords'] = $effect->getKeywords();
            }

            if ($this->matches($slice, $actionRuleCode, $stored)) {
                $rows[] = ['sourceRuleCode' => $skillCode, 'effect' => $stored];
            }
        }

        return $rows;
    }

    /**
     * next_action_attack_cost самой команды. Следующая атака снимет его.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка команды.
     *
     * @return array<int, array<string, mixed>> Строки карты.
     */
    private function attackCostOf(CharacterRuleSlice $slice, string $actionRuleCode): array
    {
        $spec = $slice->findLive($actionRuleCode)?->getSpec();
        $base = null;
        $supported = $spec instanceof AbilityActionSpec
            || $spec instanceof AbilityPlainSpec
            || $spec instanceof AbilityProcessSpec
            || $spec instanceof AbilitySpellSpec;
        if ($supported) {
            $base = $spec->getBase();
        }

        if ($base === null) {
            return [];
        }

        $rows = [];
        foreach ($base->getActionEffects() as $effect) {
            if (!$effect instanceof NextActionAttackCostEffect) {
                continue;
            }

            $rows[] = [
                'sourceRuleCode' => $actionRuleCode,
                'effect' => [
                    'type' => 'next_action_attack_cost',
                    'resource_code' => $effect->getResourceCode(),
                    'delta' => $effect->getDelta(),
                ],
            ];
        }

        return $rows;
    }
}
