<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Mechanic\Constant\AdvantageSourceLabels;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityPlainSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityProcessSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\AfterActionUntilResourceSpentCheckModifierEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\NextActionAttackAccuracyEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\NextActionAttackCostEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\NextActionAttackDodgeSoakFromReactionEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\NextActionAttackScoreAdjustEffect;

/**
 * Новые хвосты ожидания действия из карточек.
 */
final class GamePendingActionTails
{
    /**
     * Запись проверки из одного последействия.
     *
     * @param array<string, mixed> $effect Эффект.
     * @param string $checkCode Код проверки.
     * @param string $side Сторона attacker или defender.
     *
     * @return RollAdvantage|null Запись или null.
     */
    public function checkAdvantageOf(array $effect, string $checkCode, string $side): ?RollAdvantage
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
        $source = is_string($source) && $source !== '' ? $source : null;

        return new RollAdvantage(
            $source,
            $delta,
            AdvantageSourceLabels::getLabel($source),
        );
    }

    /**
     * Хвосты следующего действия из карточки.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param array<int, string> $targetKeys Цели закрытой атаки.
     *
     * @return array<int, array<string, mixed>> Строки карты.
     */
    public function attackTailOf(CharacterRuleSlice $slice, string $actionRuleCode, array $targetKeys): array
    {
        $base = $this->abilityBase($slice, $actionRuleCode);
        if ($base === null) {
            return [];
        }

        $rows = [];
        foreach ($base->getActionEffects() as $effect) {
            array_push($rows, ...$this->tailOf($actionRuleCode, $effect, $targetKeys));
        }

        return $rows;
    }

    /**
     * Висящие проверки карточки действия.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     *
     * @return array<int, array<string, mixed>> Строки карты.
     */
    public function afterActionOf(CharacterRuleSlice $slice, string $actionRuleCode): array
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
    public function afterActionStored(AfterActionUntilResourceSpentCheckModifierEffect $effect): array
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
     * Хвост смягчения от Реакции, подготовленный карточкой.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     *
     * @return array<int, array<string, mixed>> Строки карты.
     */
    public function dodgeSoakTailOf(CharacterRuleSlice $slice, string $actionRuleCode): array
    {
        $base = $this->abilityBase($slice, $actionRuleCode);
        if ($base === null) {
            return [];
        }

        $rows = [];
        foreach ($base->getActionEffects() as $effect) {
            if (!$effect instanceof NextActionAttackDodgeSoakFromReactionEffect) {
                continue;
            }

            $rows[] = [
                'sourceRuleCode' => $actionRuleCode,
                'effect' => [
                    'type' => 'next_action_attack_dodge_soak_from_reaction',
                    'max_total_action_cost' => $effect->getMaxTotalActionCost(),
                    'scope' => [
                        'components' => array_values($effect->getScope()->getComponents()),
                        'hit_count' => $effect->getScope()->getHitCount(),
                    ],
                ],
            ];
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
    public function attackCostOf(CharacterRuleSlice $slice, string $actionRuleCode): array
    {
        $base = $this->abilityBase($slice, $actionRuleCode);
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

    /**
     * База поддерживаемой карточки действия.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     *
     * @return AbilityBase|null База или null.
     */
    public function abilityBase(CharacterRuleSlice $slice, string $actionRuleCode): ?AbilityBase
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
     * Хвост одного эффекта карточки.
     *
     * @param string $actionRuleCode Карточка.
     * @param mixed $effect Эффект карточки.
     * @param array<int, string> $targetKeys Цели закрытой атаки.
     *
     * @return array<int, array<string, mixed>> Строки карты.
     */
    private function tailOf(string $actionRuleCode, mixed $effect, array $targetKeys): array
    {
        if ($effect instanceof NextActionAttackAccuracyEffect) {
            return $this->tailRows($actionRuleCode, $this->accuracyStored($effect), $effect->isSameTarget(), $targetKeys);
        }

        if ($effect instanceof NextActionAttackScoreAdjustEffect) {
            return $this->tailRows($actionRuleCode, $this->scoreStored($effect), $effect->isSameTarget(), $targetKeys);
        }

        return [];
    }

    /**
     * Хранимая форма поправки точности.
     *
     * @param NextActionAttackAccuracyEffect $effect Эффект.
     *
     * @return array<string, mixed> Строка.
     */
    private function accuracyStored(NextActionAttackAccuracyEffect $effect): array
    {
        $stored = [
            'type' => 'next_action_attack_accuracy',
            'delta' => $effect->getDelta(),
            'same_target' => $effect->isSameTarget(),
            'scope' => [
                'components' => array_values($effect->getScope()->getComponents()),
                'hit_count' => $effect->getScope()->getHitCount(),
            ],
        ];
        if ($effect->getTargetKey() !== null) {
            $stored['targetKey'] = $effect->getTargetKey();
        }

        return $stored;
    }

    /**
     * Хранимая форма поправки успехов.
     *
     * @param NextActionAttackScoreAdjustEffect $effect Эффект.
     *
     * @return array<string, mixed> Строка.
     */
    private function scoreStored(NextActionAttackScoreAdjustEffect $effect): array
    {
        $stored = [
            'type' => 'next_action_attack_score_adjust',
            'oneDelta' => $effect->getOneDelta(),
            'faceDelta' => $effect->getFaceDelta(),
            'same_target' => $effect->isSameTarget(),
        ];
        if ($effect->getTargetKey() !== null) {
            $stored['targetKey'] = $effect->getTargetKey();
        }

        return $stored;
    }

    /**
     * Строки хвоста: по одной на цель, если нужна та же цель.
     *
     * @param string $actionRuleCode Карточка.
     * @param array<string, mixed> $stored Эффект.
     * @param bool $sameTarget Нужна та же цель.
     * @param array<int, string> $targetKeys Цели закрытой атаки.
     *
     * @return array<int, array<string, mixed>> Строки карты.
     */
    private function tailRows(string $actionRuleCode, array $stored, bool $sameTarget, array $targetKeys): array
    {
        if (!$sameTarget) {
            return [['sourceRuleCode' => $actionRuleCode, 'effect' => $stored]];
        }

        $rows = [];
        foreach ($targetKeys as $targetKey) {
            if ($targetKey === '') {
                continue;
            }

            $stored['targetKey'] = $targetKey;
            $rows[] = ['sourceRuleCode' => $actionRuleCode, 'effect' => $stored];
        }

        return $rows;
    }
}
