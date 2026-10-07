<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Game\Table\GameBattleCommandTable;
use Mifrial\Roleplay\Game\Table\GameBattleParticipantTable;
use Mifrial\Roleplay\Game\Table\GameBattleTable;

/**
 * Карта боя, состава и команды.
 */
final class GameBattleSchema
{
    /**
     * Создаёт установщик.
     *
     * @param IOpenedSchema $battleSchema DDL боя.
     * @param IOpenedSchema $participantSchema DDL состава.
     * @param IOpenedSchema $commandSchema DDL команды.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $battleSchema,
        private readonly IOpenedSchema $participantSchema,
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
            GameBattleTable::class,
            GameBattleParticipantTable::class,
            GameBattleCommandTable::class,
        ];
    }

    /**
     * Приводит таблицы к текущей карте.
     *
     * @return void
     */
    public function install(): void
    {
        $this->installOne($this->battleSchema);
        $this->installOne($this->participantSchema);
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
