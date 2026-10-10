<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Formula\ActionCharacteristicModifier;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\ActionCharacteristicNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalFormula;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ArmorBlock;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\BlockProfile;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\DefenseSlot;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ShieldBlock;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponBlock;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponDamage;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponProfile;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Записывает схлопнутые числа в новый spec предмета.
 */
final class ItemModifierOperationItems
{
    /**
     * Пишет итоги в новый spec.
     *
     * @param ItemSpec $item Исходный предмет.
     * @param array<string, array{factor: float, delta: int, size: int}> $collapsed Итоги.
     * @param array<int, array<string, mixed>> $actions Прибавки урона.
     *
     * @return ItemSpec Копия.
     */
    public static function write(ItemSpec $item, array $collapsed, array $actions): ItemSpec
    {
        return new ItemSpec(
            $item->getCategory(),
            $item->getCostGm(),
            $item->isInnate(),
            self::weight($item->getWeight(), $collapsed['weight'] ?? null),
            $item->getSpecialRuleCodes(),
            $item->getGroupCode(),
            $item->getProficiencyFamilyCode(),
            $item->getMagicConductor(),
            $item->getAdvantages(),
            $item->getCheckAdvantages(),
            $item->getOccupyHands(),
            self::weapon($item->getWeapon(), $collapsed, $actions),
            self::armor($item->getArmor(), $collapsed),
            self::shield($item->getShield(), $collapsed, $actions),
            self::block($item->getBlockProfile(), $collapsed['block'] ?? null),
        );
    }

    /**
     * Вес после множителя и слагаемого.
     *
     * @param DimensionalNumber|null $weight Исходный.
     * @param array{factor: float, delta: int, size: int}|null $change Итог или null.
     *
     * @return DimensionalNumber|null Число или null.
     */
    private static function weight(?DimensionalNumber $weight, ?array $change): ?DimensionalNumber
    {
        if ($weight === null || $change === null) {
            return $weight;
        }

        return self::scaled($weight, $change, false);
    }

    /**
     * Блок оружия. Отсутствующий блок не создаётся.
     *
     * @param WeaponBlock|null $weapon Блок или null.
     * @param array<string, array{factor: float, delta: int, size: int}> $collapsed Итоги.
     * @param array<int, array<string, mixed>> $actions Прибавки.
     *
     * @return WeaponBlock|null Блок или null.
     */
    private static function weapon(?WeaponBlock $weapon, array $collapsed, array $actions): ?WeaponBlock
    {
        if ($weapon === null) {
            return null;
        }

        return new WeaponBlock(
            self::characteristic($weapon->getMinStrength(), $collapsed['weapon.min_strength'] ?? null),
            self::profiles($weapon->getWeaponProfiles(), $actions, 'weapon'),
            self::characteristic($weapon->getDurability(), $collapsed['weapon.durability'] ?? null),
            $weapon->getMinActionCost(),
        );
    }

    /**
     * Блок щита. Отсутствующий блок не создаётся.
     *
     * @param ShieldBlock|null $shield Блок или null.
     * @param array<string, array{factor: float, delta: int, size: int}> $collapsed Итоги.
     * @param array<int, array<string, mixed>> $actions Прибавки.
     *
     * @return ShieldBlock|null Блок или null.
     */
    private static function shield(?ShieldBlock $shield, array $collapsed, array $actions): ?ShieldBlock
    {
        if ($shield === null) {
            return null;
        }

        return new ShieldBlock(
            self::characteristic($shield->getMinStrength(), $collapsed['shield.min_strength'] ?? null),
            self::characteristic($shield->getDurability(), $collapsed['shield.durability'] ?? null),
            self::profiles($shield->getWeaponProfiles(), $actions, 'shield'),
            $shield->getCharacteristicLimits(),
        );
    }

    /**
     * Блок доспеха. Отсутствующий блок не создаётся.
     *
     * @param ArmorBlock|null $armor Блок или null.
     * @param array<string, array{factor: float, delta: int, size: int}> $collapsed Итоги.
     *
     * @return ArmorBlock|null Блок или null.
     */
    private static function armor(?ArmorBlock $armor, array $collapsed): ?ArmorBlock
    {
        if ($armor === null) {
            return null;
        }

        return new ArmorBlock(
            self::penalty($armor->getStrengthPenalty(), $collapsed['armor.strength_penalty'] ?? null),
            self::characteristic($armor->getMaxAgility(), $collapsed['armor.max_agility'] ?? null),
            $armor->getCharacteristicLimits(),
            self::defenseSlots($armor->getDefenseSlots(), $collapsed['armor.defense'] ?? null),
            $armor->getResistanceSlots(),
        );
    }

