<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Service\Cache\TableCache;
use Mifrial\Core\SmartTable\Service\Schema\TableSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * DDL открытой карты.
 */
final class OpenedSchema implements IOpenedSchema
{
    /**
     * Создаёт порт схемы.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param TableSchema $tableSchema Исполнитель DDL.
     * @param TableCache $tableCache Кэш.
     *
     * @return void
     */
    public function __construct(
        private readonly SmartTableDefinition $tableDefinition,
        private readonly TableSchema $tableSchema,
        private readonly TableCache $tableCache,
    ) {
    }

    /**
     * Проверяет, есть ли физическая таблица этой карты.
     *
     * @return bool true, если таблица есть.
     */
    public function exists(): bool
    {
        return $this->tableSchema->hasPhysicalTable($this->tableDefinition->getName());
    }

    /**
     * Создаёт физическую таблицу по карте.
     *
     * @return void
     */
    public function createTable(): void
    {
        $this->tableSchema->createTable($this->tableDefinition);
        $this->tableCache->noteDdl($this->tableDefinition->getName());
    }

    /**
     * Добавляет отсутствующие колонки, индексы, mfv и FK карты.
     *
     * @return void
     */
    public function updateTable(): void
    {
        $this->tableSchema->updateTable($this->tableDefinition);
        $this->tableCache->noteDdl($this->tableDefinition->getName());
    }

    /**
     * Добавляет недостающее по карте и снимает leftover.
     *
     * @return void
     */
    public function forceUpdateTable(): void
    {
        $this->tableSchema->forceUpdateTable($this->tableDefinition);
        $this->tableCache->noteDdl($this->tableDefinition->getName());
    }

    /**
     * Удаляет физическую таблицу и mfv полей карты.
     *
     * @return void
     */
    public function deleteTable(): void
    {
        $this->tableSchema->deleteTable($this->tableDefinition);
        $this->tableCache->noteDdl($this->tableDefinition->getName());
    }
}
