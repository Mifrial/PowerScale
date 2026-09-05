<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Keyword\Service;

use Mifrial\Roleplay\Keyword\Dto\KeywordRecord;

/**
 * Собирает JSON-вид признака из Record.
 */
final class KeywordViewAssembler
{
    /**
     * Собирает JSON-вид признака.
     *
     * @param KeywordRecord $keywordRecord Признак.
     *
     * @return array<string, mixed> Keyword.
     */
    public function assemble(KeywordRecord $keywordRecord): array
    {
        return [
            'id' => $keywordRecord->getId(),
            'code' => $keywordRecord->getCode(),
            'name' => $keywordRecord->getName(),
            'description' => $keywordRecord->getDescription(),
            'active' => $keywordRecord->isActive(),
        ];
    }
}
