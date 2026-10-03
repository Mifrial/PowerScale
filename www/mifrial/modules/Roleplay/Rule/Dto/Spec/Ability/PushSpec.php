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
     * @param string $pool strength или weapon_damage.
     * @param string $damage Вид урона.
     * @param string $profiles Допустимые профили.
     * @param array<string, int> $divisors Делитель по типу урона.
     *
     * @return void
     */
    public function __construct(
        private readonly string $pool,
        private readonly string $damage,
        private readonly string $profiles,
        private readonly array $divisors,
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
}
