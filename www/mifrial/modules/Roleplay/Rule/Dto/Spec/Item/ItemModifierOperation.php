<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

use Mifrial\Roleplay\Rule\Dto\Spec\Item\Op\ItemModifierOp;

/**
 * Операция модификатора: ветка стата, условие по признакам и источник.
 */
final class ItemModifierOperation
{
    /**
     * Создаёт операцию.
     *
     * @param ItemModifierOp $op Ветка стата.
     * @param ItemModifierApplies $when Условие. Пустые списки — всегда.
     * @param string|null $sourceCode Код правила source или null.
     *
     * @return void
     */
    public function __construct(
        private readonly ItemModifierOp $op,
        private readonly ItemModifierApplies $when,
        private readonly ?string $sourceCode,
    ) {
    }

    /**
     * Ветка стата.
     *
     * @return ItemModifierOp Операция.
     */
    public function getOp(): ItemModifierOp
    {
        return $this->op;
    }

    /**
     * Условие по кодам признаков.
     *
     * @return ItemModifierApplies Тройка списков.
     */
    public function getWhen(): ItemModifierApplies
    {
        return $this->when;
    }

    /**
     * Источник чисел.
     *
     * @return string|null Код или null.
     */
    public function getSourceCode(): ?string
    {
        return $this->sourceCode;
    }
}
