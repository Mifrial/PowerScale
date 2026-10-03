<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

/**
 * Урон себе после удара.
 */
final class SelfDamage
{
    /**
     * Создаёт значение.
     *
     * @param int $sizeDelta Сдвиг размера.
     * @param string $damageTypeCode Тип урона.
     * @param bool $internal Внутренний урон.
     *
     * @return void
     */
    public function __construct(
        private readonly int $sizeDelta,
        private readonly string $damageTypeCode,
        private readonly bool $internal,
    ) {
    }

    /**
     * Сдвиг размера.
     *
     * @return int Значение.
     */
    public function getSizeDelta(): int
    {
        return $this->sizeDelta;
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
     * Внутренний урон.
     *
     * @return bool Значение.
     */
    public function isInternal(): bool
    {
        return $this->internal;
    }
}
