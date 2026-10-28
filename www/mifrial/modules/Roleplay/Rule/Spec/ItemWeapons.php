<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Item\ActionCharacteristicBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponAmmunition;
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
            DimensionalNumbers::optionalCharacteristic($part, 'min_strength'),
            self::profiles($part, 'weapon_profiles'),
            DimensionalNumbers::optional($part, 'durability'),
            SpecShape::optionalInt($part, 'min_action_cost'),
            self::ammunitionRequirements($part),
        );
    }

    /**
     * Наборы кодов ключевых слов боеприпасов.
     *
     * @param array<string, mixed> $part Блок.
     *
     * @return array<int, array<int, string>> Наборы.
     *
     * @throws RuleSpecShapeException Если форма чужая или набор пуст.
     */
    private static function ammunitionRequirements(array $part): array
    {
        $requirements = [];
        foreach (SpecShape::list($part, 'ammunition_requirements') as $row) {
            $row = SpecShape::requireMap($row, 'ammunition_requirements');
            $codes = SpecShape::stringList($row, 'keyword_codes');
            if ($codes === []) {
                throw new RuleSpecShapeException('ammunition_requirements');
            }

            $requirements[] = $codes;
        }

        return $requirements;
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
        $row = SpecShape::requireMap($row, 'weapon_profiles');

        $type = SpecShape::string($row, 'type');
        if (!in_array($type, ['strike', 'throw', 'shoot'], true)) {
            throw new RuleSpecShapeException('type');
        }

        $distance = DistanceValues::range($row);
        $falloff = DistanceValues::optionalValue($row, 'falloff');
        if ($type === 'throw' && $distance->getMax() !== null) {
            throw new RuleSpecShapeException('distance.max');
        }

        if ($type !== 'throw' && $distance->getMax() === null) {
            throw new RuleSpecShapeException('distance.max');
        }

        if ($type === 'strike' && $falloff !== null) {
            throw new RuleSpecShapeException('falloff');
        }

        if ($type !== 'strike' && $falloff === null) {
            throw new RuleSpecShapeException('falloff');
        }

        return new WeaponProfile(
            $type,
            $distance,
            self::damage($row),
            Formulas::dimensional($row, 'penetration'),
            DimensionalNumbers::required($row, 'accuracy'),
            self::bases($row),
            $falloff,
            SpecShape::optionalInt($row, 'dodge_benefit'),
            self::ammunition($row, $type),
            SpecShape::optionalInt($row, 'extra_hands'),
        );
    }

    /**
     * Боеприпасы профиля.
     *
     * @param array<string, mixed> $row Строка.
     * @param string $type Вид профиля.
     *
     * @return WeaponAmmunition Описание.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function ammunition(array $row, string $type): WeaponAmmunition
    {
        $document = SpecShape::object($row, 'ammunition');
        if ($document === null) {
            if ($type === 'shoot') {
                throw new RuleSpecShapeException('ammunition');
            }

            return new WeaponAmmunition($type === 'throw' ? 'self' : 'none', []);
        }

        $mode = SpecShape::string($document, 'mode');
        $codes = SpecShape::stringList($document, 'keyword_codes');
        if (!in_array($mode, ['none', 'self', 'item'], true)) {
            throw new RuleSpecShapeException('ammunition.mode');
        }

        $expected = $type === 'strike' ? 'none' : ($type === 'throw' ? 'self' : 'item');
        if ($mode !== $expected) {
            throw new RuleSpecShapeException('ammunition.mode');
        }

        if (($mode === 'item') !== ($codes !== [])) {
            throw new RuleSpecShapeException('ammunition.keyword_codes');
        }

        return new WeaponAmmunition($mode, $codes);
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
        $base = SpecShape::requireMap($base, 'action_characteristics');

        return new ActionCharacteristicBase(
            SpecShape::string($base, 'characteristic'),
            Formulas::dimensional($base, 'value'),
        );
    }
}
