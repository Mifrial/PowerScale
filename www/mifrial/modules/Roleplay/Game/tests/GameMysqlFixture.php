<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Cache\Interface\Container\ICacheContainer;
use Mifrial\Core\Cache\Interface\Service\ICacheStore;
use Mifrial\Core\Kernel\Interface\Service\IApplication;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedTable;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Schema\UserSchema;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;
use Mifrial\Core\User\Tests\UserMysqlTables;
use Mifrial\Messages\Chat\Schema\ChatSchema;
use Mifrial\Messages\Chat\Table\ChatMemberTable;
use Mifrial\Messages\Chat\Table\ChatMessageTable;
use Mifrial\Messages\Chat\Table\ChatTable;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Schema\CharacterSchema;
use Mifrial\Roleplay\Character\Table\CharacterTable;
use Mifrial\Roleplay\Character\Table\CharacterViewerTable;
use Mifrial\Roleplay\Game\Interface\Container\IGameContainer;
use Mifrial\Roleplay\Game\Interface\Service\IGameMemberships;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Schema\GameAdmissionSchema;
use Mifrial\Roleplay\Game\Schema\GameBattleSchema;
use Mifrial\Roleplay\Game\Schema\GameCheckSchema;
use Mifrial\Roleplay\Game\Schema\GameChronicleSchema;
use Mifrial\Roleplay\Game\Schema\GameDeliverySchema;
use Mifrial\Roleplay\Game\Schema\GameEconomySchema;
use Mifrial\Roleplay\Game\Schema\GameProcessSchema;
use Mifrial\Roleplay\Game\Schema\GameSchema;
use Mifrial\Roleplay\Game\Schema\GameStrikeSchema;
use Mifrial\Roleplay\Game\Schema\GameWideStrikeSchema;
use Mifrial\Roleplay\Game\Table\GameBattleCommandTable;
use Mifrial\Roleplay\Game\Table\GameBattleParticipantTable;
use Mifrial\Roleplay\Game\Table\GameBattleTable;
use Mifrial\Roleplay\Game\Table\GameCharacterTable;
use Mifrial\Roleplay\Game\Table\GameCheckCommandTable;
use Mifrial\Roleplay\Game\Table\GameCheckTable;
use Mifrial\Roleplay\Game\Table\GameChronicleEntryTable;
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
use Mifrial\Roleplay\Game\Table\GameStrikeCommandTable;
use Mifrial\Roleplay\Game\Table\GameStrikeTable;
use Mifrial\Roleplay\Game\Table\GameTable;
use Mifrial\Roleplay\Game\Table\GameWideStrikeCommandTable;
use Mifrial\Roleplay\Game\Table\GameWideStrikeTable;
use Mifrial\Roleplay\Game\Table\GameWideStrikeTargetTable;
use Mifrial\Roleplay\Keyword\Schema\KeywordSchema;
use Mifrial\Roleplay\Keyword\Table\KeywordTable;
use Mifrial\Roleplay\Mechanic\Schema\MechanicSchema;
use Mifrial\Roleplay\Mechanic\Table\MechanicTable;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Schema\RuleSchema;
use Mifrial\Roleplay\Rule\Table\RuleRevisionItemTable;
use Mifrial\Roleplay\Rule\Table\RuleRevisionTable;
use Mifrial\Roleplay\Rule\Table\RuleSpaceTable;
use Mifrial\Roleplay\Rule\Table\RuleTable;
use Mifrial\Roleplay\Rule\Table\RuleVersionTable;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;
use Mifrial\Roleplay\RuleSpace\Schema\RuleSpaceSchema;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceCatalogItemTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceCatalogSectionTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceMetaTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceRevisionCatalogTable;
use PHPUnit\Framework\TestCase;

/**
 * MySQL: мир с ревизией и таблица game.
 */
trait GameMysqlFixture
{
    private ?IApplication $application = null;

    private ?IGames $games = null;

    private ?IGameMemberships $memberships = null;

    private ?ICharacters $characters = null;

    private ?IRuleSpaces $ruleSpaces = null;

    private ?IUserAccounts $userAccounts = null;

