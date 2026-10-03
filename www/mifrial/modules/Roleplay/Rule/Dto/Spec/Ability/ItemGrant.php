<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Грант предмета.
 */
final class ItemGrant implements AbilityGrant
{
    /**
     * Создаёт грант.
     *
     * @param string $itemCode Поле.
     * @param int $quantity Поле.
     * @param bool $permanent Поле.     *
     * @return void
     */
    public function __construct(
        private readonly string $itemCode,
        private readonly int $quantity,
        private readonly bool $permanent,
    ) {
    }

    /**
     * Тип гранта.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'item';
    }

    /**
     * Код.
     *
     * @return string Значение.
     */
    public function getItemCode(): string
    {
        return $this->itemCode;
    }

    /**
     * Количество.
     *
     * @return int Значение.
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * Постоянный грант.
     *
     * @return bool Значение.
     */
    public function isPermanent(): bool
    {
        return $this->permanent;
    }
}
