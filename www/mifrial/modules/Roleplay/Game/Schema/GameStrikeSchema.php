<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Game\Table\GameStrikeCommandTable;
use Mifrial\Roleplay\Game\Table\GameStrikeTable;

/**
 * Карта удара и его команды.
 */
final class GameStrikeSchema
{
    /**
     * Создаёт установщик.
     *
     * @param IOpenedSchema $strikeSchema DDL удара.
     * @param IOpenedSchema $commandSchema DDL команды.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $strikeSchema,
        private readonly IOpenedSchema $commandSchema,
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
            GameStrikeTable::class,
            GameStrikeCommandTable::class,
        ];
    }

    /**
     * Приводит таблицы к текущей карте.
     *
     * @return void
     */
    public function install(): void
    {
        $this->installOne($this->strikeSchema);
        $this->installOne($this->commandSchema);
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
