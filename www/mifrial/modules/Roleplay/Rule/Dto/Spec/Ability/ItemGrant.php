<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Грант предмета.
 */
final class ItemGrant implements AbilityGrant
{
    /**
     * Создаёт грант.
     *
     * @param string $itemCode Код предмета.
     * @param int $quantity Количество.
     * @param bool $permanent Постоянный грант.
     * @param array<string, string> $parameters Параметры экземпляра: код → код параметра способности.
     * @param array<string, string> $payload Поля payload экземпляра: код → код параметра способности.
     *
     * @return void
     */
    public function __construct(
        private readonly string $itemCode,
        private readonly int $quantity,
        private readonly bool $permanent,
        private readonly array $parameters = [],
        private readonly array $payload = [],
    ) {
    }

    /**
     * Тип гранта.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'item';
    }

    /**
     * Код.
     *
     * @return string Значение.
     */
    public function getItemCode(): string
    {
        return $this->itemCode;
    }

    /**
     * Количество.
     *
     * @return int Значение.
     */
    public function getQuantity(): int
    {
        return $this->quantity;
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

    /**
     * Привязка параметров экземпляра к параметрам способности.
     *
     * @return array<string, string> Код экземпляра → код параметра способности.
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Привязка полей payload к параметрам способности.
     *
     * @return array<string, string> Код поля payload → код параметра.
     */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
