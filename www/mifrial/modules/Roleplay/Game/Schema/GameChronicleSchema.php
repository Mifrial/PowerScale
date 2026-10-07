<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Game\Table\GameChronicleEntryTable;

/**
 * Карта записи летописи.
 */
final class GameChronicleSchema
{
    /**
     * Создаёт установщик.
     *
     * @param IOpenedSchema $entrySchema DDL записи.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $entrySchema,
    ) {
    }

    /**
     * Возвращает class-string карты.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public static function getTableClasses(): array
    {
        return [
            GameChronicleEntryTable::class,
        ];
    }

    /**
     * Приводит таблицу к текущей карте.
     *
     * @return void
     */
    public function install(): void
    {
        if ($this->entrySchema->exists()) {
            $this->entrySchema->updateTable();

            return;
        }

        $this->entrySchema->createTable();
    }
}
