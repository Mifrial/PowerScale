<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Game\Table\GameEconomyOperationTable;
use Mifrial\Roleplay\Game\Table\GameShopPositionTable;

/**
 * Карта магазина и строки операции.
 */
final class GameEconomySchema
{
    /**
     * Создаёт установщик.
     *
     * @param IOpenedSchema $positionSchema DDL позиции.
     * @param IOpenedSchema $operationSchema DDL операции.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $positionSchema,
        private readonly IOpenedSchema $operationSchema,
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
            GameShopPositionTable::class,
            GameEconomyOperationTable::class,
        ];
    }

    /**
     * Приводит таблицы к текущей карте.
     *
     * @return void
     */
    public function install(): void
    {
        $this->installOne($this->positionSchema);
        $this->installOne($this->operationSchema);
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
