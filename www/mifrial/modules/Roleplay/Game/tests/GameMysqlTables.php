<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Tests\UserMysqlTables;
use Mifrial\Messages\Chat\Table\ChatMemberTable;
use Mifrial\Messages\Chat\Table\ChatMessageTable;
use Mifrial\Messages\Chat\Table\ChatTable;
use Mifrial\Roleplay\Character\Table\CharacterTable;
use Mifrial\Roleplay\Character\Table\CharacterViewerTable;
use Mifrial\Roleplay\Game\Table\GameActionTable;
use Mifrial\Roleplay\Game\Table\GameActionStrikeTable;
use Mifrial\Roleplay\Game\Table\GameBattleParticipantTable;
use Mifrial\Roleplay\Game\Table\GameBattleTable;
use Mifrial\Roleplay\Game\Table\GameCharacterTable;
use Mifrial\Roleplay\Game\Table\GameCheckTable;
use Mifrial\Roleplay\Game\Table\GameChronicleEntryTable;
use Mifrial\Roleplay\Game\Table\GameCommandTable;
use Mifrial\Roleplay\Game\Table\GameDeliveryTable;
use Mifrial\Roleplay\Game\Table\GameEconomyOperationTable;
use Mifrial\Roleplay\Game\Table\GameInvitationTable;
use Mifrial\Roleplay\Game\Table\GameJoinRequestTable;
use Mifrial\Roleplay\Game\Table\GameMemberTable;
use Mifrial\Roleplay\Game\Table\GameNpcTable;
use Mifrial\Roleplay\Game\Table\GameProcessTable;
use Mifrial\Roleplay\Game\Table\GameSessionCharacterTable;
use Mifrial\Roleplay\Game\Table\GameSessionTable;
use Mifrial\Roleplay\Game\Table\GameShopPositionTable;
use Mifrial\Roleplay\Game\Table\GameTable;
use Mifrial\Roleplay\Keyword\Table\KeywordTable;
use Mifrial\Roleplay\Mechanic\Table\MechanicTable;
use Mifrial\Roleplay\Rule\Table\RuleRevisionItemTable;
use Mifrial\Roleplay\Rule\Table\RuleRevisionTable;
use Mifrial\Roleplay\Rule\Table\RuleSpaceTable;
use Mifrial\Roleplay\Rule\Table\RuleTable;
use Mifrial\Roleplay\Rule\Table\RuleVersionTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceCatalogItemTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceCatalogSectionTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceMetaTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceRevisionCatalogTable;

/**
 * Снос таблиц Game и зависимых модулей перед DROP character/user.
 */
final class GameMysqlTables
{
    /**
     * Удаляет таблицы Game, Character, Rule, Chat и User, если есть.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     *
     * @return void
     */
    public static function drop(ISmartTableGateway $smartTableGateway): void
    {
        $tableClasses = [
            GameDeliveryTable::class,
            GameCheckTable::class,
            GameActionStrikeTable::class,
            GameActionTable::class,
            GameCommandTable::class,
            GameBattleParticipantTable::class,
            GameBattleTable::class,
            GameProcessTable::class,
            GameSessionCharacterTable::class,
            GameSessionTable::class,
            GameNpcTable::class,
            GameCharacterTable::class,
            GameJoinRequestTable::class,
            GameInvitationTable::class,
            GameEconomyOperationTable::class,
            GameShopPositionTable::class,
            GameChronicleEntryTable::class,
            GameMemberTable::class,
            GameTable::class,
            CharacterViewerTable::class,
            CharacterTable::class,
            RuleSpaceCatalogItemTable::class,
            RuleSpaceRevisionCatalogTable::class,
            RuleSpaceCatalogSectionTable::class,
            RuleSpaceMetaTable::class,
            RuleRevisionItemTable::class,
            RuleRevisionTable::class,
            RuleVersionTable::class,
            RuleSpaceTable::class,
            RuleTable::class,
            MechanicTable::class,
            KeywordTable::class,
            ChatMessageTable::class,
            ChatMemberTable::class,
            ChatTable::class,
        ];
        foreach ($tableClasses as $tableClass) {
            $openedTable = $smartTableGateway->open($tableClass);
            if ($openedTable->schema()->exists()) {
                $openedTable->schema()->deleteTable();
            }
        }

        UserMysqlTables::drop($smartTableGateway);
    }
}
