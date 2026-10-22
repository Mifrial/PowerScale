<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityPlainSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityProcessSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\AfterActionUntilResourceSpentCheckModifierEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\NextActionAttackCostEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\NextActionCostEffect;

/**
 * Читает, снимает и ставит карту ожидания следующего действия.
 */
final class GamePendingActionCosts
{
    private const DEFAULT_SOURCE = 'circumstances';

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

        $declared = $this->declare($slice, $choices, $actionRuleCode, $actorKind, $actorId, $pending, $amounts, true);
        foreach ($declared['amounts'] as $code => $amount) {
            $kept[] = new ResourceSpend($code, new ScalarResourceValue($amount));
        }

        return ['spends' => $kept, 'pending' => $declared['pending']];
    }

    /**
     * Начало действия: применяет cost-эффекты, не трогая висящие проверки.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы актора.
     * @param string $actionRuleCode Карточка команды.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     * @param array<string, mixed> $pending Карта боя.
     * @param array<string, int> $amounts Цены по ресурсам.
     * @param bool $forAttack Команда — атака: применить next_action_attack_cost.
     *
     * @return array{amounts: array<string, int>, pending: array<string, mixed>, touched: array<string, bool>} Итог.
     */
    public function declare(
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
        $kept = [];
        $touched = [];
        foreach ($waiting as $entry) {
            $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
            if (!is_array($effect)) {
                $kept[] = $entry;
                continue;
            }

            $type = $effect['type'] ?? null;
            if ($type !== 'next_action_attack_cost' && $type !== 'next_action_cost') {
                $kept[] = $entry;
                continue;
            }

            if ($type === 'next_action_attack_cost' && !$forAttack) {
                continue;
            }

            if ($type === 'next_action_cost' && !$this->matches($slice, $actionRuleCode, $effect)) {
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

        $pending[$key] = $kept;
        if ($kept === []) {
            unset($pending[$key]);
        }

        return ['amounts' => $amounts, 'pending' => $pending, 'touched' => $touched];
    }

    /**
     * Конец действия: уменьшает старые висящие проверки и дописывает новые.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы актора.
     * @param string $actionRuleCode Карточка команды.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     * @param array<string, mixed> $pending Карта боя.
     * @param array<string, int> $spentAmounts Фактическая трата по ресурсам.
     *
     * @return array<string, mixed> Новая карта.
     */
    public function complete(
        CharacterRuleSlice $slice,
        array $choices,
        string $actionRuleCode,
        string $actorKind,
        int $actorId,
        array $pending,
        array $spentAmounts,
    ): array {
        $key = $actorKind . ':' . $actorId;
        $waiting = is_array($pending[$key] ?? null) ? $pending[$key] : [];
        $kept = $this->reduceAfterAction($waiting, $spentAmounts);

        array_push($kept, ...$this->afterActionOf($slice, $actionRuleCode));
        array_push($kept, ...$this->queued($slice, $choices, $actionRuleCode));
        array_push($kept, ...$this->attackCostOf($slice, $actionRuleCode));

        $pending[$key] = $kept;
        if ($kept === []) {
            unset($pending[$key]);
        }

        return $pending;
    }

    /**
     * Висящие проверки актора для одного броска.
     *
     * @param array<string, mixed> $pending Карта боя.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     * @param string $checkCode Код проверки.
     * @param string $side Сторона attacker или defender.
     *
     * @return array<int, RollAdvantage> Записи.
     */
    public function checkAdvantages(
        array $pending,
        string $actorKind,
        int $actorId,
        string $checkCode,
        string $side,
    ): array {
        $key = $actorKind . ':' . $actorId;
        $waiting = is_array($pending[$key] ?? null) ? $pending[$key] : [];
        $rows = [];
        foreach ($waiting as $entry) {
            $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
            if (!is_array($effect) || ($effect['type'] ?? null) !== 'after_action_until_resource_spent_check_modifier') {
                continue;
            }

            $advantage = $this->checkAdvantageOf($effect, $checkCode, $side);
            if ($advantage !== null) {
                $rows[] = $advantage;
            }
        }

        return $rows;
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
        if (!is_array($keywords) || $keywords === []) {
            return ($effect['ruleCode'] ?? null) === $actionRuleCode;
        }

        $rule = $slice->findLive($actionRuleCode);

        return $rule !== null && array_diff($keywords, $rule->getKeywordCodes()) === [];
    }

    /**
     * Уменьшает старые последействия на фактическую трату.
     *
     * @param array<int, mixed> $waiting Ожидание актора.
     * @param array<string, int> $spentAmounts Трата по ресурсам.
     *
     * @return array<int, mixed> Оставшиеся записи.
     */
    private function reduceAfterAction(array $waiting, array $spentAmounts): array
    {
        $kept = [];
        foreach ($waiting as $entry) {
            $keptEntry = $this->reduceAfterActionEntry($entry, $spentAmounts);
            if ($keptEntry !== null) {
                $kept[] = $keptEntry;
            }
        }

        return $kept;
    }

    /**
     * Уменьшает остаток одной записи последействия.
     *
     * @param mixed $entry Запись ожидания.
     * @param array<string, int> $spentAmounts Трата по ресурсам.
     *
     * @return mixed Запись или null, если погашена.
     */
    private function reduceAfterActionEntry(mixed $entry, array $spentAmounts): mixed
    {
        $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
        $keep = true;
        if (is_array($effect) && ($effect['type'] ?? null) === 'after_action_until_resource_spent_check_modifier') {
            $resource = $effect['resource_code'] ?? null;
            $amount = $effect['amount'] ?? null;
            $spent = is_string($resource) ? ($spentAmounts[$resource] ?? 0) : 0;
            if (is_int($amount) && is_int($spent) && $spent > 0) {
                $remaining = $amount - $spent;
                $keep = $remaining > 0;
                if ($keep) {
                    $effect['amount'] = $remaining;
                    $entry['effect'] = $effect;
                }
            }
        }

        return $keep ? $entry : null;
    }

    /**
     * Запись проверки из одного последействия.
     *
     * @param array<string, mixed> $effect Эффект.
     * @param string $checkCode Код проверки.
     * @param string $side Сторона attacker или defender.
     *
     * @return RollAdvantage|null Запись или null.
     */
    private function checkAdvantageOf(array $effect, string $checkCode, string $side): ?RollAdvantage
    {
        $codes = $effect['check_codes'] ?? null;
        $appliesTo = $effect['applies_to'] ?? null;
        $amount = $effect['amount'] ?? 0;
        $delta = $effect['delta'] ?? null;
        $skip = !is_array($codes)
            || !in_array($checkCode, $codes, true)
            || ($side === 'defender' && $appliesTo === 'attacker_hit')
            || ($side === 'attacker' && $appliesTo === 'defender_hit')
            || !is_int($amount) || $amount <= 0
            || !is_int($delta) || $delta === 0;
        if ($skip) {
            return null;
        }

        $source = $effect['source_code'] ?? null;

        return new RollAdvantage(
            is_string($source) && $source !== '' ? $source : self::DEFAULT_SOURCE,
            $delta,
        );
    }

    /**
     * Висящие проверки карточки действия.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     *
     * @return array<int, array<string, mixed>> Строки карты.
     */
    private function afterActionOf(CharacterRuleSlice $slice, string $actionRuleCode): array
    {
        $base = $this->abilityBase($slice, $actionRuleCode);
        if ($base === null) {
            return [];
        }

        $rows = [];
        foreach ($base->getActionEffects() as $effect) {
            if (!$effect instanceof AfterActionUntilResourceSpentCheckModifierEffect) {
                continue;
            }

            $rows[] = [
                'sourceRuleCode' => $actionRuleCode,
                'effect' => $this->afterActionStored($effect),
            ];
        }

        return $rows;
    }

    /**
     * Сохраняемая форма висящей проверки.
     *
     * @param AfterActionUntilResourceSpentCheckModifierEffect $effect Эффект.
     *
     * @return array<string, mixed> Строка карты.
     */
    private function afterActionStored(AfterActionUntilResourceSpentCheckModifierEffect $effect): array
    {
        $stored = [
            'type' => 'after_action_until_resource_spent_check_modifier',
            'resource_code' => $effect->getResourceCode(),
            'amount' => $effect->getAmount(),
            'check_codes' => $effect->getCheckCodes(),
            'delta' => $effect->getDelta(),
        ];
        if ($effect->getSourceCode() !== null) {
            $stored['source_code'] = $effect->getSourceCode();
        }

        if ($effect->getAppliesTo() !== null) {
            $stored['applies_to'] = $effect->getAppliesTo();
        }

        return $stored;
    }

    /**
     * База поддерживаемой карточки действия.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     *
     * @return AbilityBase|null База или null.
     */
    private function abilityBase(CharacterRuleSlice $slice, string $actionRuleCode): ?AbilityBase
    {
        $spec = $slice->findLive($actionRuleCode)?->getSpec();
        if (
            $spec instanceof AbilityActionSpec
            || $spec instanceof AbilityPlainSpec
            || $spec instanceof AbilityProcessSpec
            || $spec instanceof AbilitySpellSpec
        ) {
            return $spec->getBase();
        }

        return null;
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
