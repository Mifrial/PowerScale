<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

/**
 * Урон по самому действующему: сила удара со сдвигом размера.
 */
final class SelfDamageOutcomeEffect implements ModifierOutcomeEffect
{
    /**
     * Один урон от самой сильной покрытой строки.
     */
    public const MODE_STRONGEST = 'strongest';

    /**
     * По одному урону на каждую покрытую строку.
     */
    public const MODE_EACH = 'each';

    /**
     * Создаёт последствие.
     *
     * @param int $sizeDelta Сдвиг размера силы удара.
     * @param string $damageTypeCode Тип урона.
     * @param bool $ignoreDefense Игнорировать защиту и сопротивление.
     * @param string $mode Выбор строк: strongest или each.
     *
     * @return void
     */
    public function __construct(
        private readonly int $sizeDelta,
        private readonly string $damageTypeCode,
        private readonly bool $ignoreDefense,
        private readonly string $mode = self::MODE_STRONGEST,
    ) {
    }

    /**
     * Поддерживает ли движок режим выбора строк.
     *
     * @param string $value Значение из спеки.
     *
     * @return bool true, если поддерживает.
     */
    public static function supportsMode(string $value): bool
    {
        return $value === self::MODE_STRONGEST || $value === self::MODE_EACH;
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

    /**
     * Выбор строк для урона по себе.
     *
     * @return string strongest или each.
     */
    public function getMode(): string
    {
        return $this->mode;
    }
}
