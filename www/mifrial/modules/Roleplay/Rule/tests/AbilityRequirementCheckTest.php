<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\AndRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\CharacteristicValueRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\CurrentSpeedRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\HasAbilityKeywordRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\HasAbilityRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\HasKeywordRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\HasMagicPathRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\MagicPathExperienceRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\MinWeaponMasteryRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\OrRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\ResourceLimitRequirement;
use Mifrial\Roleplay\Rule\Service\AbilityRequirementCheck;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use Mifrial\Roleplay\Rule\Value\RequirementNumbers;
use Mifrial\Roleplay\Rule\Value\RequirementTraits;
use Mifrial\Roleplay\Rule\Value\RequirementUnmodelled;
use Mifrial\Roleplay\Rule\Value\RequirementValues;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет трёхзначный оценщик требований способностей.
 */
final class AbilityRequirementCheckTest extends TestCase
{
    /**
     * has_ability: минимум по умолчанию и явный.
     *
     * @return void
     */
    public function testHasAbilityLevels(): void
    {
        $check = new AbilityRequirementCheck();
        $values = new RequirementValues(new RequirementNumbers(['swing' => 2]));

        self::assertTrue($check->check(new HasAbilityRequirement('swing', null), $values));
        self::assertTrue($check->check(new HasAbilityRequirement('swing', 2), $values));
        self::assertFalse($check->check(new HasAbilityRequirement('swing', 3), $values));
        self::assertFalse($check->check(new HasAbilityRequirement('missing', null), $values));
    }

    /**
     * characteristic_value сравнивает размерные числа.
     *
     * @return void
     */
    public function testCharacteristicValue(): void
    {
        $check = new AbilityRequirementCheck();
        $values = new RequirementValues(new RequirementNumbers(characteristics: ['strength' => new DimensionalNumber(4, 1)]));

        self::assertTrue($check->check(new CharacteristicValueRequirement('strength', new DimensionalNumber(4, 0)), $values));
        self::assertFalse($check->check(new CharacteristicValueRequirement('strength', new DimensionalNumber(4, 2)), $values));
        self::assertFalse($check->check(new CharacteristicValueRequirement('agility', new DimensionalNumber(1, 0)), $values));
    }

    /**
     * resource_limit требует наличия ресурса; без min достаточно наличия.
     *
     * @return void
     */
    public function testResourceLimit(): void
    {
        $check = new AbilityRequirementCheck();
        $values = new RequirementValues(new RequirementNumbers(resourceLimits: [
            'focus' => 5,
            'mana' => new DimensionalNumber(4, 0),
        ]));

        self::assertTrue($check->check(new ResourceLimitRequirement('focus', null), $values));
        self::assertTrue($check->check(new ResourceLimitRequirement('focus', 5), $values));
        self::assertFalse($check->check(new ResourceLimitRequirement('focus', 6), $values));
        self::assertTrue($check->check(new ResourceLimitRequirement('mana', new DimensionalNumber(4, 0)), $values));
        self::assertFalse($check->check(new ResourceLimitRequirement('mana', new DimensionalNumber(5, 0)), $values));
        self::assertFalse($check->check(new ResourceLimitRequirement('missing', null), $values));
        self::assertFalse($check->check(new ResourceLimitRequirement('focus', new DimensionalNumber(1, 0)), $values));
    }

    /**
     * has_ability_keyword: уровни выше нуля и исключённые признаки.
     *
     * @return void
     */
    public function testAbilityKeywordCount(): void
    {
        $check = new AbilityRequirementCheck();
        $values = new RequirementValues(
            new RequirementNumbers(levels: ['sword' => 1, 'axe' => 2, 'bow' => 0]),
            new RequirementTraits(abilityKeywords: [
                'sword' => ['melee', 'combat'],
                'axe' => ['melee', 'combat', 'heavy'],
                'bow' => ['ranged', 'combat'],
            ]),
        );

        self::assertTrue($check->check(new HasAbilityKeywordRequirement('combat', 2, []), $values));
        self::assertFalse($check->check(new HasAbilityKeywordRequirement('combat', 3, []), $values));
        self::assertTrue($check->check(new HasAbilityKeywordRequirement('combat', 1, ['heavy']), $values));
        self::assertFalse($check->check(new HasAbilityKeywordRequirement('combat', 2, ['heavy']), $values));
    }

