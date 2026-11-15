<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

/**
 * Пополнение маны врождённых предметов-хранилищ от характеристики.
 */
final class ItemManaRefillEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param string $characteristicCode Характеристика, чьё значение прибавляется.
     *
     * @return void
     */
    public function __construct(private readonly string $characteristicCode)
    {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'item_mana_refill';
    }

    /**
     * Характеристика пополнения.
     *
     * @return string Код.
     */
    public function getCharacteristicCode(): string
    {
        return $this->characteristicCode;
    }
}
