<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Толчок: пул, урон и допустимые профили.
 */
final class PushSpec
{
    /**
     * Создаёт толчок.
     *
     * @param string $pool Код пула; пустая строка, если проверка задана кодом.
     * @param string $damage Вид урона; пустая строка, если урон берётся из профиля.
     * @param string $profiles Допустимые профили.
     * @param array<string, int> $divisors Делитель по типу урона.
     * @param string $blockProfiles Допустимые профили блока.
     * @param string $check Код проверки толчка.
     * @param int $damageModifier Сдвиг урона профиля в пунктах.
     * @param int $stabilityBonusCap Потолок бонуса Устойчивости на блоке.
     *
     * @return void
     */
    public function __construct(
        private readonly string $pool,
        private readonly string $damage,
        private readonly string $profiles,
        private readonly array $divisors,
        private readonly string $blockProfiles = 'any',
        private readonly string $check = 'check-push',
        private readonly int $damageModifier = -3,
        private readonly int $stabilityBonusCap = 6,
    ) {
    }

    /**
     * Пул.
     *
     * @return string Код.
     */
    public function getPool(): string
    {
        return $this->pool;
    }

    /**
     * Урон.
     *
     * @return string Код.
     */
    public function getDamage(): string
    {
        return $this->damage;
    }

    /**
     * Профили.
     *
     * @return string Код.
     */
    public function getProfiles(): string
    {
        return $this->profiles;
    }

    /**
     * Делители стойки по типу урона.
     *
     * @return array<string, int> Словарь.
     */
    public function getDivisors(): array
    {
        return $this->divisors;
    }

    /**
     * Профили, которыми можно блокировать толчок.
     *
     * @return string Код.
     */
    public function getBlockProfiles(): string
    {
        return $this->blockProfiles;
    }

    /**
     * Код проверки толчка.
     *
     * @return string Код.
     */
    public function getCheck(): string
    {
        return $this->check;
    }

    /**
     * Сдвиг урона профиля в пунктах.
     *
     * @return int Пункты.
     */
    public function getDamageModifier(): int
    {
        return $this->damageModifier;
    }

    /**
     * Потолок бонуса Устойчивости на блоке.
     *
     * @return int Пункты.
     */
    public function getStabilityBonusCap(): int
    {
        return $this->stabilityBonusCap;
    }
}
