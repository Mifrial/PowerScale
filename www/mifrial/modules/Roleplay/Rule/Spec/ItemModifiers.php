<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierApplies;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierOperation;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierPrice;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierPriceOverride;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierPriceScale;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\ActionStrengthOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\AdvantageOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\ArmorReliabilityOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\BlockOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\CheckAdvantageOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\DefenseOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\DurabilityOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\ItemModifierOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\KeywordOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\MagicConductorOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\MaxAgilityOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\MinActionCostOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\MinResourceCostOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\MinStrengthOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\ResistanceOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\StrengthPenaltyOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\WeightOp;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Модификатор предмета.
 */
final class ItemModifiers
{
    /**
     * Читает модификатор.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return ItemModifierSpec Spec.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function read(array $document): ItemModifierSpec
    {
        return new ItemModifierSpec(
            SpecShape::string($document, 'type_code'),
            self::applies($document),
            self::price(SpecShape::object($document, 'price') ?? []),
            self::effects($document),
            self::operations($document),
            self::scale($document),
        );
    }

    /**
     * Применимость.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return ItemModifierApplies Признаки.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function applies(array $document): ItemModifierApplies
    {
        $applies = SpecShape::object($document, 'applies') ?? [];

        return new ItemModifierApplies(
            SpecShape::stringList($applies, 'keyword_all'),
            SpecShape::stringList($applies, 'keyword_any'),
            SpecShape::stringList($applies, 'keyword_none'),
        );
    }

    /**
     * Цена.
     *
     * @param array<string, mixed> $price Объект.
     *
     * @return ItemModifierPrice Цена.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function price(array $price): ItemModifierPrice
    {
        return new ItemModifierPrice(
            self::optionalNumber($price, 'factor'),
            SpecShape::optionalInt($price, 'add_gm'),
            SpecShape::optionalInt($price, 'add_gm_per_100g'),
            SpecShape::optionalInt($price, 'min_final_gm'),
            self::overrides($price),
        );
    }

    /**
     * Перекрытия цены.
     *
     * @param array<string, mixed> $price Цена.
     *
     * @return array<string, ItemModifierPriceOverride> Словарь.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function overrides(array $price): array
    {
        $map = SpecShape::object($price, 'by_keyword') ?? [];
        $overrides = [];
        foreach ($map as $code => $row) {
            if (!is_string($code) || !is_array($row) || array_is_list($row)) {
                throw new RuleSpecShapeException('by_keyword');
            }

            $overrides[$code] = new ItemModifierPriceOverride(
                self::optionalNumber($row, 'factor'),
                SpecShape::optionalInt($row, 'add_gm'),
                SpecShape::optionalInt($row, 'add_gm_per_100g'),
                SpecShape::optionalInt($row, 'min_final_gm'),
            );
        }

        return $overrides;
    }

    /**
     * Эффекты.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return array<int, ItemModifierEffect> Список.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function effects(array $document): array
    {
        $effects = [];
        foreach (SpecShape::list($document, 'effects') as $row) {
            $effects[] = self::effect($row);
        }

        return $effects;
    }

    /**
     * Один эффект.
     *
     * @param mixed $row Строка.
     *
     * @return ItemModifierEffect Эффект.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function effect(mixed $row): ItemModifierEffect
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('effects');
        }

        $ops = [];
        foreach (SpecShape::list($row, 'ops') as $op) {
            $ops[] = self::op($op);
        }

        return new ItemModifierEffect(SpecShape::optionalString($row, 'label'), SpecShape::string($row, 'text'), $ops);
    }

    /**
     * Операции чисел.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return array<int, ItemModifierOperation> Список.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function operations(array $document): array
    {
        $operations = [];
        foreach (SpecShape::list($document, 'operations') as $row) {
            $operations[] = self::operation($row);
        }

        return $operations;
    }

    /**
     * Одна операция чисел.
     *
     * @param mixed $row Строка.
     *
     * @return ItemModifierOperation Операция.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function operation(mixed $row): ItemModifierOperation
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('operations');
        }

        $when = SpecShape::object($row, 'when') ?? [];

        return new ItemModifierOperation(
            self::byType(SpecShape::string($row, 'type'), $row),
            new ItemModifierApplies(
                SpecShape::stringList($when, 'keyword_all'),
                SpecShape::stringList($when, 'keyword_any'),
                SpecShape::stringList($when, 'keyword_none'),
            ),
            SpecShape::optionalString($row, 'source_code'),
        );
    }

    /**
     * Операция эффекта.
     *
     * @param mixed $row Строка.
     *
     * @return ItemModifierOp Операция.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function op(mixed $row): ItemModifierOp
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('ops');
        }

        return self::byType(SpecShape::string($row, 'type'), $row);
    }

    /**
     * Ветка операции.
     *
     * @param string $type Тип.
     * @param array<string, mixed> $row Строка.
     *
     * @return ItemModifierOp Операция.
     *
     * @throws RuleSpecShapeException Если тип неизвестен.
     */
    private static function byType(string $type, array $row): ItemModifierOp
    {
        return match ($type) {
            'weight' => new WeightOp(self::optionalNumber($row, 'factor'), self::optionalNumber($row, 'add_kg')),
            'min_strength' => new MinStrengthOp(SpecShape::int($row, 'delta')),
            'durability' => new DurabilityOp(SpecShape::optionalInt($row, 'delta'), SpecShape::optionalInt($row, 'add_size')),
            'max_agility' => new MaxAgilityOp(SpecShape::optionalInt($row, 'delta'), SpecShape::optionalInt($row, 'add_size')),
            'block' => new BlockOp(self::optionalNumber($row, 'factor'), SpecShape::optionalInt($row, 'add'), SpecShape::optionalInt($row, 'add_size')),
            'defense' => new DefenseOp(self::optionalNumber($row, 'factor'), SpecShape::optionalInt($row, 'add'), SpecShape::optionalInt($row, 'add_size'), SpecShape::optionalInt($row, 'min')),
            'armor_reliability' => new ArmorReliabilityOp(SpecShape::optionalInt($row, 'set'), SpecShape::optionalInt($row, 'add')),
            'strength_penalty' => new StrengthPenaltyOp(SpecShape::optionalInt($row, 'add'), SpecShape::optionalInt($row, 'set')),
            default => self::rest($type, $row),
        };
    }

