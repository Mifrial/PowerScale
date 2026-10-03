<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Поля снимка валидатора, не порты.

namespace Mifrial\Roleplay\Character\Dto;

/**
 * Результат валидатора: отказы и снимок, который C4 умеет собрать.
 */
final class CharacterValidation
{
    /**
     * Создаёт результат.
     *
     * @param array<int, CharacterProblem> $problems Отказы.
     * @param array<string, int> $abilityLevels Уровни выборов.
     * @param array<int, string> $racialAbilityCodes Каталог расы.
     * @param int $osSurchargeTotal Доплата ОС.
     * @param array<int, CharacterEquippedModifier> $equippedModifiers Надетые предметы.
     * @param array<string, array<int, string>> $coveredPaths Пути, покрытые грантом, по ключу экземпляра.
     * @param array<int, CharacterPurchasedCharacteristic> $purchasedCharacteristics Ступени закупки из правила.
     * @param bool $active Признак листа после разбора входа.
     *
     * @return void
     */
    public function __construct(
        private readonly array $problems,
        private readonly array $abilityLevels,
        private readonly array $racialAbilityCodes,
        private readonly int $osSurchargeTotal,
        private readonly array $equippedModifiers,
        private readonly array $coveredPaths,
        private readonly array $purchasedCharacteristics,
        private readonly bool $active,
    ) {
    }

    /**
     * Отказы.
     *
     * @return array<int, CharacterProblem> Список.
     */
    public function getProblems(): array
    {
        return $this->problems;
    }

    /**
     * Уровни, ушедшие в доплату.
     *
     * @return array<string, int> Код → уровень.
     */
    public function getAbilityLevels(): array
    {
        return $this->abilityLevels;
    }

    /**
     * Коды каталога расы.
     *
     * @return array<int, string> Коды.
     */
    public function getRacialAbilityCodes(): array
    {
        return $this->racialAbilityCodes;
    }

    /**
     * Сумма доплаты.
     *
     * @return int ОС.
     */
    public function getOsSurchargeTotal(): int
    {
        return $this->osSurchargeTotal;
    }

    /**
     * Снимки надетого.
     *
     * @return array<int, CharacterEquippedModifier> Список.
     */
    public function getEquippedModifiers(): array
    {
        return $this->equippedModifiers;
    }

    /**
     * Покрытые пути по ключу экземпляра.
     *
     * @return array<string, array<int, string>> Ключ → пути.
     */
    public function getCoveredPaths(): array
    {
        return $this->coveredPaths;
    }

    /**
     * Закупленные характеристики. Цена и значение со ступени правила.
     *
     * @return array<int, CharacterPurchasedCharacteristic> Список.
     */
    public function getPurchasedCharacteristics(): array
    {
        return $this->purchasedCharacteristics;
    }

    /**
     * Признак активного листа.
     *
     * @return bool true, если активен.
     */
    public function isActive(): bool
    {
        return $this->active;
    }
}
