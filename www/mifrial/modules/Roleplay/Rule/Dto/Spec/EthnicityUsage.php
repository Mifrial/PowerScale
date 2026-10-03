<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

/**
 * Пара язык и письменности народа.
 */
final class EthnicityUsage
{
    /**
     * Создаёт пару.
     *
     * @param string $languageCode Код языка.
     * @param array<int, string> $scriptCodes Письменности.
     *
     * @return void
     */
    public function __construct(
        private readonly string $languageCode,
        private readonly array $scriptCodes,
    ) {
    }

    /**
     * Язык.
     *
     * @return string Код.
     */
    public function getLanguageCode(): string
    {
        return $this->languageCode;
    }

    /**
     * Письменности.
     *
     * @return array<int, string> Коды.
     */
    public function getScriptCodes(): array
    {
        return $this->scriptCodes;
    }
}