    private ?ISmartTableGateway $smartTableGateway = null;

    private ?ICacheStore $cacheStore = null;

    protected int $ownerUserId = 0;

    /**
     * Подключается или пропускает тест.
     *
     * @return void
     */
    protected function connectGameMysql(): void
    {
        try {
            $this->bootGameApplication();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Game tests');
        }

        $this->dropGameTables();
        $this->flushRuleSliceCache();
        $this->installGameSchemas();
        $this->ownerUserId = $this->gameUserAccounts()->addFromInput([
            'login' => 'owner',
            'name' => 'Owner',
        ]);
    }

    /**
     * Снимает таблицы.
     *
     * @return void
     */
    protected function dropGameTables(): void
    {
        if (!$this->smartTableGateway instanceof ISmartTableGateway) {
            return;
        }

        $gateway = $this->smartTableGateway;
        $this->deleteIfExists($gateway->open(GameDeliveryTable::class));
        $this->deleteIfExists($gateway->open(GameCheckTable::class));
        $this->deleteIfExists($gateway->open(GameCheckCommandTable::class));
        $this->deleteIfExists($gateway->open(GameWideStrikeTargetTable::class));
        $this->deleteIfExists($gateway->open(GameWideStrikeTable::class));
        $this->deleteIfExists($gateway->open(GameWideStrikeCommandTable::class));
        $this->deleteIfExists($gateway->open(GameStrikeTable::class));
        $this->deleteIfExists($gateway->open(GameStrikeCommandTable::class));
        $this->deleteIfExists($gateway->open(GameBattleParticipantTable::class));
        $this->deleteIfExists($gateway->open(GameBattleTable::class));
        $this->deleteIfExists($gateway->open(GameProcessTable::class));
        $this->deleteIfExists($gateway->open(GameBattleCommandTable::class));
        $this->deleteIfExists($gateway->open(GameSessionCharacterTable::class));
        $this->deleteIfExists($gateway->open(GameSessionTable::class));
        $this->deleteIfExists($gateway->open(GameNpcTable::class));
        $this->deleteIfExists($gateway->open(GameCharacterTable::class));
        $this->deleteIfExists($gateway->open(GameJoinRequestTable::class));
        $this->deleteIfExists($gateway->open(GameInvitationTable::class));
        $this->deleteIfExists($gateway->open(GameEconomyOperationTable::class));
        $this->deleteIfExists($gateway->open(GameShopPositionTable::class));
        $this->deleteIfExists($gateway->open(GameChronicleEntryTable::class));
        $this->deleteIfExists($gateway->open(GameMemberTable::class));
        $this->deleteIfExists($gateway->open(GameTable::class));
        $this->deleteIfExists($gateway->open(CharacterViewerTable::class));
        $this->deleteIfExists($gateway->open(CharacterTable::class));
        $this->deleteIfExists($gateway->open(RuleSpaceCatalogItemTable::class));
        $this->deleteIfExists($gateway->open(RuleSpaceRevisionCatalogTable::class));
        $this->deleteIfExists($gateway->open(RuleSpaceCatalogSectionTable::class));
        $this->deleteIfExists($gateway->open(RuleSpaceMetaTable::class));
        $this->deleteIfExists($gateway->open(RuleRevisionItemTable::class));
        $this->deleteIfExists($gateway->open(RuleRevisionTable::class));
        $this->deleteIfExists($gateway->open(RuleVersionTable::class));
        $this->deleteIfExists($gateway->open(RuleSpaceTable::class));
        $this->deleteIfExists($gateway->open(RuleTable::class));
        $this->deleteIfExists($gateway->open(MechanicTable::class));
        $this->deleteIfExists($gateway->open(KeywordTable::class));
        $this->deleteIfExists($gateway->open(ChatMessageTable::class));
        $this->deleteIfExists($gateway->open(ChatMemberTable::class));
        $this->deleteIfExists($gateway->open(ChatTable::class));
        UserMysqlTables::drop($gateway);
    }

