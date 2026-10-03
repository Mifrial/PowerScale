<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

/**
 * База характеристики из другой характеристики и источников.
 */
final class CharacteristicBaseFrom
{
    /**
     * Создаёт ссылку.
     *
     * @param string $characteristicCode Источник базы.
     * @param array<int, string> $sourceCodes Источники модификаторов.
     *
     * @return void
     */
    public function __construct(
        private readonly string $characteristicCode,
        private readonly array $sourceCodes,
    ) {
    }

    /**
     * Характеристика-источник.
     *
     * @return string Код.
     */
    public function getCharacteristicCode(): string
    {
        return $this->characteristicCode;
    }

    /**
     * Источники модификаторов.
     *
     * @return array<int, string> Коды.
     */
    public function getSourceCodes(): array
    {
        return $this->sourceCodes;
    }
}
