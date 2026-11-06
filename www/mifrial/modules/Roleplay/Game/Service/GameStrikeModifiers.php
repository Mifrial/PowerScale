<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityPlainSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionAttackCharacteristicModifierEffect;

/**
 * Модификаторы удара: проверочные преимущества и сдвиги характеристик.
 */
final class GameStrikeModifiers
{
    /**
     * Принимает модификатор успеха.
     *
     * @param GameStrikeSuccessModifier $successModifier Модификатор успеха.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeSuccessModifier $successModifier,
    ) {
    }

    /**
     * Модификаторы проверки с пассивных карточек.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, int> $levels Уровни способностей актора.
     * @param string $checkCode Код проверки.
     *
     * @return array<int, RollAdvantage> Записи.
     */
    public function passiveCheckAdvantages(CharacterRuleSlice $slice, array $levels, string $checkCode): array
    {
        $rows = [];
        foreach ($slice->getLiveRules() as $rule) {
            $base = $this->passiveBase($rule, $levels);
            if ($base === null) {
                continue;
            }

            array_push($rows, ...$this->advantagesOf($base, $checkCode));
        }

        return $rows;
    }
    /**
     * Текущий сдвиг характеристики атаки карточки действия.
     *
     * Учитывает эффекты самой карточки и её дочерних навыков
     * (`parent_ability_code`) с уровнем ≥ 1.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param string $profileType Тип профиля strike, throw или shoot.
     * @param int $hitNumber Номер попадания.
     * @param array{damageTypeCode?: string|null, occupyHands?: int} $attackContext
     *        Контекст профиля: тип урона и занятые оружием руки.
     * @param array<string, int> $abilityLevels Уровни способностей актора.
     *
     * @return int Сдвиг.
     */
    public function actionCharacteristicModifier(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        string $profileType,
        int $hitNumber,
        array $attackContext = [],
        array $abilityLevels = [],
    ): int {
        return $this->netSourceDeltas(
            $this->actionCharacteristicDeltas(
                $slice,
                $actionRuleCode,
                $profileType,
                $hitNumber,
                $attackContext,
                $abilityLevels,
            ),
        );
    }
    /**
     * Сдвиг характеристики атаки с бонусом от рейтинга успеха.
     *
     * Складывает по правилу source: от одного источника остаются сильнейший
     * бонус и сильнейший штраф, разные источники суммируются.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param string $profileType Тип профиля strike, throw или shoot.
     * @param int $hitNumber Номер попадания.
     * @param array{damageTypeCode?: string|null, occupyHands?: int} $attackContext
     *        Контекст профиля: тип урона и занятые оружием руки.
     * @param array<string, int> $abilityLevels Уровни способностей актора.
     * @param int|null $success Рейтинг успеха или null.
     *
     * @return int Сдвиг.
     */
    public function attackCharacteristicModifier(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        string $profileType,
        int $hitNumber,
        array $attackContext = [],
        array $abilityLevels = [],
        ?int $success = null,
    ): int {
        $entries = $this->actionCharacteristicDeltas(
            $slice,
            $actionRuleCode,
            $profileType,
            $hitNumber,
            $attackContext,
            $abilityLevels,
        );
        if ($success !== null) {
            array_push(
                $entries,
                ...$this->successEntries($slice, $actionRuleCode, $profileType, $hitNumber, $success),
            );
        }

        return $this->netSourceDeltas($entries);
    }
    /**
     * Сдвиг характеристики урона от рейтинга успеха карточки действия.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param string $profileType Тип профиля strike, throw или shoot.
     * @param int $hitNumber Номер попадания.
     * @param int $success Рейтинг успеха.
     *
     * @return int Сдвиг.
     */
    public function successRatingCharacteristicModifier(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        string $profileType,
        int $hitNumber,
        int $success,
    ): int {
        $base = GameStrikeRuleLookup::actionBase($slice, $actionRuleCode);
        if ($base === null) {
            return 0;
        }

        return $this->netSourceDeltas(
            $this->successModifier->entriesFrom($base->getActionEffects(), $profileType, $hitNumber, $success),
        );
    }
    /**
     * База пассивной карточки с уровнем ≥ 1.
     *
     * @param CharacterResolvedRule $rule Правило.
     * @param array<string, int> $levels Уровни способностей.
     *
     * @return AbilityBase|null База или null.
     */
    private function passiveBase(CharacterResolvedRule $rule, array $levels): ?AbilityBase
    {
        if (($levels[$rule->getCode()] ?? 0) < 1) {
            return null;
        }

        $spec = $rule->getSpec();

        return $spec instanceof AbilityPlainSpec ? $spec->getBase() : null;
    }