    /**
     * Мир с ревизией 1.
     *
     * @param string $code Код.
     *
     * @return RuleSpaceRecord Мир.
     */
    protected function addWorldWithRevision(string $code): RuleSpaceRecord
    {
        $world = $this->gameRuleSpaces()->add($code, 'World', $this->ownerUserId, '');
        $this->gameRuleSpaces()->commit($world->getId(), [
            RuleCommitEntry::put('human', new RuleVersionBody('ability', 'Human', '', [], [], [], 'needs_work')),
        ]);

        return $world;
    }

    /**
     * Фасад игр.
     *
     * @return IGames Фасад.
     */
    protected function gameFacade(): IGames
    {
        self::assertInstanceOf(IGames::class, $this->games);

        return $this->games;
    }

    /**
     * Фасад строки персонажа.
     *
     * @return IGameMemberships Фасад.
     */
    protected function membershipFacade(): IGameMemberships
    {
        self::assertInstanceOf(IGameMemberships::class, $this->memberships);

        return $this->memberships;
    }

    /**
     * Фасад персонажа.
     *
     * @return ICharacters Фасад.
     */
    protected function characterFacade(): ICharacters
    {
        self::assertInstanceOf(ICharacters::class, $this->characters);

        return $this->characters;
    }

    /**
     * Приложение.
     *
     * @return IApplication Приложение.
     */
    protected function gameApplication(): IApplication
    {
        self::assertInstanceOf(IApplication::class, $this->application);

        return $this->application;
    }

    /**
     * Учётки.
     *
     * @return IUserAccounts Фасад.
     */
    protected function gameUserAccounts(): IUserAccounts
    {
        self::assertInstanceOf(IUserAccounts::class, $this->userAccounts);

        return $this->userAccounts;
    }

    /**
     * Миры.
     *
     * @return IRuleSpaces Фасад.
     */
    protected function gameRuleSpaces(): IRuleSpaces
    {
        self::assertInstanceOf(IRuleSpaces::class, $this->ruleSpaces);

        return $this->ruleSpaces;
    }

    /**
     * boot и ping.
     *
     * @return void
     *
     * @throws DatabaseException Если MySQL недоступен.
     */
    private function bootGameApplication(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $this->application = $application;
        $locator = $application->getLocator();
        $games = $locator->get(IGameContainer::class)->get(IGames::class);
        self::assertInstanceOf(IGames::class, $games);
        $this->games = $games;
        $memberships = $locator->get(IGameContainer::class)->get(IGameMemberships::class);
        self::assertInstanceOf(IGameMemberships::class, $memberships);
        $this->memberships = $memberships;
        $characters = $locator->get(ICharacterContainer::class)->get(ICharacters::class);
        self::assertInstanceOf(ICharacters::class, $characters);
        $this->characters = $characters;
        $ruleSpaces = $locator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
        self::assertInstanceOf(IRuleSpaces::class, $ruleSpaces);
        $this->ruleSpaces = $ruleSpaces;
        $smartTableContainer = $locator->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        self::assertInstanceOf(ISmartTableGateway::class, $smartTableGateway);
        $this->smartTableGateway = $smartTableGateway;
        $databaseConnection = $smartTableContainer->get(IDatabaseConnection::class);
        if (!$databaseConnection instanceof IlluminateDatabaseConnection) {
            TestCase::markTestSkipped('test connection is not Illuminate');
        }

        $databaseConnection->ping();
        $cacheStore = $locator->get(ICacheContainer::class)->get(ICacheStore::class);
        self::assertInstanceOf(ICacheStore::class, $cacheStore);
        $this->cacheStore = $cacheStore;
        $userAccounts = $locator->get(IUserContainer::class)->get(IUserAccounts::class);
        self::assertInstanceOf(IUserAccounts::class, $userAccounts);
        $this->userAccounts = $userAccounts;
    }

