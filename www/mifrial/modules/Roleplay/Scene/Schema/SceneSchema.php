<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Schema;

use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Scene\Table\SceneBoardTable;
use Mifrial\Roleplay\Scene\Table\SceneElementTable;
use Mifrial\Roleplay\Scene\Table\SceneEnclosureTable;
use Mifrial\Roleplay\Scene\Table\SceneLocationTable;

/**
 * Карты модуля Roleplay/Scene для CLI setup и тестов.
 */
final class SceneSchema
{
    /**
     * Возвращает class-string карт модуля в порядке зависимостей.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public static function getTableClasses(): array
    {
        return [
            SceneLocationTable::class,
            SceneBoardTable::class,
            SceneElementTable::class,
            SceneEnclosureTable::class,
        ];
    }
}
