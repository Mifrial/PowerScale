<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Query;

use Closure;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Ленивая карта runtime-таблицы словаря для hop.
 */
final class CatalogDefinitionLookup
{
    /**
     * @var Closure(string): SmartTableDefinition|null
     */
    private mixed $byName = null;

    /**
     * Подключает загрузчик по физ. имени.
     *
     * @param Closure $byName Имя → definition.
     *
     * @return void
     */
    public function attach(Closure $byName): void
    {
        $this->byName = $byName;
    }

    /**
     * Возвращает карту таблицы словаря.
     *
     * @param string $tableName Физическое имя.
     *
     * @return SmartTableDefinition Карта.
     *
     * @throws MapInvalidException Если каталог не подключён.
     */
    public function definitionByName(string $tableName): SmartTableDefinition
    {
        if ($this->byName === null) {
            throw new MapInvalidException('Reference target map is unavailable');
        }

        return ($this->byName)($tableName);
    }
}
