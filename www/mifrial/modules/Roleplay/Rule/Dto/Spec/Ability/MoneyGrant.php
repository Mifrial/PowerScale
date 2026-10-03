<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Денежный грант.
 */
final class MoneyGrant implements AbilityGrant
{
    /**
     * Создаёт грант.
     *
     * @param int $fixed Поле.
     * @param int $percent Поле.
     * @param string $apply Поле.
     * @param bool $permanent Поле.     *
     * @return void
     */
    public function __construct(
        private readonly int $fixed,
        private readonly int $percent,
        private readonly string $apply,
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
        return 'money';
    }

    /**
     * Фиксированная сумма.
     *
     * @return int Значение.
     */
    public function getFixed(): int
    {
        return $this->fixed;
    }

    /**
     * Процент.
     *
     * @return int Значение.
     */
    public function getPercent(): int
    {
        return $this->percent;
    }

    /**
     * max или min.
     *
     * @return string Значение.
     */
    public function getApply(): string
    {
        return $this->apply;
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
