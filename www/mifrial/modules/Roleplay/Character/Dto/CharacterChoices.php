<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

/**
 * Выборы листа для валидатора C4. Не посчитанный лист.
 */
final class CharacterChoices
{
    /**
     * Создаёт выборы.
     *
     * @param string $name Имя как пришло, до отказа за пустой trim.
     * @param string|null $raceCode Код расы или null.
     * @param array<int, CharacterAbilityChoice> $abilities Способности.
     * @param array<int, CharacterInventoryChoice> $inventory Предметы.
     * @param array<int, CharacterCharacteristicPurchase> $characteristicPurchases Закупки характеристик.
     * @param array<int, CharacterCustomRule> $customRules Свои правила.
     * @param bool|null $active Признак листа. null — ключа во входе не было.
     *
     * @return void
     */
    public function __construct(
        private readonly string $name,
        private readonly ?string $raceCode,
        private readonly array $abilities,
        private readonly array $inventory,
        private readonly array $characteristicPurchases,
        private readonly array $customRules,
        private readonly ?bool $active,
    ) {
    }

    /**
     * Имя.
     *
     * @return string Имя.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Код расы.
     *
     * @return string|null Код или null.
     */
    public function getRaceCode(): ?string
    {
        return $this->raceCode;
    }

    /**
     * Выбранные способности.
     *
     * @return array<int, CharacterAbilityChoice> Список.
     */
    public function getAbilities(): array
    {
        return $this->abilities;
    }

    /**
     * Инвентарь.
     *
     * @return array<int, CharacterInventoryChoice> Список.
     */
    public function getInventory(): array
    {
        return $this->inventory;
    }

    /**
     * Закупки характеристик.
     *
     * @return array<int, CharacterCharacteristicPurchase> Список.
     */
    public function getCharacteristicPurchases(): array
    {
        return $this->characteristicPurchases;
    }

    /**
     * Свои правила.
     *
     * @return array<int, CharacterCustomRule> Список.
     */
    public function getCustomRules(): array
    {
        return $this->customRules;
    }

    /**
     * Признак активного листа.
     *
     * @return bool true, если активен.
     */
    public function isActive(): bool
    {
        return $this->active ?? true;
    }

    /**
     * Ключ active был во входе.
     *
     * @return bool true, если значение передали.
     */
    public function hasActive(): bool
    {
        return $this->active !== null;
    }
}
