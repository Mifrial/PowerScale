<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Schema;

use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Map\Table\MapAreaTable;
use Mifrial\Roleplay\Map\Table\MapNodeTable;
use Mifrial\Roleplay\Map\Table\MapTable;
use Mifrial\Roleplay\Map\Table\MapVariantAreaTable;
use Mifrial\Roleplay\Map\Table\MapVariantLineTable;
use Mifrial\Roleplay\Map\Table\MapVariantNodeTable;
use Mifrial\Roleplay\Map\Table\MapVariantTable;

/**
 * Карты модуля Roleplay/Map для CLI setup и тестов.
 */
final class MapSchema
{
    /**
     * Возвращает class-string карт модуля.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public static function getTableClasses(): array
    {
        return [
            MapTable::class,
            MapVariantTable::class,
            MapNodeTable::class,
            MapVariantNodeTable::class,
            MapAreaTable::class,
            MapVariantAreaTable::class,
            MapVariantLineTable::class,
        ];
    }
}
