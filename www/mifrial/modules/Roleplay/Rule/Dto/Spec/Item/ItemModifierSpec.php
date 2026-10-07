<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

use Mifrial\Roleplay\Rule\Dto\Spec\RuleSpec;

/**
 * Spec правила item_modifier.
 */
final class ItemModifierSpec implements RuleSpec
{
    /**
     * Создаёт spec.
     *
     * @param string $typeCode Тип модификатора.
     * @param ItemModifierApplies $applies Применимость.
     * @param ItemModifierPrice $price Цена.
     * @param array<int, ItemModifierEffect> $effects Эффекты.
     * @param array<int, ItemModifierOperation> $operations Операции чисел.
     * @param ItemModifierPriceScale|null $priceScale Множитель чужой цены.
     *
     * @return void
     */
    public function __construct(
        private readonly string $typeCode,
        private readonly ItemModifierApplies $applies,
        private readonly ItemModifierPrice $price,
        private readonly array $effects,
        private readonly array $operations,
        private readonly ?ItemModifierPriceScale $priceScale,
    ) {
    }

    /**
     * Тип правила.
     *
     * @return string Код.
     */
    public function getRuleType(): string
    {
        return 'item_modifier';
    }

    /**
     * Тип модификатора.
     *
     * @return string Значение.
     */
    public function getTypeCode(): string
    {
        return $this->typeCode;
    }

    /**
     * Применимость.
     *
     * @return ItemModifierApplies Признаки.
     */
    public function getApplies(): ItemModifierApplies
    {
        return $this->applies;
    }

    /**
     * Цена.
     *
     * @return ItemModifierPrice Цена.
     */
    public function getPrice(): ItemModifierPrice
    {
        return $this->price;
    }

    /**
     * Эффекты.
     *
     * @return array<int, ItemModifierEffect> Список.
     */
    public function getEffects(): array
    {
        return $this->effects;
    }

    /**
     * Операции чисел. Старые эффекты сюда не входят.
     *
     * @return array<int, ItemModifierOperation> Список.
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    /**
     * Множитель чужой цены.
     *
     * @return ItemModifierPriceScale|null Множитель или null.
     */
    public function getPriceScale(): ?ItemModifierPriceScale
    {
        return $this->priceScale;
    }
}
