<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Dto;

/**
 * Ключ кэша готового среза.
 */
final class RevisionSliceKey
{
    /**
     * Собирает ключ vs:{table}:{spaceId}:{revision}.
     *
     * @param string $identityTable Физическое имя identity.
     * @param int $spaceId Пространство.
     * @param int $revision Номер.
     *
     * @return string Ключ store.
     */
    public static function make(string $identityTable, int $spaceId, int $revision): string
    {
        return 'vs:' . $identityTable . ':' . $spaceId . ':' . $revision;
    }
}