    /**
     * Преимущества эффектов одной базы.
     *
     * @param AbilityBase $base База.
     * @param string $checkCode Код проверки.
     *
     * @return array<int, RollAdvantage> Записи.
     */
    private function advantagesOf(AbilityBase $base, string $checkCode): array
    {
        $rows = [];
        foreach ($base->getActionEffects() as $effect) {
            $row = GameStrikeCheckAdvantage::of($effect, $checkCode);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Вклады «силы удара» карточки и её детей с источниками.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param string $profileType Тип профиля strike, throw или shoot.
     * @param int $hitNumber Номер попадания.
     * @param array{damageTypeCode?: string|null, occupyHands?: int} $attackContext
     *        Контекст профиля: тип урона и занятые оружием руки.
     * @param array<string, int> $abilityLevels Уровни способностей актора.
     *
     * @return list<array{source: string|null, delta: int}> Вклады.
     */
    private function actionCharacteristicDeltas(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        string $profileType,
        int $hitNumber,
        array $attackContext = [],
        array $abilityLevels = [],
    ): array {
        $base = GameStrikeRuleLookup::actionBase($slice, $actionRuleCode);
        if ($base === null) {
            return [];
        }

        return $this->effectDeltas(
            $slice,
            $actionRuleCode,
            $base,
            $profileType,
            $hitNumber,
            $attackContext,
            $abilityLevels,
        );
    }
    /**
     * Нетто вкладов: один источник — сильнейший плюс и сильнейший минус.
     *
     * @param list<array{source: string|null, delta: int}> $entries Вклады.
     *
     * @return int Сумма.
     */
    private function netSourceDeltas(array $entries): int
    {
        $net = 0;
        foreach ($this->groups($entries) as $deltas) {
            $net += $this->netOf($deltas);
        }

        return $net;
    }

    /**
     * Вклады успеха карточки.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param string $profileType Тип профиля.
     * @param int $hitNumber Номер попадания.
     * @param int $success Рейтинг успеха.
     *
     * @return list<array{source: string|null, delta: int}> Вклады.
     */
    private function successEntries(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        string $profileType,
        int $hitNumber,
        int $success,
    ): array {
        $base = GameStrikeRuleLookup::actionBase($slice, $actionRuleCode);

        return $base === null
            ? []
            : $this->successModifier->entriesFrom($base->getActionEffects(), $profileType, $hitNumber, $success);
    }

    /**
     * Вклады эффектов карточки и её детей.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param AbilityBase $base База карточки.
     * @param string $profileType Тип профиля.
     * @param int $hitNumber Номер попадания.
     * @param array{damageTypeCode?: string|null, occupyHands?: int} $attackContext Контекст.
     * @param array<string, int> $abilityLevels Уровни способностей.
     *
     * @return list<array{source: string|null, delta: int}> Вклады.
     */
    private function effectDeltas(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        AbilityBase $base,
        string $profileType,
        int $hitNumber,
        array $attackContext,
        array $abilityLevels,
    ): array {
        $damageType = $attackContext['damageTypeCode'] ?? null;
        $damageType = $damageType !== '' ? $damageType : null;
        $occupyHands = $attackContext['occupyHands'] ?? 0;

        $entries = [];
        foreach ($this->actionEffectBases($slice, $actionRuleCode, $base, $abilityLevels) as $current) {
            foreach ($current->getActionEffects() as $effect) {
                $entry = $this->effectEntry($effect, $profileType, $hitNumber, $damageType, $occupyHands);
                if ($entry !== null) {
                    $entries[] = $entry;
                }
            }
        }

        return $entries;
    }

    /**
     * Вклад одного эффекта характеристики атаки.
     *
     * @param mixed $effect Эффект.
     * @param string $profileType Тип профиля.
     * @param int $hitNumber Номер попадания.
     * @param string|null $damageType Тип урона.
     * @param int $occupyHands Занятые руки.
     *
     * @return array{source: string|null, delta: int}|null Вклад или null.
     */
    private function effectEntry(
        mixed $effect,
        string $profileType,
        int $hitNumber,
        ?string $damageType,
        int $occupyHands,
    ): ?array {
        if (!$effect instanceof CurrentActionAttackCharacteristicModifierEffect) {
            return null;
        }

        $types = $effect->getDamageTypeCodes();
        $damageOk = $types === [] || ($damageType !== null && in_array($damageType, $types, true));
        $minHands = $effect->getMinOccupyHands();
        $handsOk = $minHands === null || $occupyHands >= $minHands;
        $applies = $effect->getScope()->appliesTo($profileType, $hitNumber) && $damageOk && $handsOk;

        return $applies ? ['source' => $effect->getSourceCode(), 'delta' => $effect->getDelta()] : null;
    }

    /**
     * Группы вкладов по источнику.
     *
     * @param list<array{source: string|null, delta: int}> $entries Вклады.
     *
     * @return array<string, list<int>> Группы.
     */
    private function groups(array $entries): array
    {
        $groups = [];
        foreach ($entries as $index => $entry) {
            if ($entry['delta'] === 0) {
                continue;
            }

            $key = $entry['source'] ?? "\0" . $index;
            $groups[$key][] = $entry['delta'];
        }

        return $groups;
    }

    /**
     * Нетто одной группы: сильнейший плюс и сильнейший минус.
     *
     * @param list<int> $deltas Вклады группы.
     *
     * @return int Нетто.
     */
    private function netOf(array $deltas): int
    {
        $positive = array_filter($deltas, static fn (int $delta): bool => $delta > 0);
        $negative = array_filter($deltas, static fn (int $delta): bool => $delta < 0);

        return max([0, ...$positive]) + min([0, ...$negative]);
    }

    /**
     * Базы карточки действия и купленных дочерних навыков.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param AbilityBase $base База карточки.
     * @param array<string, int> $abilityLevels Уровни способностей.
     *
     * @return array<int, AbilityBase> Базы.
     */
    private function actionEffectBases(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        AbilityBase $base,
        array $abilityLevels,
    ): array {
        $bases = [$base];
        foreach ($slice->getLiveRules() as $rule) {
            $level = $abilityLevels[$rule->getCode()] ?? 0;
            if ($level < 1) {
                continue;
            }

            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if (!$spec instanceof AbilityActionSpec && !$spec instanceof AbilityPlainSpec) {
                continue;
            }

            $child = $spec->getBase();
            if ($child->getParentAbilityCode() !== $actionRuleCode) {
                continue;
            }

            $bases[] = $child;
        }

        return $bases;
    }
}
