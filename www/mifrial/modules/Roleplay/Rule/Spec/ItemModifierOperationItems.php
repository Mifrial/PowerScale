<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CharacteristicModifyGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalFormula;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\FixedNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\FixedScalar;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ArmorBlock;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\BlockProfile;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\CharacteristicLimit;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\DefenseSlot;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ShieldBlock;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponBlock;

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
            ItemModifierItemNumbers::weight($item->getWeight(), $collapsed['weight'] ?? null),
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
            self::grants($item->getGrants(), $collapsed),
            $item->isProjectile(),
        );
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
            ItemModifierItemNumbers::characteristicNumber($weapon->getMinStrength(), $collapsed['weapon.min_strength'] ?? null),
            ItemModifierItemProfiles::profiles($weapon->getWeaponProfiles(), $actions, 'weapon'),
            ItemModifierItemNumbers::characteristic($weapon->getDurability(), $collapsed['weapon.durability'] ?? null),
            $weapon->getMinActionCost(),
            $weapon->getAmmunitionRequirements(),
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
            ItemModifierItemNumbers::characteristicNumber($shield->getMinStrength(), $collapsed['shield.min_strength'] ?? null),
            ItemModifierItemNumbers::characteristic($shield->getDurability(), $collapsed['shield.durability'] ?? null),
            ItemModifierItemProfiles::profiles($shield->getWeaponProfiles(), $actions, 'shield'),
            self::limits($shield->getCharacteristicLimits(), $collapsed, 'shield'),
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
            ItemModifierItemNumbers::characteristic($armor->getMaxAgility(), $collapsed['armor.max_agility'] ?? null),
            self::limits($armor->getCharacteristicLimits(), $collapsed, 'armor'),
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

        return ItemModifierItemNumbers::scaledInt($penalty ?? 0, $change);
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
            ItemModifierItemNumbers::scaled($block->getDefense(), $change, false),
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
                ItemModifierItemNumbers::scaled($slot->getDefense(), $change, false),
                $slot->getDurability(),
                $slot->getSourceCode(),
            );
        }

        return $next;
    }

    /**
     * Гранты после схлопнутых characteristic_modify.
     *
     * @param array<int, AbilityGrant> $grants Исходные.
     * @param array<string, array{factor: float, delta: int, size: int}> $collapsed Итоги.
     *
     * @return array<int, AbilityGrant> Гранты.
     */
    private static function grants(array $grants, array $collapsed): array
    {
        $next = $grants;
        foreach ($collapsed as $target => $change) {
            if (!str_starts_with($target, 'grants.characteristic_modify.')) {
                continue;
            }

            $code = substr($target, strlen('grants.characteristic_modify.'));
            if ($code === '' || $change['delta'] === 0) {
                continue;
            }

            $next = self::grantOf($next, $code, $change['delta']);
        }

        return $next;
    }

    /**
     * Добавляет или усиливает modify-грант характеристики.
     *
     * @param array<int, AbilityGrant> $grants Список.
     * @param string $code Характеристика.
     * @param int $delta Слагаемое.
     *
     * @return array<int, AbilityGrant> Список.
     */
    private static function grantOf(array $grants, string $code, int $delta): array
    {
        foreach ($grants as $index => $grant) {
            if (!$grant instanceof CharacteristicModifyGrant || $grant->getCharacteristicCode() !== $code) {
                continue;
            }

            $amount = $grant->getAmount();
            if (!$amount instanceof FixedScalar) {
                break;
            }

            $grants[$index] = new CharacteristicModifyGrant(
                $code,
                $grant->getSourceCode(),
                $grant->getCheckCodes(),
                new FixedScalar($amount->getValue() + $delta),
                $grant->isPermanent(),
            );

            return $grants;
        }

        $grants[] = new CharacteristicModifyGrant($code, 'item', [], new FixedScalar($delta), true);

        return $grants;
    }

    /**
     * Лимиты блока после схлопывания.
     *
     * @param array<int, CharacteristicLimit> $limits Исходные.
     * @param array<string, array{factor: float, delta: int, size: int}> $collapsed Итоги.
     * @param string $part armor или shield.
     *
     * @return array<int, CharacteristicLimit> Лимиты.
     */
    private static function limits(array $limits, array $collapsed, string $part): array
    {
        $prefix = $part . '.characteristic_limit.';
        $next = [];
        foreach ($limits as $limit) {
            $change = $collapsed[$prefix . $limit->getCharacteristicCode()] ?? null;
            $next[] = $change === null ? $limit : new CharacteristicLimit(
                $limit->getCharacteristicCode(),
                self::limitFormula($limit->getLimit(), $change),
            );
        }

        return $next;
    }

    /**
     * Сдвиг размерного или фиксированного лимита. Иная формула не меняется.
     *
     * @param DimensionalFormula $formula Лимит.
     * @param array{factor: float, delta: int, size: int} $change Итог.
     *
     * @return DimensionalFormula Формула.
     */
    private static function limitFormula(DimensionalFormula $formula, array $change): DimensionalFormula
    {
        if ($formula instanceof DimensionalNode) {
            return new DimensionalNode(ItemModifierItemNumbers::scaled($formula->getNumber(), $change, true));
        }

        if ($formula instanceof FixedNode) {
            return new FixedNode($formula->getValue() + $change['delta']);
        }

        return $formula;
    }
}
