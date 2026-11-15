<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Экземпляр предмета: код правила, признак надетости и параметры экземпляра.
 */
final class CharacterInventoryChoice
{
    /**
     * Создаёт выбор.
     *
     * @param string $ruleCode Код предмета.
     * @param bool $equipped Надет.
     * @param int $quantity Количество. Нет ключа во входе — 1.
     * @param array<int, string> $modifiers Коды модификаторов.
     * @param array<string, DimensionalNumber> $parameters Параметры экземпляра.
     * @param string|null $grantedByRuleCode Код способности-донора или null.
     * @param array<string, mixed> $payload Выборы экземпляра: Объём, записи заклинаний.
     *
     * @return void
     */
    public function __construct(
        private readonly string $ruleCode,
        private readonly bool $equipped,
        private readonly int $quantity = 1,
        private readonly array $modifiers = [],
        private readonly array $parameters = [],
        private readonly ?string $grantedByRuleCode = null,
        private readonly array $payload = [],
    ) {
    }

    /**
     * Код предмета.
     *
     * @return string Код.
     */
    public function getRuleCode(): string
    {
        return $this->ruleCode;
    }

    /**
     * Признак надетости.
     *
     * @return bool true, если надет.
     */
    public function isEquipped(): bool
    {
        return $this->equipped;
    }

    /**
     * Количество.
     *
     * @return int Число.
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * Коды модификаторов предмета.
     *
     * @return array<int, string> Коды.
     */
    public function getModifiers(): array
    {
        return $this->modifiers;
    }

    /**
     * Параметры экземпляра.
     *
     * @return array<string, DimensionalNumber> Код → значение.
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Способность, из гранта которой получен предмет.
     *
     * @return string|null Код или null.
     */
    public function getGrantedByRuleCode(): ?string
    {
        return $this->grantedByRuleCode;
    }

    /**
     * Выборы экземпляра, не сводимые к размерному числу.
     *
     * @return array<string, mixed> Ключ → значение.
     */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