    /**
     * has_keyword ищет признак персонажа.
     *
     * @return void
     */
    public function testHasKeyword(): void
    {
        $check = new AbilityRequirementCheck();
        $values = new RequirementValues(traits: new RequirementTraits(keywords: ['human', 'noble']));

        self::assertTrue($check->check(new HasKeywordRequirement('human'), $values));
        self::assertFalse($check->check(new HasKeywordRequirement('elf'), $values));
    }

    /**
     * min_weapon_mastery: любая семья с признаком нужного уровня.
     *
     * @return void
     */
    public function testMinWeaponMastery(): void
    {
        $check = new AbilityRequirementCheck();
        $values = new RequirementValues(traits: new RequirementTraits(
            weaponFamiliesByKeyword: [
                'dagger' => ['light', 'blade'],
                'sword' => ['blade', 'heavy'],
                'bow' => ['ranged'],
            ],
            weaponMasteryLevels: ['dagger' => 1, 'sword' => 3],
        ));

        self::assertTrue($check->check(new MinWeaponMasteryRequirement('blade', 3), $values));
        self::assertFalse($check->check(new MinWeaponMasteryRequirement('blade', 4), $values));
        self::assertTrue($check->check(new MinWeaponMasteryRequirement('light', 1), $values));
        self::assertFalse($check->check(new MinWeaponMasteryRequirement('ranged', 1), $values));
    }

    /**
     * Скорость и магия: null только при отсутствии модели.
     *
     * @return void
     */
    public function testSpeedAndMagicUnknowns(): void
    {
        $check = new AbilityRequirementCheck();
        $empty = new RequirementValues();
        $filled = new RequirementValues(unmodelled: new RequirementUnmodelled(
            speed: ['horizontal' => ['direction' => 'front', 'stepsPerActionPoint' => 2]],
            magicPaths: ['psionic'],
            magicPathExperience: ['psionic' => 6],
        ));

        self::assertNull($check->check(new CurrentSpeedRequirement('horizontal', 'front', 1), $empty));
        self::assertTrue($check->check(new CurrentSpeedRequirement('horizontal', 'front', 2), $filled));
        self::assertFalse($check->check(new CurrentSpeedRequirement('horizontal', 'back', 2), $filled));
        self::assertFalse($check->check(new CurrentSpeedRequirement('vertical', 'front', 1), $filled));
        self::assertNull($check->check(new HasMagicPathRequirement('psionic'), $empty));
        self::assertTrue($check->check(new HasMagicPathRequirement('psionic'), $filled));
        self::assertFalse($check->check(new HasMagicPathRequirement('arcane'), $filled));
        self::assertNull($check->check(new MagicPathExperienceRequirement('psionic', 1), $empty));
        self::assertTrue($check->check(new MagicPathExperienceRequirement('psionic', 6), $filled));
        self::assertFalse($check->check(new MagicPathExperienceRequirement('psionic', 7), $filled));
    }

    /**
     * and/or: пустые списки и распространение неизвестного.
     *
     * @return void
     */
    public function testLogicWithUnknowns(): void
    {
        $check = new AbilityRequirementCheck();
        $values = new RequirementValues(new RequirementNumbers(levels: ['swing' => 1]));
        $yes = new HasAbilityRequirement('swing', null);
        $no = new HasAbilityRequirement('missing', null);
        $unknown = new HasMagicPathRequirement('psionic');

        self::assertTrue($check->check(new AndRequirement([]), $values));
        self::assertFalse($check->check(new OrRequirement([]), $values));
        self::assertFalse($check->check(new AndRequirement([$yes, $no]), $values));
        self::assertNull($check->check(new AndRequirement([$yes, $unknown]), $values));
        self::assertNull($check->check(new AndRequirement([$unknown]), $values));
        self::assertTrue($check->check(new OrRequirement([$yes, $unknown]), $values));
        self::assertNull($check->check(new OrRequirement([$no, $unknown]), $values));
        self::assertFalse($check->check(new OrRequirement([$no]), $values));
        self::assertTrue($check->check(new AndRequirement([new OrRequirement([$yes, $no])]), $values));
    }
}
