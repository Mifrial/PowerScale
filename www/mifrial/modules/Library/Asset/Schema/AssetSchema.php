<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Schema;

use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Library\Asset\Table\AssetCollectionTable;
use Mifrial\Library\Asset\Table\AssetItemTable;

/**
 * Карты модуля Library/Asset для CLI setup и тестов.
 */
final class AssetSchema
{
    /**
     * Возвращает class-string карт модуля в порядке зависимостей.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public static function getTableClasses(): array
    {
        return [
            AssetCollectionTable::class,
            AssetItemTable::class,
        ];
    }
}
