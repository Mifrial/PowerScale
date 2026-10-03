<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item\Op;

/**
 * Ветка resistance.
 */
final class ResistanceOp implements ItemModifierOp
{
    /**
     * Создаёт ветку.
     *
     * @param string $damageTypeCode Тип урона.
     * @param string $mode Режим.
     * @param int $value Значение.
     *
     * @return void
     */
    public function __construct(
        private readonly string $damageTypeCode,
        private readonly string $mode,
        private readonly int $value,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'resistance';
    }

    /**
     * Тип урона.
     *
     * @return string Значение.
     */
    public function getDamageTypeCode(): string
    {
        return $this->damageTypeCode;
    }

    /**
     * Режим.
     *
     * @return string Значение.
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Значение.
     *
     * @return int Значение.
     */
    public function getValue(): int
    {
        return $this->value;
    }
}
