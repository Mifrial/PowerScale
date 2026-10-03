<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

/**
 * Числовой потолок характеристики надетого предмета.
 */
final class CharacterCharacteristicLimit
{
    /**
     * Создаёт потолок.
     *
     * @param string $characteristicCode Код характеристики.
     * @param int $limit Число листа.
     *
     * @return void
     */
    public function __construct(
        private readonly string $characteristicCode,
        private readonly int $limit,
    ) {
    }

    /**
     * Код характеристики.
     *
     * @return string Код.
     */
    public function getCharacteristicCode(): string
    {
        return $this->characteristicCode;
    }

    /**
     * Потолок.
     *
     * @return int Число.
     */
    public function getLimit(): int
    {
        return $this->limit;
    }
}
