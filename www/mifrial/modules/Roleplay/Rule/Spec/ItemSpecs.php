<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Item\AdvantageModifier;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ArmorBlock;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemCheckAdvantage;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemHands;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\BlockProfile;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\CharacteristicLimit;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\DefenseSlot;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ResistanceSlot;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ShieldBlock;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Предмет: лимиты характеристики читаются как размерная формула.
 */
final class ItemSpecs
{
    /**
     * Предмет.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return ItemSpec Spec.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function read(array $document): ItemSpec
    {
        return new ItemSpec(
            SpecShape::string($document, 'category'),
            SpecShape::optionalInt($document, 'cost_gm'),
            SpecShape::bool($document, 'innate'),
            DimensionalNumbers::optional($document, 'weight'),
            SpecShape::stringList($document, 'special_rule_codes'),
            SpecShape::optionalString($document, 'group_code'),
            SpecShape::optionalString($document, 'proficiency_family_code'),
            SpecShape::optionalInt($document, 'magic_conductor'),
            self::advantages($document),
            self::checkAdvantages($document),
            self::hands($document),
            ItemWeapons::block($document),
            self::armor($document),
            self::shield($document),
        );
    }

    /**
     * Броня, если ключ есть.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return ArmorBlock|null Блок или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function armor(array $document): ?ArmorBlock
    {
        $part = SpecShape::object($document, 'armor');
        if ($part === null) {
            return null;
        }

        return new ArmorBlock(
            SpecShape::optionalInt($part, 'strength_penalty'),
            DimensionalNumbers::optional($part, 'max_agility'),
            self::limits($part),
            self::defenseSlots($part),
            self::resistanceSlots($part),
        );
    }

    /**
     * Щит, если ключ есть.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return ShieldBlock|null Блок или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function shield(array $document): ?ShieldBlock
    {
        $part = SpecShape::object($document, 'shield');
        if ($part === null) {
            return null;
        }

        return new ShieldBlock(
            DimensionalNumbers::optional($part, 'min_strength'),
            self::block($part),
            DimensionalNumbers::optional($part, 'durability'),
            ItemWeapons::profiles($part, 'weapon_profiles'),
            self::limits($part),
        );
    }

    /**
     * Список лимитов.
     *
     * @param array<string, mixed> $part Броня или щит.
     *
     * @return array<int, CharacteristicLimit> Лимиты.
     *
     * @throws RuleSpecShapeException Если строка чужая.
     */
    public static function limits(array $part): array
    {
        $limits = [];
        foreach (SpecShape::list($part, 'characteristic_limits') as $row) {
            $limits[] = self::one($row);
        }

        return $limits;
    }

    /**
     * Одна строка.
     *
     * @param mixed $row Элемент.
     *
     * @return CharacteristicLimit Лимит.
     *
     * @throws RuleSpecShapeException Если строка не объект.
     */
    private static function one(mixed $row): CharacteristicLimit
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('characteristic_limits');
        }

        return new CharacteristicLimit(
            SpecShape::string($row, 'characteristic_code'),
            Formulas::dimensional($row, 'limit'),
        );
    }

    /**
     * Слоты защиты.
     *
     * @param array<string, mixed> $part Броня.
     *
     * @return array<int, DefenseSlot> Слоты.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function defenseSlots(array $part): array
    {
        $slots = [];
        foreach (SpecShape::list($part, 'defense_slots') as $row) {
            $slots[] = self::defense($row);
        }

        return $slots;
    }

    /**
     * Один слот защиты.
     *
     * @param mixed $row Строка.
     *
     * @return DefenseSlot Слот.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function defense(mixed $row): DefenseSlot
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('defense_slots');
        }

        return new DefenseSlot(
            DimensionalNumbers::required($row, 'defense'),
            SpecShape::int($row, 'durability'),
            SpecShape::optionalString($row, 'source_code'),
        );
    }

    /**
     * Слоты сопротивления.
     *
     * @param array<string, mixed> $part Броня или блок.
     *
     * @return array<int, ResistanceSlot> Слоты.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function resistanceSlots(array $part): array
    {
        $slots = [];
        foreach (SpecShape::list($part, 'resistance_slots') as $row) {
            $slots[] = self::resistance($row);
        }

        return $slots;
    }

    /**
     * Один слот сопротивления.
     *
     * @param mixed $row Строка.
     *
     * @return ResistanceSlot Слот.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function resistance(mixed $row): ResistanceSlot
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('resistance_slots');
        }

        return new ResistanceSlot(
            SpecShape::optionalString($row, 'damage_type_code'),
            DimensionalNumbers::required($row, 'value'),
            SpecShape::int($row, 'durability'),
            SpecShape::optionalString($row, 'source_code'),
        );
    }

    /**
     * Профиль блока щита.
     *
     * @param array<string, mixed> $part Щит.
     *
     * @return BlockProfile Профиль.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function block(array $part): BlockProfile
    {
        $block = SpecShape::object($part, 'block') ?? [];

        return new BlockProfile(
            DimensionalNumbers::required($block, 'efficiency'),
            DimensionalNumbers::required($block, 'defense'),
            self::resistances($block, 'resistances'),
        );
    }

    /**
     * Сопротивления.
     *
     * @param array<string, mixed> $part Блок.
     * @param string $key Ключ списка.
     *
     * @return array<int, ResistanceSlot> Слоты.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function resistances(array $part, string $key): array
    {
        $slots = [];
        foreach (SpecShape::list($part, $key) as $row) {
            $slots[] = self::resistance($row);
        }

        return $slots;
    }

    /**
     * Помехи предмета.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return array<int, AdvantageModifier> Список.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function advantages(array $document): array
    {
        $rows = [];
        foreach (SpecShape::list($document, 'advantages') as $row) {
            $rows[] = self::advantage($row);
        }

        return $rows;
    }

    /**
     * Одна помеха.
     *
     * @param mixed $row Строка.
     *
     * @return AdvantageModifier Помеха.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function advantage(mixed $row): AdvantageModifier
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('advantages');
        }

        return new AdvantageModifier(
            SpecShape::optionalString($row, 'source_code'),
            SpecShape::optionalString($row, 'source_label'),
            SpecShape::int($row, 'delta'),
        );
    }

    /**
     * Помехи проверок.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return array<int, ItemCheckAdvantage> Список.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function checkAdvantages(array $document): array
    {
        $rows = [];
        foreach (SpecShape::list($document, 'check_advantages') as $row) {
            $rows[] = self::checkAdvantage($row);
        }

        return $rows;
    }

    /**
     * Одна помеха проверки.
     *
     * @param mixed $row Строка.
     *
     * @return ItemCheckAdvantage Помеха.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function checkAdvantage(mixed $row): ItemCheckAdvantage
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('check_advantages');
        }

        return new ItemCheckAdvantage(
            SpecShape::int($row, 'delta'),
            SpecShape::stringList($row, 'characteristic_codes'),
            SpecShape::bool($row, 'includes_hit'),
        );
    }

    /**
     * Слоты рук. Чужой тип ключа — ошибка формы.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return ItemHands|null Слоты или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function hands(array $document): ?ItemHands
    {
        $hands = SpecShape::object($document, 'occupy_hands');
        if ($hands === null) {
            return null;
        }

        return new ItemHands(
            SpecShape::int($hands, 'min'),
            SpecShape::int($hands, 'max'),
            SpecShape::optionalInt($hands, 'action'),
        );
    }
}
