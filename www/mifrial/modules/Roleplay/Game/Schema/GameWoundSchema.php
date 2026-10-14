<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Game\Table\GameWoundCommandTable;

/**
 * Команды зажима и перевязки.
 */
final class GameWoundSchema
{
    /**
     * Создаёт установщик.
     *
     * @param IOpenedSchema $commandSchema DDL команды.
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
        return [GameWoundCommandTable::class];
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