    /**
     * Остальные ветки.
     *
     * @param string $type Тип.
     * @param array<string, mixed> $row Строка.
     *
     * @return ItemModifierOp Операция.
     *
     * @throws RuleSpecShapeException Если тип неизвестен.
     */
    private static function rest(string $type, array $row): ItemModifierOp
    {
        return match ($type) {
            'action_strength' => new ActionStrengthOp(
                SpecShape::string($row, 'field'),
                SpecShape::int($row, 'delta'),
                SpecShape::stringList($row, 'profiles'),
                SpecShape::stringList($row, 'damage_type_codes'),
            ),
            'resistance' => new ResistanceOp(
                SpecShape::string($row, 'damage_type_code'),
                SpecShape::string($row, 'mode'),
                SpecShape::int($row, 'value'),
            ),
            'keyword' => new KeywordOp(SpecShape::stringList($row, 'add'), SpecShape::stringList($row, 'remove')),
            'min_action_cost' => new MinActionCostOp(SpecShape::int($row, 'min')),
            'min_resource_cost' => new MinResourceCostOp(
                SpecShape::string($row, 'resource_code'),
                self::nativeNumber($row, 'minimum'),
            ),
            'magic_conductor' => new MagicConductorOp(SpecShape::int($row, 'value')),
            'advantage' => new AdvantageOp(SpecShape::int($row, 'delta'), SpecShape::string($row, 'source_code')),
            'check_advantage' => new CheckAdvantageOp(
                SpecShape::int($row, 'delta'),
                SpecShape::stringList($row, 'characteristic_codes'),
                SpecShape::bool($row, 'includes_hit'),
            ),
            default => throw new RuleSpecShapeException('ops'),
        };
    }

    /**
     * Множитель чужой цены.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return ItemModifierPriceScale|null Множитель или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function scale(array $document): ?ItemModifierPriceScale
    {
        $scale = SpecShape::object($document, 'price_scale');
        if ($scale === null) {
            return null;
        }

        return new ItemModifierPriceScale(
            SpecShape::string($scale, 'type_code'),
            SpecShape::number($scale, 'factor'),
            SpecShape::bool($scale, 'increasing_only'),
        );
    }

    /**
     * Число или null.
     *
     * @param array<string, mixed> $row Строка.
     * @param string $key Ключ.
     *
     * @return int|float|null Число или null.
     *
     * @throws RuleSpecShapeException Если ключ не число.
     */
    private static function optionalNumber(array $row, string $key): int|float|null
    {
        if (!array_key_exists($key, $row) || $row[$key] === null) {
            return null;
        }

        return SpecShape::number($row, $key);
    }

    /**
     * Native minimum ресурса.
     *
     * @param array<string, mixed> $row Строка операции.
     * @param string $key Ключ значения.
     *
     * @return int|DimensionalNumber Native число.
     *
     * @throws RuleSpecShapeException Если значение имеет неверную форму.
     */
    private static function nativeNumber(array $row, string $key): int|DimensionalNumber
    {
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }

        if (is_array($value) && !array_is_list($value)) {
            return DimensionalNumbers::pair($value, $key);
        }

        throw new RuleSpecShapeException($key);
    }
}