    /**
     * Штраф силы. Нет итога — исходное значение.
     *
     * @param int|null $penalty Штраф или null.
     * @param array{factor: float, delta: int, size: int}|null $change Итог или null.
     *
     * @return int|null Штраф или null.
     */
    private static function penalty(?int $penalty, ?array $change): ?int
    {
        if ($change === null) {
            return $penalty;
        }

        return self::scaledInt($penalty ?? 0, $change);
    }

    /**
     * Профиль блока. Нет профиля — поля нет.
     *
     * @param BlockProfile|null $block Профиль или null.
     * @param array{factor: float, delta: int, size: int}|null $change Итог или null.
     *
     * @return BlockProfile|null Профиль или null.
     */
    private static function block(?BlockProfile $block, ?array $change): ?BlockProfile
    {
        if ($block === null || $change === null) {
            return $block;
        }

        return new BlockProfile(
            $block->getEfficiency(),
            self::scaled($block->getDefense(), $change, false),
            $block->getResistances(),
        );
    }

    /**
     * Слоты защиты. Новые слоты не создаются.
     *
     * @param array<int, DefenseSlot> $slots Слоты.
     * @param array{factor: float, delta: int, size: int}|null $change Итог или null.
     *
     * @return array<int, DefenseSlot> Слоты.
     */
    private static function defenseSlots(array $slots, ?array $change): array
    {
        if ($change === null) {
            return $slots;
        }

        return self::scaledSlots($slots, $change);
    }

    /**
     * Копии слотов с новой защитой.
     *
     * @param array<int, DefenseSlot> $slots Слоты.
     * @param array{factor: float, delta: int, size: int} $change Итог.
     *
     * @return array<int, DefenseSlot> Слоты.
     */
    private static function scaledSlots(array $slots, array $change): array
    {
        $next = [];
        foreach ($slots as $slot) {
            $next[] = new DefenseSlot(
                self::scaled($slot->getDefense(), $change, false),
                $slot->getDurability(),
                $slot->getSourceCode(),
            );
        }

        return $next;
    }

    /**
     * Прочность, сила или ловкость по шкале характеристики.
     *
     * @param DimensionalNumber|null $number Число или null.
     * @param array{factor: float, delta: int, size: int}|null $change Итог или null.
     *
     * @return DimensionalNumber|null Число или null.
     */
    private static function characteristic(?DimensionalNumber $number, ?array $change): ?DimensionalNumber
    {
        if ($number === null || $change === null) {
            return $number;
        }

        return self::scaled($number, $change, true);
    }

    /**
     * Множитель к базе, затем слагаемое.
     *
     * @param DimensionalNumber $number Число.
     * @param array{factor: float, delta: int, size: int} $change Итог.
     * @param bool $characteristic true — слагаемое по шкале характеристики.
     *
     * @return DimensionalNumber Число.
     */
    private static function scaled(DimensionalNumber $number, array $change, bool $characteristic): DimensionalNumber
    {
        $base = (int) round($number->getBase() * $change['factor']);
        if (!$characteristic) {
            return new DimensionalNumber($base + $change['delta'], $number->getSize() + $change['size']);
        }

        return self::shifted($base, $number->getSize() + $change['size'], $change['delta']);
    }

    /**
     * Сдвиг по шкале характеристики. Ноль оставляет базу.
     *
     * @param int $base База после множителя.
     * @param int $size Размер.
     * @param int $delta Слагаемое.
     *
     * @return DimensionalNumber Число.
     */
    private static function shifted(int $base, int $size, int $delta): DimensionalNumber
    {
        $scaled = new CharacteristicNumber($base, $size);
        if ($delta === 0) {
            return $scaled;
        }

        return $scaled->modify($delta);
    }

