<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Game\Table\GameCharacterTable;
use Mifrial\Roleplay\Game\Table\GameMemberTable;
use Mifrial\Roleplay\Game\Table\GameNpcTable;
use Mifrial\Roleplay\Game\Table\GameSessionCharacterTable;
use Mifrial\Roleplay\Game\Table\GameSessionTable;
use Mifrial\Roleplay\Game\Table\GameTable;

/**
 * Сверка карты Game с физикой.
 */
final class GameSchema
{
    /**
     * Создаёт установщик схемы.
     *
     * @param IOpenedSchema $gameSchema DDL `game`.
     * @param IOpenedSchema $memberSchema DDL `game_member`.
     * @param IOpenedSchema $characterSchema DDL `game_character`.
     * @param IOpenedSchema $npcSchema DDL `game_npc`.
     * @param IOpenedSchema $sessionSchema DDL `game_session`.
     * @param IOpenedSchema $sessionCharacterSchema DDL `game_session_character`.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $gameSchema,
        private readonly IOpenedSchema $memberSchema,
        private readonly IOpenedSchema $characterSchema,
        private readonly IOpenedSchema $npcSchema,
        private readonly IOpenedSchema $sessionSchema,
        private readonly IOpenedSchema $sessionCharacterSchema,
    ) {
    }

    /**
     * Возвращает class-string карт модуля.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public static function getTableClasses(): array
    {
        return [
            GameTable::class,
            GameMemberTable::class,
            GameCharacterTable::class,
            GameNpcTable::class,
            GameSessionTable::class,
            GameSessionCharacterTable::class,
        ];
    }

    /**
     * Приводит таблицу к текущей карте.
     *
     * @return void
     */
    public function install(): void
    {
        $this->apply($this->gameSchema);
        $this->apply($this->memberSchema);
        $this->apply($this->characterSchema);
        $this->apply($this->npcSchema);
        $this->apply($this->sessionSchema);
        $this->apply($this->sessionCharacterSchema);
    }

    /**
     * Создаёт или обновляет одну карту.
     *
     * @param IOpenedSchema $openedSchema DDL.
     *
     * @return void
     */
    private function apply(IOpenedSchema $openedSchema): void
    {
        if ($openedSchema->exists()) {
            $openedSchema->updateTable();

            return;
        }

        $openedSchema->createTable();
    }
}
