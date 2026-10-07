<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Game\Table\GameCheckCommandTable;
use Mifrial\Roleplay\Game\Table\GameCheckTable;

/**
 * Карта проверки и её команды.
 */
final class GameCheckSchema
{
    /**
     * Создаёт установщик.
     *
     * @param IOpenedSchema $checkSchema DDL проверки.
     * @param IOpenedSchema $commandSchema DDL команды.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $checkSchema,
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
            GameCheckTable::class,
            GameCheckCommandTable::class,
        ];
    }

    /**
     * Приводит таблицы к текущей карте.
     *
     * @return void
     */
    public function install(): void
    {
        $this->installOne($this->checkSchema);
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
