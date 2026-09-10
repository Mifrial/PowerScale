<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Cache\Interface\Container\ICacheContainer;
use Mifrial\Core\Cache\Interface\Service\ICacheStore;
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
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
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
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;
use Mifrial\Roleplay\RuleSpace\Schema\RuleSpaceSchema;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceCatalogItemTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceCatalogSectionTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceMetaTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceRevisionCatalogTable;
use PHPUnit\Framework\TestCase;

final class CharacterRuleSlicesMysqlTest extends TestCase
{
    private ?ICharacterRuleSlices $characterRuleSlices = null;

    private ?IRuleSpaces $ruleSpaces = null;

    private ?IKeywords $keywords = null;

    private ?ISmartTableGateway $smartTableGateway = null;

    private ?ICacheStore $cacheStore = null;

    private ?IUserAccounts $userAccounts = null;

    private int $ownerUserId = 0;

    /**
     * MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        try {
            $this->connectPorts();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Character slice tests');
        }

        $this->dropTables();
        $this->flushRuleSliceCache();
        $this->installSchemas();
        $this->ownerUserId = $this->userAccounts()->addFromInput([
            'login' => 'owner',
            'name' => 'Owner',
        ]);
    }

    /**
     * Снос таблиц.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropTables();
    }

    /**
     * Commit с keyword: code правила и code признака.
     *
     * @return void
     */
    public function testGetResolvesRuleAndKeywordCodes(): void
    {
        $ruleSpaces = $this->ruleSpaces();
        $world = $ruleSpaces->add('arena', 'Arena', $this->ownerUserId);
        $keywordId = $this->keywords()->add('fire', 'Fire');
        $ruleSpaces->commit($world->getId(), [
            RuleCommitEntry::put('bolt', new RuleVersionBody(
                'ability',
                'Bolt',
                '',
                ['power' => 1],
                [$keywordId],
                null,
                [],
                'needs_work',
            )),
            RuleCommitEntry::put('old-ward', $this->emptyBody('Ward'), false),
        ]);
        $slice = $this->characterRuleSlices()->get($world->getId(), 1);
        self::assertSame($world->getId(), $slice->getSpaceId());
        self::assertSame(1, $slice->getRevision());
        self::assertSame('arena', $slice->getSpaceCode());
        self::assertNotNull($slice->findLive('bolt'));
        self::assertSame(['fire'], $slice->findLive('bolt')->getKeywordCodes());
        self::assertTrue($slice->hasTombstone('old-ward'));
        self::assertNull($slice->findLive('old-ward'));
        self::assertSame(['bolt'], array_map(
            static fn ($rule): string => $rule->getCode(),
            $slice->getLiveRules(),
        ));
    }

    /**
     * Выключенный мир.
     *
     * @return void
     */
    public function testDeactivatedWorldIsInvalid(): void
    {
        $ruleSpaces = $this->ruleSpaces();
        $world = $ruleSpaces->add('arena', 'Arena', $this->ownerUserId);
        $ruleSpaces->commit($world->getId(), [RuleCommitEntry::put('bolt', $this->emptyBody('Bolt'))]);
        $ruleSpaces->deactivate($world->getId());
        try {
            $this->characterRuleSlices()->get($world->getId(), 1);
            self::fail('deactivated world must fail');
        } catch (CharacterInvalidException $exception) {
            self::assertSame('CHARACTER_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Нет ревизии.
     *
     * @return void
     */
    public function testMissingRevisionIsNotFound(): void
    {
        $world = $this->ruleSpaces()->add('arena', 'Arena', $this->ownerUserId);
        try {
            $this->characterRuleSlices()->get($world->getId(), 1);
            self::fail('missing revision must fail');
        } catch (CharacterNotFoundException $exception) {
            self::assertSame('CHARACTER_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * Порты.
     *
     * @return void
     *
     * @throws DatabaseException Если MySQL недоступен.
     */
    private function connectPorts(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $locator = $application->getLocator();
        $characterRuleSlices = $locator->get(ICharacterContainer::class)->get(ICharacterRuleSlices::class);
        self::assertInstanceOf(ICharacterRuleSlices::class, $characterRuleSlices);
        $this->characterRuleSlices = $characterRuleSlices;
        $ruleSpaces = $locator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
        self::assertInstanceOf(IRuleSpaces::class, $ruleSpaces);
        $this->ruleSpaces = $ruleSpaces;
        $keywords = $locator->get(IKeywordContainer::class)->get(IKeywords::class);
        self::assertInstanceOf(IKeywords::class, $keywords);
        $this->keywords = $keywords;
        $smartTableContainer = $locator->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        self::assertInstanceOf(ISmartTableGateway::class, $smartTableGateway);
        $this->smartTableGateway = $smartTableGateway;
        $databaseConnection = $smartTableContainer->get(IDatabaseConnection::class);
        if (!$databaseConnection instanceof IlluminateDatabaseConnection) {
            self::markTestSkipped('test connection is not Illuminate');
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
     * Keyword, Mechanic, Rule, sidecar.
     *
     * @return void
     */
    private function installSchemas(): void
    {
        $gateway = $this->smartTableGateway();
        (new UserSchema(
            $gateway->open(UserTable::class)->schema(),
            $gateway->open(UserGroupTable::class)->schema(),
            $gateway->open(UserGroupMemberTable::class)->schema(),
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
    }

    /**
     * Снятие по FK.
     *
     * @return void
     */
    private function dropTables(): void
    {
        if (!$this->smartTableGateway instanceof ISmartTableGateway) {
            return;
        }

        $gateway = $this->smartTableGateway;
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
        UserMysqlTables::drop($gateway);
    }

    /**
     * Сбрасывает кэш срезов часов.
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

    /**
     * Тело без признаков.
     *
     * @param string $name Подпись.
     *
     * @return RuleVersionBody Снимок.
     */
    private function emptyBody(string $name): RuleVersionBody
    {
        return new RuleVersionBody('ability', $name, '', [], [], null, [], 'needs_work');
    }

    /**
     * Загрузчик среза.
     *
     * @return ICharacterRuleSlices Порт.
     */
    private function characterRuleSlices(): ICharacterRuleSlices
    {
        self::assertInstanceOf(ICharacterRuleSlices::class, $this->characterRuleSlices);

        return $this->characterRuleSlices;
    }

    /**
     * Миры.
     *
     * @return IRuleSpaces Фасад.
     */
    private function ruleSpaces(): IRuleSpaces
    {
        self::assertInstanceOf(IRuleSpaces::class, $this->ruleSpaces);

        return $this->ruleSpaces;
    }

    /**
     * Признаки.
     *
     * @return IKeywords Фасад.
     */
    private function keywords(): IKeywords
    {
        self::assertInstanceOf(IKeywords::class, $this->keywords);

        return $this->keywords;
    }

    /**
     * Учётки.
     *
     * @return IUserAccounts Фасад.
     */
    private function userAccounts(): IUserAccounts
    {
        self::assertInstanceOf(IUserAccounts::class, $this->userAccounts);

        return $this->userAccounts;
    }

    /**
     * Шлюз.
     *
     * @return ISmartTableGateway Шлюз.
     */
    private function smartTableGateway(): ISmartTableGateway
    {
        self::assertInstanceOf(ISmartTableGateway::class, $this->smartTableGateway);

        return $this->smartTableGateway;
    }
}
