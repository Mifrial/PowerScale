<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\RuleSpec;
use Mifrial\Roleplay\Rule\Spec\SpecShape;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Spec правила weapon_family.
 */
final class WeaponFamilySpec implements RuleSpec
{
    /**
     * Создаёт spec.
     *
     * @param mixed $costs Поле.
     *
     * @return void
     */
    public function __construct(
        private readonly array $costs,
    ) {
    }


    /**
     * Тип правила.
     *
     * @return string Код.
     */
    public function getRuleType(): string
    {
        return 'weapon_family';
    }

    /**
     * Лестница стоимостей.
     *
     * @return array Значение.
     */
    public function getCosts(): array
    {
        return $this->costs;
    }

}
