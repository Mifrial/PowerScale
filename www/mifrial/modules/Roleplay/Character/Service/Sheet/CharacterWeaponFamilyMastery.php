<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityBase;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\CharacteristicSpec;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Мастерство семейств оружия по характеристикам боя из спек.
 */
final class CharacterWeaponFamilyMastery
{
    /**
     * Собирает расчёт.
     *
     * @param CharacterEquippedLive $equipped Надетые и врождённые предметы.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterEquippedLive $equipped,
    ) {
    }

    /**
     * Карта семья → профиль характеристики → значение.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, CharacteristicNumber> $characteristics Живые характеристики.
     *
     * @return array<string, array<string, array{base: int, size: int}>> Карта.
     *
     * @throws CharacterInvalidException Если один профиль сидит на двух характеристиках.
     */
    public function compute(CharacterRuleSlice $slice, CharacterChoices $choices, array $characteristics): array
    {
        $profiles = $this->profiles($slice, $characteristics);
        $levels = $this->levels($slice, $choices);
        $map = [];
        foreach ($levels as $familyCode => $level) {
            if ($level === 0) {
                continue;
            }

            $map[$familyCode] = [];
            foreach ($profiles as $profileType => $value) {
                $shifted = $value->modify($level);
                $map[$familyCode][$profileType] = [
                    'base' => $shifted->getBase(),
                    'size' => $shifted->getSize(),
                ];
            }
        }

        return $map;
    }

    /**
     * Профиль мастерства → живая характеристика, по полю спеки.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, CharacteristicNumber> $characteristics Живые характеристики.
     *
     * @return array<string, CharacteristicNumber> Профиль → значение.
     *
     * @throws CharacterInvalidException Если профиль неоднозначен.
     */
    private function profiles(CharacterRuleSlice $slice, array $characteristics): array
    {
        $profiles = [];
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $this->masterySpec($rule);
            $value = $characteristics[$rule->getCode()] ?? null;
            if ($spec === null || !$value instanceof CharacteristicNumber) {
                continue;
            }

            $this->addProfiles($profiles, $spec, $value);
        }

        return $profiles;
    }

    /**
     * Спека с профилями мастерства.
     *
     * @param CharacterResolvedRule $rule Правило.
     *
     * @return CharacteristicSpec|null Спека или null.
     */
    private function masterySpec(CharacterResolvedRule $rule): ?CharacteristicSpec
    {
        if ($rule->isSpecBroken()) {
            return null;
        }

        $spec = $rule->getSpec();

        return $spec instanceof CharacteristicSpec ? $spec : null;
    }

    /**
     * Раскладывает профили одной характеристики.
     *
     * @param array<string, CharacteristicNumber> $profiles Профиль → значение.
     * @param CharacteristicSpec $spec Спека.
     * @param CharacteristicNumber $value Значение.
     *
     * @return void
     *
     * @throws CharacterInvalidException Если профиль уже занят.
     */
    private function addProfiles(array &$profiles, CharacteristicSpec $spec, CharacteristicNumber $value): void
    {
        foreach ($spec->getWeaponMasteryProfiles() as $profileType) {
            if (isset($profiles[$profileType])) {
                throw new CharacterInvalidException('Character weapon mastery profile is ambiguous');
            }

            $profiles[$profileType] = $value;
        }
    }

    /**
     * Уровень семьи: купленный домен и пол 1 от врождённого предмета.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     *
     * @return array<string, int> Код семьи → уровень.
     */
    private function levels(CharacterRuleSlice $slice, CharacterChoices $choices): array
    {
        $levels = [];
        foreach ($this->equipped->equippedOrInnate($slice, $choices) as $row) {
            $spec = $row['spec'];

            $familyCode = $spec->getProficiencyFamilyCode();
            if ($familyCode === null || $familyCode === '') {
                continue;
            }

            $level = $this->purchased($slice, $choices, $familyCode);
            if ($spec->isInnate()) {
                $level = max($level, 1);
            }

            $levels[$familyCode] = max($levels[$familyCode] ?? 0, $level);
        }

        return $levels;
    }

    /**
     * Купленный уровень домена weapon-family для семьи.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param string $familyCode Код семьи.
     *
     * @return int Уровень.
     */
    private function purchased(CharacterRuleSlice $slice, CharacterChoices $choices, string $familyCode): int
    {
        $level = 0;
        foreach ($choices->getAbilities() as $ability) {
            if (!$this->matchesFamily($slice, $ability, $familyCode)) {
                continue;
            }

            if ($ability->getLevel() > $level) {
                $level = $ability->getLevel();
            }
        }

        return $level;
    }

    /**
     * Выбор ссылается на семью через справочник домена.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterAbilityChoice $ability Выбор.
     * @param string $familyCode Код семьи.
     *
     * @return bool true, если это владение этой семьёй.
     */
    private function matchesFamily(CharacterRuleSlice $slice, CharacterAbilityChoice $ability, string $familyCode): bool
    {
        $rule = $slice->findLive($ability->getRuleCode());
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        $base = CharacterAbilityBase::of($spec);
        if ($base === null || $base->getDomainRef() !== 'weapon-family') {
            return false;
        }

        $domain = $ability->getDomainCode() !== '' ? $ability->getDomainCode() : $ability->getDomain();

        return $domain === $familyCode;
    }
}
