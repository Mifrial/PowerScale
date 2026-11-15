<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Schema;

use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Lore\Table\LoreArticleTable;
use Mifrial\Roleplay\Lore\Table\LoreLinkTable;
use Mifrial\Roleplay\Lore\Table\LoreSectionTable;
use Mifrial\Roleplay\Lore\Table\LoreWorldTable;

/**
 * Карты модуля Roleplay/Lore для CLI setup и тестов.
 */
final class LoreSchema
{
    /**
     * Возвращает class-string карт модуля в порядке зависимостей.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public static function getTableClasses(): array
    {
        return [
            LoreWorldTable::class,
            LoreSectionTable::class,
            LoreArticleTable::class,
            LoreLinkTable::class,
        ];
    }
}
