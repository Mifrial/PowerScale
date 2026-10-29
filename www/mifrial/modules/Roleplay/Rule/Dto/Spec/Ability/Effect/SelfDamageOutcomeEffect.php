<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

/**
 * Урон по самому действующему: сила удара со сдвигом размера.
 */
final class SelfDamageOutcomeEffect implements ModifierOutcomeEffect
{
    /**
     * Создаёт последствие.
     *
     * @param int $sizeDelta Сдвиг размера силы удара.
     * @param string $damageTypeCode Тип урона.
     * @param bool $ignoreDefense Игнорировать защиту и сопротивление.
     *
     * @return void
     */
    public function __construct(
        private readonly int $sizeDelta,
        private readonly string $damageTypeCode,
        private readonly bool $ignoreDefense,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'self_damage';
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
     * Игнорировать защиту.
     *
     * @return bool Значение.
     */
    public function isIgnoreDefense(): bool
    {
        return $this->ignoreDefense;
    }
}
