<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalFormula;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;

/**
 * Дистанция в шагах или ипари.
 */
final class DistanceValue
{
    public const TYPE_STEP = 'step';

    public const TYPE_IPARI = 'ipari';

    /**
     * Создаёт значение дистанции.
     *
     * @param string $type Step или ipari.
     * @param DimensionalFormula $value Формула дистанции.
     *
     * @return void
     *
     * @throws RuleInvalidException Если тип неизвестен.
     */
    public function __construct(
        private readonly string $type,
        private readonly DimensionalFormula $value,
    ) {
        if (!in_array($type, [self::TYPE_STEP, self::TYPE_IPARI], true)) {
            throw new RuleInvalidException('Тип дистанции невалиден');
        }
    }

    /**
     * Тип дистанции.
     *
     * @return string step или ipari.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Формула дистанции.
     *
     * @return DimensionalFormula Узел.
     */
    public function getValue(): DimensionalFormula
    {
        return $this->value;
    }
}
