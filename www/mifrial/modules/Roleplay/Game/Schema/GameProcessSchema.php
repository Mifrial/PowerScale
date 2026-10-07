<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Game\Table\GameProcessTable;

/**
 * Карта process.
 */
final class GameProcessSchema
{
    /**
     * Создаёт установщик.
     *
     * @param IOpenedSchema $processSchema DDL process.
     *
     * @return void
     */
    public function __construct(private readonly IOpenedSchema $processSchema)
    {
    }

    /**
     * Возвращает class-string карт.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public static function getTableClasses(): array
    {
        return [GameProcessTable::class];
    }

    /**
     * Приводит таблицу к текущей карте.
     *
     * @return void
     */
    public function install(): void
    {
        if ($this->processSchema->exists()) {
            $this->processSchema->updateTable();

            return;
        }

        $this->processSchema->createTable();
    }
}
