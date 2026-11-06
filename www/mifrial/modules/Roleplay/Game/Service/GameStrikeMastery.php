<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\CharacteristicSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponBlock;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Мастерство оружия: характеристика профиля и сохранённое семейство.
 */
final class GameStrikeMastery
{
    /**
     * Принимает срезы, участников и снаряжение.
     *
     * @param GameStrikeParticipants $participants Участники удара.
     * @param GameStrikeEquipment $equipment Снаряжение удара.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeParticipants $participants,
        private readonly GameStrikeEquipment $equipment,
    ) {
    }

    /**
     * Лучшее мастерство носимого оружия этого профиля.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы.
     * @param string $profileType Тип профиля.
     * @param string|null $participantKey Ключ листа защитника.
     *
     * @return DimensionalNumber|null Лучшее мастерство или null.
     *
     * @throws GameInvalidException Если characteristic или domain неоднозначны.
     */
    public function bestEquipped(
        CharacterRuleSlice $slice,
        array $sheet,
        array $choices,
        string $profileType,
        ?string $participantKey = null,
    ): ?DimensionalNumber {
        $best = $this->pickBest($slice, $sheet, $choices, $profileType, $participantKey);
        if ($best instanceof DimensionalNumber) {
            return $best;
        }

        return $this->participants->formulaContext($slice, $sheet, $choices, $participantKey)
            ->findCharacteristic($this->combatCharacteristic($slice, $profileType));
    }

    /**
     * Мастерство оружия семейства и профиля.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы.
     * @param string|null $familyCode Семейство.
     * @param string $profileType Тип профиля.
     * @param string|null $participantKey Ключ листа.
     *
     * @return DimensionalNumber Мастерство.
     *
     * @throws GameInvalidException Если характеристика или семейство битые.
     */
    public function of(
        CharacterRuleSlice $slice,
        array $sheet,
        array $choices,
        ?string $familyCode,
        string $profileType,
        ?string $participantKey = null,
    ): DimensionalNumber {
        if (is_array($sheet['sheet'] ?? null) && is_array($sheet['choices'] ?? null)) {
            $sheet = $sheet['sheet'];
        }

        if ($familyCode === null || $familyCode === '') {
            throw new GameInvalidException('Game strike weapon family is invalid');
        }

        $characteristicCode = $this->combatCharacteristic($slice, $profileType);
        $derived = $this->participants->formulaContext($slice, $sheet, $choices, $participantKey)
            ->findCharacteristic($characteristicCode);
        if (!$derived instanceof DimensionalNumber) {
            throw new GameInvalidException('Game strike characteristic is invalid');
        }

        $stored = $this->stored($slice, $sheet, $choices, $familyCode, $profileType);
        if ($stored instanceof CharacteristicNumber) {
            return $stored;
        }

        return new CharacteristicNumber($derived->getBase(), $derived->getSize());
    }

    /**
     * Лучшее мастерство среди носимого оружия.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы.
     * @param string $profileType Тип профиля.
     * @param string|null $participantKey Ключ листа.
     *
     * @return DimensionalNumber|null Мастерство или null.
     *
     * @throws GameInvalidException Если характеристика или семейство битые.
     */
    private function pickBest(
        CharacterRuleSlice $slice,
        array $sheet,
        array $choices,
        string $profileType,
        ?string $participantKey,
    ): ?DimensionalNumber {
        $best = null;
        foreach ($this->inventory($choices) as $row) {
            $item = $this->equippedWeapon($slice, $row, $profileType);
            if ($item === null) {
                continue;
            }

            $mastery = $this->of(
                $slice,
                $sheet,
                $choices,
                $item->getProficiencyFamilyCode(),
                $profileType,
                $participantKey,
            );
            if ($best === null || $mastery->compare($best) > 0) {
                $best = $mastery;
            }
        }

        return $best;
    }

    /**
     * Сохранённое мастерство семейства.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы.
     * @param string $familyCode Семейство.
     * @param string $profileType Тип профиля.
     *
     * @return CharacteristicNumber|null Мастерство или null.
     */
    private function stored(
        CharacterRuleSlice $slice,
        array $sheet,
        array $choices,
        string $familyCode,
        string $profileType,
    ): ?CharacteristicNumber {
        $computed = $this->participants->computedSheet($slice, $sheet, $choices);
        $stored = $computed['weaponFamilyMastery'][$familyCode][$profileType] ?? null;
        if (is_array($stored) && is_int($stored['base'] ?? null) && is_int($stored['size'] ?? null)) {
            return new CharacteristicNumber($stored['base'], $stored['size']);
        }

        return null;
    }

    /**
     * Инвентарь из выборов.
     *
     * @param array<string, mixed> $choices Выборы.
     *
     * @return array<int, mixed> Строки.
     */
    private function inventory(array $choices): array
    {
        $inventory = $choices['inventory'] ?? [];

        return is_array($inventory) ? $inventory : [];
    }

    /**
     * Носимое оружие строки с нужным профилем.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param mixed $row Строка инвентаря.
     * @param string $profileType Тип профиля.
     *
     * @return ItemSpec|null Предмет или null.
     */
    private function equippedWeapon(CharacterRuleSlice $slice, mixed $row, string $profileType): ?ItemSpec
    {
        if (!is_array($row) || !is_string($row['ruleCode'] ?? null)) {
            return null;
        }

        $rule = $slice->findLive($row['ruleCode']);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        $valid = $spec instanceof ItemSpec
            && $this->equipment->isCarried($row, $spec)
            && $this->hasProfile($spec, $profileType)
            && $spec->getProficiencyFamilyCode() !== null;

        return $valid ? $spec : null;
    }

    /**
     * У предмета есть оружие с нужным профилем.
     *
     * @param ItemSpec $item Предмет.
     * @param string $profileType Тип профиля.
     *
     * @return bool true, если есть.
     */
    private function hasProfile(ItemSpec $item, string $profileType): bool
    {
        $weapon = $item->getWeapon();

        return $weapon instanceof WeaponBlock && $this->matches($weapon, $profileType);
    }

    /**
     * У оружия есть профиль нужного типа.
     *
     * @param WeaponBlock $weapon Оружие.
     * @param string $profileType Тип профиля.
     *
     * @return bool true, если есть.
     */
    private function matches(WeaponBlock $weapon, string $profileType): bool
    {
        foreach ($weapon->getWeaponProfiles() as $profile) {
            if ($profile->getType() === $profileType) {
                return true;
            }
        }

        return false;
    }

    /**
     * Характеристика мастерства для профиля.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $profileType Тип профиля.
     *
     * @return string Код характеристики.
     *
     * @throws GameInvalidException Если характеристика неоднозначна или её нет.
     */
    private function combatCharacteristic(CharacterRuleSlice $slice, string $profileType): string
    {
        $matched = null;
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof CharacteristicSpec && in_array($profileType, $spec->getWeaponMasteryProfiles(), true)) {
                if ($matched !== null) {
                    throw new GameInvalidException('Game strike characteristic is ambiguous');
                }

                $matched = $rule->getCode();
            }
        }

        if ($matched === null) {
            throw new GameInvalidException('Game strike characteristic is invalid');
        }

        return $matched;
    }
}
