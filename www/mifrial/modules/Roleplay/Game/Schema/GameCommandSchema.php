<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Game\Table\GameCommandTable;

/**
 * Карта журнала команд игры.
 */
final class GameCommandSchema
{
    /**
     * Создаёт установщик.
     *
     * @param IOpenedSchema $commandSchema DDL журнала.
     *
     * @return void
     */
    public function __construct(private readonly IOpenedSchema $commandSchema)
    {
    }

    /**
     * Возвращает class-string карт.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public static function getTableClasses(): array
    {
        return [GameCommandTable::class];
    }

    /**
     * Приводит таблицу к текущей карте.
     *
     * @return void
     */
    public function install(): void
    {
        if ($this->commandSchema->exists()) {
            $this->commandSchema->updateTable();

            return;
        }

        $this->commandSchema->createTable();
    }
}