    /**
     * Целое поле: множитель, затем слагаемое.
     *
     * @param int $value Значение.
     * @param array{factor: float, delta: int, size: int} $change Итог.
     *
     * @return int Число.
     */
    private static function scaledInt(int $value, array $change): int
    {
        return (int) round($value * $change['factor']) + $change['delta'];
    }

    /**
     * Профили атаки. Формула не actionCharacteristic не меняется.
     *
     * @param array<int, WeaponProfile> $profiles Профили.
     * @param array<int, array<string, mixed>> $actions Прибавки.
     * @param string $part weapon или shield.
     *
     * @return array<int, WeaponProfile> Профили.
     */
    private static function profiles(array $profiles, array $actions, string $part): array
    {
        $next = $profiles;
        foreach ($actions as $action) {
            $next = self::applyAction($next, $action, $part);
        }

        return $next;
    }

    /**
     * Одна прибавка ко всем профилям блока.
     *
     * @param array<int, WeaponProfile> $profiles Профили.
     * @param array<string, mixed> $action Прибавка.
     * @param string $part weapon или shield.
     *
     * @return array<int, WeaponProfile> Профили.
     */
    private static function applyAction(array $profiles, array $action, string $part): array
    {
        if ($action['part'] !== $part || $action['delta'] === 0) {
            return $profiles;
        }

        $rewritten = [];
        foreach ($profiles as $profile) {
            $rewritten[] = self::profile($profile, $action);
        }

        return $rewritten;
    }

    /**
     * Один профиль. Чужой вид и чужой тип урона не меняются.
     *
     * @param WeaponProfile $profile Профиль.
     * @param array<string, mixed> $action Прибавка.
     *
     * @return WeaponProfile Профиль.
     */
    private static function profile(WeaponProfile $profile, array $action): WeaponProfile
    {
        if (!self::profileMatches($profile, $action)) {
            return $profile;
        }

        $damage = $profile->getDamage();
        $penetration = $profile->getPenetration();
        if ($action['field'] === 'damage') {
            $damage = self::damage($damage, $action['delta'], $action['source']);
        }

        if ($action['field'] === 'penetration') {
            $penetration = self::formula($penetration, $action['delta'], $action['source']);
        }

        return new WeaponProfile(
            $profile->getType(),
            $profile->getDistance(),
            $profile->getRange(),
            $damage,
            $penetration,
            $profile->getAccuracy(),
            $profile->getActionCharacteristics(),
            $profile->getFalloff(),
            $profile->getDodgeBenefit(),
        );
    }

    /**
     * Профиль входит в списки операции. Пустой список — все.
     *
     * @param WeaponProfile $profile Профиль.
     * @param array<string, mixed> $action Прибавка.
     *
     * @return bool true, если профиль меняется.
     */
    private static function profileMatches(WeaponProfile $profile, array $action): bool
    {
        $profiles = $action['profiles'];
        if ($profiles !== [] && !in_array($profile->getType(), $profiles, true)) {
            return false;
        }

        $damageTypes = $action['damageTypes'];
        $damageType = $profile->getDamage()->getDamageTypeCode();

        return $damageTypes === [] || ($damageType !== null && in_array($damageType, $damageTypes, true));
    }

    /**
     * Урон с модификатором формулы.
     *
     * @param WeaponDamage $damage Урон.
     * @param int $delta Слагаемое.
     * @param string|null $source Код source операции.
     *
     * @return WeaponDamage Урон.
     */
    private static function damage(WeaponDamage $damage, int $delta, ?string $source): WeaponDamage
    {
        return new WeaponDamage(self::formula($damage->getFormula(), $delta, $source), $damage->getDamageTypeCode());
    }

    /**
     * Дописывает источник операции, не код модификатора предмета.
     *
     * @param DimensionalFormula $formula Формула.
     * @param int $delta Слагаемое.
     * @param string|null $source Код source операции.
     *
     * @return DimensionalFormula Формула.
     */
    private static function formula(DimensionalFormula $formula, int $delta, ?string $source): DimensionalFormula
    {
        if (!$formula instanceof ActionCharacteristicNode) {
            return $formula;
        }

        $modifiers = $formula->getModifiers();
        $modifiers[] = new ActionCharacteristicModifier($delta, $source, null);

        return new ActionCharacteristicNode(
            $formula->getAction(),
            $formula->getCharacteristic(),
            $formula->getMultiplier(),
            $modifiers,
        );
    }
}