    /**
     * Схемы до game.
     *
     * @return void
     */
    private function installGameSchemas(): void
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        (new UserSchema(
            $gateway->open(UserTable::class)->schema(),
            $gateway->open(UserGroupTable::class)->schema(),
            $gateway->open(UserGroupMemberTable::class)->schema(),
        ))->install();
        (new ChatSchema(
            $gateway->open(ChatTable::class)->schema(),
            $gateway->open(ChatMemberTable::class)->schema(),
            $gateway->open(ChatMessageTable::class)->schema(),
        ))->install();
        (new KeywordSchema($gateway->open(KeywordTable::class)->schema()))->install();
        (new MechanicSchema($gateway->open(MechanicTable::class)->schema()))->install();
        (new RuleSchema(
            $gateway->open(RuleTable::class)->schema(),
            $gateway->open(RuleSpaceTable::class)->schema(),
            $gateway->open(RuleVersionTable::class)->schema(),
            $gateway->open(RuleRevisionTable::class)->schema(),
            $gateway->open(RuleRevisionItemTable::class)->schema(),
        ))->install();
        (new RuleSpaceSchema(
            $gateway->open(RuleSpaceMetaTable::class)->schema(),
            $gateway->open(RuleSpaceCatalogSectionTable::class)->schema(),
            $gateway->open(RuleSpaceCatalogItemTable::class)->schema(),
            $gateway->open(RuleSpaceRevisionCatalogTable::class)->schema(),
        ))->install();
        (new CharacterSchema(
            $gateway->open(CharacterTable::class)->schema(),
            $gateway->open(CharacterViewerTable::class)->schema(),
        ))->install();
        (new GameSchema(
            $gateway->open(GameTable::class)->schema(),
            $gateway->open(GameMemberTable::class)->schema(),
            $gateway->open(GameCharacterTable::class)->schema(),
            $gateway->open(GameNpcTable::class)->schema(),
            $gateway->open(GameSessionTable::class)->schema(),
            $gateway->open(GameSessionCharacterTable::class)->schema(),
        ))->install();
        (new GameAdmissionSchema(
            $gateway->open(GameInvitationTable::class)->schema(),
            $gateway->open(GameJoinRequestTable::class)->schema(),
        ))->install();
        (new GameChronicleSchema(
            $gateway->open(GameChronicleEntryTable::class)->schema(),
        ))->install();
        (new GameEconomySchema(
            $gateway->open(GameShopPositionTable::class)->schema(),
            $gateway->open(GameEconomyOperationTable::class)->schema(),
        ))->install();
        (new GameCheckSchema(
            $gateway->open(GameCheckTable::class)->schema(),
            $gateway->open(GameCheckCommandTable::class)->schema(),
        ))->install();
        (new GameStrikeSchema(
            $gateway->open(GameStrikeTable::class)->schema(),
            $gateway->open(GameStrikeCommandTable::class)->schema(),
        ))->install();
        (new GameWideStrikeSchema(
            $gateway->open(GameWideStrikeTable::class)->schema(),
            $gateway->open(GameWideStrikeTargetTable::class)->schema(),
            $gateway->open(GameWideStrikeCommandTable::class)->schema(),
        ))->install();
        (new GameDeliverySchema(
            $gateway->open(GameDeliveryTable::class)->schema(),
        ))->install();
        (new GameProcessSchema(
            $gateway->open(GameProcessTable::class)->schema(),
        ))->install();
        (new GameBattleSchema(
            $gateway->open(GameBattleTable::class)->schema(),
            $gateway->open(GameBattleParticipantTable::class)->schema(),
            $gateway->open(GameBattleCommandTable::class)->schema(),
        ))->install();
    }

    /**
     * Сбрасывает кэш срезов.
     *
     * @return void
     */
    private function flushRuleSliceCache(): void
    {
        if (!$this->cacheStore instanceof ICacheStore || !$this->cacheStore->isUsable()) {
            return;
        }

        $cacheKeys = [];
        for ($spaceId = 1; $spaceId <= 30; ++$spaceId) {
            for ($revision = 1; $revision <= 10; ++$revision) {
                $cacheKeys[] = 'vs:rule:' . $spaceId . ':' . $revision;
            }
        }

        $this->cacheStore->deleteKeys($cacheKeys);
    }

    /**
     * deleteTable если есть.
     *
     * @param IOpenedTable $openedTable Карта.
     *
     * @return void
     */
    private function deleteIfExists(IOpenedTable $openedTable): void
    {
        if ($openedTable->schema()->exists()) {
            $openedTable->schema()->deleteTable();
        }
    }
}
