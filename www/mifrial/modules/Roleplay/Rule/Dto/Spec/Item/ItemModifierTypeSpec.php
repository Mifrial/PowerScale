<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

use Mifrial\Roleplay\Rule\Dto\Spec\RuleSpec;
use Mifrial\Roleplay\Rule\Spec\SpecShape;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Spec правила item_modifier_type.
 */
final class ItemModifierTypeSpec implements RuleSpec
{
    /**
     * Создаёт spec.
     *
     * @param mixed $exclusive Поле.
     *
     * @return void
     */
    public function __construct(
        private readonly bool $exclusive,
    ) {
    }


    /**
     * Тип правила.
     *
     * @return string Код.
     */
    public function getRuleType(): string
    {
        return 'item_modifier_type';
    }

    /**
     * На предмете не больше одного модификатора этого типа.
     *
     * @return bool true, если тип единственный.
     */
    public function isExclusive(): bool
    {
        return $this->exclusive;
    }

}
