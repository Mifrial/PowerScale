<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Game\Table\GameActionStrikeTable;
use Mifrial\Roleplay\Game\Table\GameActionTable;

/**
 * Карта общего действия и его ударов.
 */
final class GameActionSchema
{
    /**
     * Создаёт установщик.
     *
     * @param IOpenedSchema $actionSchema DDL действия.
     * @param IOpenedSchema $targetSchema DDL удара.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $actionSchema,
        private readonly IOpenedSchema $targetSchema,
    ) {
    }

    /**
     * Возвращает class-string карт.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public static function getTableClasses(): array
    {
        return [
            GameActionTable::class,
            GameActionStrikeTable::class,
        ];
    }

    /**
     * Приводит таблицы к текущей карте.
     *
     * @return void
     */
    public function install(): void
    {
        $this->installOne($this->actionSchema);
        $this->installOne($this->targetSchema);
    }

    /**
     * Создаёт или обновляет одну таблицу.
     *
     * @param IOpenedSchema $schema DDL.
     *
     * @return void
     */
    private function installOne(IOpenedSchema $schema): void
    {
        if ($schema->exists()) {
            $schema->updateTable();

            return;
        }

        $schema->createTable();
    }
}
