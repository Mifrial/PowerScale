<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Formula;

/**
 * Узел характеристики действия.
 */
final class ActionCharacteristicNode implements DimensionalFormula
{
    /**
     * Создаёт узел.
     *
     * @param mixed $action Поле.
     * @param mixed $characteristic Поле.
     * @param mixed $multiplier Поле.
     * @param mixed $modifiers Поле.
     *
     * @return void
     */
    public function __construct(
        private readonly string $action,
        private readonly string $characteristic,
        private readonly ?int $multiplier,
        private readonly array $modifiers,
    ) {
    }

    /**
     * Тип узла.
     *
     * @return string Код.
     */
    public function getNode(): string
    {
        return 'actionCharacteristic';
    }

    /**
     * Действие strike, throw или shoot.
     *
     * @return string Значение.
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Код характеристики.
     *
     * @return string Значение.
     */
    public function getCharacteristic(): string
    {
        return $this->characteristic;
    }

    /**
     * Множитель или null.
     *
     * @return ?int Значение.
     */
    public function getMultiplier(): ?int
    {
        return $this->multiplier;
    }

    /**
     * Модификаторы.
     *
     * @return array Значение.
     */
    public function getModifiers(): array
    {
        return $this->modifiers;
    }
}
