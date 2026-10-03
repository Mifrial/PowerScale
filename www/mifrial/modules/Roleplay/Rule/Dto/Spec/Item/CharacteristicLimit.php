<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalFormula;

/**
 * Лимит характеристики предмета. limit — размерная формула.
 */
final class CharacteristicLimit
{
    /**
     * Создаёт лимит.
     *
     * @param string $characteristicCode Код.
     * @param DimensionalFormula $limit Формула.
     *
     * @return void
     */
    public function __construct(
        private readonly string $characteristicCode,
        private readonly DimensionalFormula $limit,
    ) {
    }

    /**
     * Код характеристики.
     *
     * @return string Код.
     */
    public function getCharacteristicCode(): string
    {
        return $this->characteristicCode;
    }

    /**
     * Формула лимита.
     *
     * @return DimensionalFormula Узел.
     */
    public function getLimit(): DimensionalFormula
    {
        return $this->limit;
    }
}
