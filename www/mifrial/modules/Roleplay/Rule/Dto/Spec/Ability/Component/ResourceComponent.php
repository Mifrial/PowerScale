<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Ветка resource.
 */
final class ResourceComponent implements ActionComponent
{
    /**
     * Создаёт ветку.
     *
     * @param string $resourceCode Ресурс.
     * @param int|DimensionalNumber|ChosenAmount $amount Количество.
     * @param ?string $label Подпись.
     *
     * @return void
     */
    public function __construct(
        private readonly string $resourceCode,
        private readonly int|DimensionalNumber|ChosenAmount $amount,
        private readonly ?string $label,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'resource';
    }

    /**
     * Ресурс.
     *
     * @return string Значение.
     */
    public function getResourceCode(): string
    {
        return $this->resourceCode;
    }

    /**
     * Количество.
     *
     * @return int|DimensionalNumber|ChosenAmount Значение.
     */
    public function getAmount(): int|DimensionalNumber|ChosenAmount
    {
        return $this->amount;
    }

    /**
     * Подпись.
     *
     * @return ?string Значение.
     */
    public function getLabel(): ?string
    {
        return $this->label;
    }
}
