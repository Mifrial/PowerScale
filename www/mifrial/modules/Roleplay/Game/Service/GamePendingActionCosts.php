<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Mechanic\Dto\RollAdjustment;
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
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\NextActionCostEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;

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

        $kept = $this->keepSoakTail($slice, $kept, $forAttack, $amounts);
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
     * @param array<int, string> $targetKeys Цели завершённой атаки.
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
        array $targetKeys = [],
    ): array {
        $key = $actorKind . ':' . $actorId;
        $waiting = is_array($pending[$key] ?? null) ? $pending[$key] : [];
        $kept = $this->withoutAttackTails($this->reduceAfterAction($waiting, $spentAmounts));

        array_push($kept, ...$this->attackTailOf($slice, $actionRuleCode, $targetKeys));
        array_push($kept, ...$this->afterActionOf($slice, $actionRuleCode));
        array_push($kept, ...$this->queued($slice, $choices, $actionRuleCode));
        array_push($kept, ...$this->attackCostOf($slice, $actionRuleCode));
        array_push($kept, ...$this->dodgeSoakTailOf($slice, $actionRuleCode));

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
     * Хвосты прошлого действия для броска атаки.
     *
     * Берёт только записи, подходящие по компоненту, номеру попадания и цели;
     * сами записи снимает {@see self::complete()} при закрытии этого действия.
     *
     * @param array<string, mixed> $pending Карта боя.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     * @param string $profileType Тип профиля strike, throw или shoot.
     * @param array<int, string> $targetKeys Цели текущей атаки.
     * @param int $hitNumber Номер попадания с единицы.
     *
     * @return array{accuracy: int, adjustment: RollAdjustment} Итог.
     */
    public function attackAdjustment(
        array $pending,
        string $actorKind,
        int $actorId,
        string $profileType,
        array $targetKeys = [],
        int $hitNumber = 1,
    ): array {
        $key = $actorKind . ':' . $actorId;
        $waiting = is_array($pending[$key] ?? null) ? $pending[$key] : [];
        $accuracy = 0;
        $oneDelta = 0;
        $faceDelta = 0;
        foreach ($waiting as $entry) {
            $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
            if (!is_array($effect) || !$this->tailApplies($effect, $profileType, $targetKeys, $hitNumber)) {
                continue;
            }

            if (($effect['type'] ?? null) === 'next_action_attack_accuracy') {
                $delta = $effect['delta'] ?? null;
                if (is_int($delta)) {
                    $accuracy += $delta;
                }

                continue;
            }

            $one = $effect['oneDelta'] ?? null;
            $face = $effect['faceDelta'] ?? null;
            if (is_int($one)) {
                $oneDelta += $one;
            }

            if (is_int($face)) {
                $faceDelta += $face;
            }
        }

        return [
            'accuracy' => $accuracy,
            'adjustment' => new RollAdjustment([], $oneDelta, $faceDelta),
        ];
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
     * Снимает хвосты прошлого действия: их поглощает текущее действие.
     *
     * Снимок прошлого удара тоже живёт до следующего действия; закрытие удара
     * положит новый снимок после этой очистки.
     *
     * @param array<int, mixed> $waiting Ожидание актора.
     *
     * @return array<int, mixed> Оставшиеся записи.
     */
    private function withoutAttackTails(array $waiting): array
    {
        return array_values(array_filter(
            $waiting,
            static function (mixed $entry): bool {
                $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
                $type = is_array($effect) ? ($effect['type'] ?? null) : null;

                return $type !== 'next_action_attack_accuracy'
                    && $type !== 'next_action_attack_score_adjust'
                    && $type !== 'next_action_attack_dodge_soak_from_reaction'
                    && $type !== 'last_strike_snapshot';
            },
        ));
    }

    /**
     * Подходит ли хвост под текущий бросок.
     *
     * @param array<string, mixed> $effect Эффект хвоста.
     * @param string $profileType Тип профиля.
     * @param array<int, string> $targetKeys Цели атаки.
     * @param int $hitNumber Номер попадания.
     *
     * @return bool true, если подходит.
     */
    private function tailApplies(array $effect, string $profileType, array $targetKeys, int $hitNumber): bool
    {
        $type = $effect['type'] ?? null;
        if ($type !== 'next_action_attack_accuracy' && $type !== 'next_action_attack_score_adjust') {
            return false;
        }

        if (($effect['same_target'] ?? false) === true) {
            $targetKey = $effect['targetKey'] ?? null;
            if (!is_string($targetKey) || !in_array($targetKey, $targetKeys, true)) {
                return false;
            }
        }

        if ($type !== 'next_action_attack_accuracy') {
            return true;
        }

        $scope = is_array($effect['scope'] ?? null) ? $effect['scope'] : [];
        $components = is_array($scope['components'] ?? null) ? $scope['components'] : [];
        if (!in_array($profileType, $components, true)) {
            return false;
        }

        $hitCount = $scope['hit_count'] ?? 1;

        return $hitCount === 'all' || (is_int($hitCount) && $hitNumber <= $hitCount);
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
    private function attackTailOf(CharacterRuleSlice $slice, string $actionRuleCode, array $targetKeys): array
    {
        $base = $this->abilityBase($slice, $actionRuleCode);
        if ($base === null) {
            return [];
        }

        $rows = [];
        foreach ($base->getActionEffects() as $effect) {
            if ($effect instanceof NextActionAttackAccuracyEffect) {
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

                array_push($rows, ...$this->tailRows($actionRuleCode, $stored, $effect->isSameTarget(), $targetKeys));
                continue;
            }

            if ($effect instanceof NextActionAttackScoreAdjustEffect) {
                $stored = [
                    'type' => 'next_action_attack_score_adjust',
                    'oneDelta' => $effect->getOneDelta(),
                    'faceDelta' => $effect->getFaceDelta(),
                    'same_target' => $effect->isSameTarget(),
                ];
                if ($effect->getTargetKey() !== null) {
                    $stored['targetKey'] = $effect->getTargetKey();
                }

                array_push($rows, ...$this->tailRows($actionRuleCode, $stored, $effect->isSameTarget(), $targetKeys));
            }
        }

        return $rows;
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
            if (!is_string($targetKey) || $targetKey === '') {
                continue;
            }

            $stored['targetKey'] = $targetKey;
            $rows[] = ['sourceRuleCode' => $actionRuleCode, 'effect' => $stored];
        }

        return $rows;
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
     * Хвост смягчения от Реакции, подготовленный карточкой.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     *
     * @return array<int, array<string, mixed>> Строки карты.
     */
    private function dodgeSoakTailOf(CharacterRuleSlice $slice, string $actionRuleCode): array
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
    /**
     * Оставляет хвост смягчения только для атаки, уложившейся в потолок ОД.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<int, mixed> $entries Записи после cost-эффектов.
     * @param bool $forAttack Команда — атака.
     * @param array<string, int> $amounts Итоговая цена по ресурсам.
     *
     * @return array<int, mixed> Записи.
     */
    private function keepSoakTail(
        CharacterRuleSlice $slice,
        array $entries,
        bool $forAttack,
        array $amounts,
    ): array {
        $cost = $this->actionCost($slice, $amounts);
        $kept = [];
        foreach ($entries as $entry) {
            if (!$this->isSoakTail($entry)) {
                $kept[] = $entry;
                continue;
            }

            $effect = is_array($entry['effect'] ?? null) ? $entry['effect'] : [];
            if ($forAttack && $this->soakTailFits($effect, $cost)) {
                $entry['effect']['armed'] = true;
                $kept[] = $entry;
            }
        }

        return $kept;
    }

    /**
     * Запись карты — хвост смягчения от Реакции.
     *
     * @param mixed $entry Запись.
     *
     * @return bool true, если хвост.
     */
    private function isSoakTail(mixed $entry): bool
    {
        $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;

        return is_array($effect) && ($effect['type'] ?? null) === 'next_action_attack_dodge_soak_from_reaction';
    }

    /**
     * Цена атаки не превышает потолок хвоста.
     *
     * @param array<string, mixed> $effect Эффект.
     * @param int $cost Цена в ОД.
     *
     * @return bool true, если хвост применяется.
     */
    private function soakTailFits(array $effect, int $cost): bool
    {
        $max = $effect['max_total_action_cost'] ?? null;

        return !is_int($max) || $cost <= $max;
    }

    /**
     * Фактическая цена действия в ОД после cost-эффектов.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, int> $amounts Цена по ресурсам.
     *
     * @return int Очки действия.
     */
    private function actionCost(CharacterRuleSlice $slice, array $amounts): int
    {
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof ResourceSpec && $spec->isTurnRefill()) {
                $value = $amounts[$rule->getCode()] ?? null;

                return is_int($value) ? $value : 0;
            }
        }

        $fallback = $amounts['action-points'] ?? null;

        return is_int($fallback) ? $fallback : 0;
    }

}
