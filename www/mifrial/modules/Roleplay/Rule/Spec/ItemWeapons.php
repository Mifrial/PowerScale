<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalFormula;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ActionCharacteristicBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponBlock;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponDamage;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponProfile;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Оружие предмета и профили щита.
 */
final class ItemWeapons
{
    /**
     * Блок оружия.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return WeaponBlock|null Блок или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function block(array $document): ?WeaponBlock
    {
        $part = SpecShape::object($document, 'weapon');
        if ($part === null) {
            return null;
        }

        return new WeaponBlock(
            DimensionalNumbers::optional($part, 'min_strength'),
            self::profiles($part, 'weapon_profiles'),
            DimensionalNumbers::optional($part, 'durability'),
            SpecShape::optionalInt($part, 'min_action_cost'),
        );
    }

    /**
     * Профили атаки.
     *
     * @param array<string, mixed> $part Блок.
     * @param string $key Ключ списка.
     *
     * @return array<int, WeaponProfile> Профили.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function profiles(array $part, string $key): array
    {
        $profiles = [];
        foreach (SpecShape::list($part, $key) as $row) {
            $profiles[] = self::profile($row);
        }

        return $profiles;
    }

    /**
     * Один профиль.
     *
     * @param mixed $row Строка.
     *
     * @return WeaponProfile Профиль.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function profile(mixed $row): WeaponProfile
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('weapon_profiles');
        }

        return new WeaponProfile(
            SpecShape::string($row, 'type'),
            Formulas::dimensional($row, 'distance'),
            self::formula($row, 'range'),
            self::damage($row),
            Formulas::dimensional($row, 'penetration'),
            DimensionalNumbers::required($row, 'accuracy'),
            self::bases($row),
            DimensionalNumbers::optional($row, 'falloff'),
            SpecShape::optionalInt($row, 'dodge_benefit'),
        );
    }

    /**
     * Формула, если ключ есть.
     *
     * @param array<string, mixed> $row Строка.
     * @param string $key Ключ.
     *
     * @return DimensionalFormula|null Узел или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function formula(array $row, string $key): ?DimensionalFormula
    {
        $value = SpecShape::object($row, $key);

        return $value === null ? null : Formulas::dimensionalNode($value);
    }

    /**
     * Урон профиля.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return WeaponDamage Урон.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function damage(array $row): WeaponDamage
    {
        $damage = SpecShape::object($row, 'damage') ?? [];

        return new WeaponDamage(
            Formulas::dimensional($damage, 'formula'),
            SpecShape::optionalString($damage, 'damage_type_code'),
        );
    }

    /**
     * Базы действия.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return array<int, ActionCharacteristicBase> Список.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function bases(array $row): array
    {
        $bases = [];
        foreach (SpecShape::list($row, 'action_characteristics') as $base) {
            $bases[] = self::base($base);
        }

        return $bases;
    }

    /**
     * Одна база.
     *
     * @param mixed $base Строка.
     *
     * @return ActionCharacteristicBase База.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function base(mixed $base): ActionCharacteristicBase
    {
        if (!is_array($base) || array_is_list($base)) {
            throw new RuleSpecShapeException('action_characteristics');
        }

        return new ActionCharacteristicBase(
            SpecShape::string($base, 'characteristic'),
            Formulas::dimensional($base, 'value'),
        );
    }
}
