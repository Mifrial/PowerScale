<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Game\Table\GameWideStrikeCommandTable;
use Mifrial\Roleplay\Game\Table\GameWideStrikeTable;
use Mifrial\Roleplay\Game\Table\GameWideStrikeTargetTable;

/**
 * Карта широкого удара, целей и команды.
 */
final class GameWideStrikeSchema
{
    /**
     * Создаёт установщик.
     *
     * @param IOpenedSchema $strikeSchema DDL удара.
     * @param IOpenedSchema $targetSchema DDL цели.
     * @param IOpenedSchema $commandSchema DDL команды.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $strikeSchema,
        private readonly IOpenedSchema $targetSchema,
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
            GameWideStrikeTable::class,
            GameWideStrikeTargetTable::class,
            GameWideStrikeCommandTable::class,
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
        $this->installOne($this->targetSchema);
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
