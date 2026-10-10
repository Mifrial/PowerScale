<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item\Op;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Операция минимальной native-стоимости ресурса.
 */
final class MinResourceCostOp implements ItemModifierOp
{
    /**
     * Создаёт операцию.
     *
     * @param string $resourceCode Код ресурса.
     * @param int|DimensionalNumber $minimum Минимум.
     *
     * @return void
     */
    public function __construct(
        private readonly string $resourceCode,
        private readonly int|DimensionalNumber $minimum,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'min_resource_cost';
    }

    /**
     * Возвращает код ресурса.
     *
     * @return string Код.
     */
    public function getResourceCode(): string
    {
        return $this->resourceCode;
    }

    /**
     * Возвращает native minimum.
     *
     * @return int|DimensionalNumber Значение.
     */
    public function getMinimum(): int|DimensionalNumber
    {
        return $this->minimum;
    }
}
