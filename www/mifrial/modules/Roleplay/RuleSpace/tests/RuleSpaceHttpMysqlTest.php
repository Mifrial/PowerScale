<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Tests;

use Mifrial\Core\Cache\Interface\Container\ICacheContainer;
use Mifrial\Core\Cache\Interface\Service\ICacheStore;
use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
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
use Mifrial\Roleplay\Keyword\Schema\KeywordSchema;
use Mifrial\Roleplay\Keyword\Table\KeywordTable;
use Mifrial\Roleplay\Mechanic\Schema\MechanicSchema;
use Mifrial\Roleplay\Mechanic\Table\MechanicTable;
use Mifrial\Roleplay\Rule\Schema\RuleSchema;
use Mifrial\Roleplay\Rule\Table\RuleRevisionItemTable;
use Mifrial\Roleplay\Rule\Table\RuleRevisionTable;
use Mifrial\Roleplay\Rule\Table\RuleSpaceTable;
use Mifrial\Roleplay\Rule\Table\RuleTable;
use Mifrial\Roleplay\Rule\Table\RuleVersionTable;
use Mifrial\Roleplay\RuleSpace\Schema\RuleSpaceSchema;
use Mifrial\Roleplay\RuleSpace\Service\RuleSpacePermissionKeys;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceCatalogItemTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceCatalogSectionTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceMetaTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceRevisionCatalogTable;
use PHPUnit\Framework\TestCase;

final class RuleSpaceHttpMysqlTest extends TestCase
{
    private ?IApplication $application = null;

    private ?IRequestContext $requestContext = null;

    private ?IUserAccounts $userAccounts = null;

    private ?ISmartTableGateway $smartTableGateway = null;

    private ?ICacheStore $cacheStore = null;

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
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for RuleSpace HTTP tests');
        }

        $this->dropTables();
        $this->flushRuleSliceCache();
        $this->installSchemas();
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
     * Нет актора — AUTH_REQUIRED; без ключа список пуст; view_all — [].
     *
     * @return void
     */
    public function testAuthRequiredAndEmptyList(): void
    {
        $payload = $this->dispatch('ruleSpace.getList', null);
        self::assertFalse($payload['success']);
        self::assertSame('AUTH_REQUIRED', $payload['error']['code']);
        $anonGet = $this->dispatch('ruleSpace.get', ['id' => 999]);
        self::assertFalse($anonGet['success']);
        self::assertSame('AUTH_REQUIRED', $anonGet['error']['code']);

        $alice = $this->addUser('alice');
        $this->setActor($alice);
        $emptyOwn = $this->dispatch('ruleSpace.getList', null);
        self::assertTrue($emptyOwn['success']);
        self::assertSame([], $emptyOwn['data']);
        $createDenied = $this->dispatch('ruleSpace.create', ['name' => 'Nope']);
        self::assertFalse($createDenied['success']);
        self::assertSame('AUTH_DENIED', $createDenied['error']['code']);
        $this->setActor($alice, [RuleSpacePermissionKeys::CREATE]);
        $ownEmpty = $this->dispatch('ruleSpace.getList', null);
        self::assertTrue($ownEmpty['success']);
        self::assertSame([], $ownEmpty['data']);

        $this->setActor($alice, [RuleSpacePermissionKeys::VIEW_ALL]);
        $empty = $this->dispatch('ruleSpace.getList', null);
        self::assertTrue($empty['success']);
        self::assertSame([], $empty['data']);
    }

    /**
     * CRUD мета и фильтр getList.
     *
     * @return void
     */
    public function testCreateGetUpdateDeactivate(): void
    {
        $alice = $this->addUser('alice');
        $this->setActor($alice, $this->editorKeys());
        $created = $this->dispatch('ruleSpace.create', [
            'name' => 'Мир правил',
            'description' => 'd',
        ]);
        self::assertTrue($created['success']);
        self::assertSame('mir-pravil', $created['data']['code']);
        self::assertSame(0, $created['data']['revision']);
        self::assertSame(0, $created['data']['rulesCount']);
        self::assertTrue($created['data']['active']);
        self::assertSame($alice, $created['data']['ownerId']);
        self::assertIsInt($created['data']['createdAt']);
        $spaceId = $created['data']['id'];

        $list = $this->dispatch('ruleSpace.getList', null);
        self::assertTrue($list['success']);
        self::assertCount(1, $list['data']);
        $byCode = $this->dispatch('ruleSpace.getByCode', ['code' => 'mir-pravil']);
        self::assertTrue($byCode['success']);
        self::assertSame($spaceId, $byCode['data']['id']);

        $updated = $this->dispatch('ruleSpace.update', ['id' => $spaceId, 'name' => 'Alpha']);
        self::assertTrue($updated['success']);
        self::assertSame('Alpha', $updated['data']['name']);
        self::assertSame('mir-pravil', $updated['data']['code']);

        $deactivated = $this->dispatch('ruleSpace.deactivate', ['id' => $spaceId]);
        self::assertTrue($deactivated['success']);
        self::assertNull($deactivated['data']);
        $after = $this->dispatch('ruleSpace.getList', null);
        self::assertTrue($after['success']);
        self::assertSame([], $after['data']);
        $still = $this->dispatch('ruleSpace.get', ['id' => $spaceId]);
        self::assertTrue($still['success']);
        self::assertFalse($still['data']['active']);
    }

    /**
     * commitDraft первая ревизия и tombstone.
     *
     * @return void
     */
    public function testCommitDraft(): void
    {
        $this->setActor($this->addUser('alice'), $this->editorKeys());
        $spaceId = $this->dispatch('ruleSpace.create', ['name' => 'World', 'code' => 'world'])['data']['id'];
        $first = $this->dispatch('ruleSpace.commitDraft', [
            'spaceId' => $spaceId,
            'rules' => [
                ['code' => 'human', 'type' => 'ability', 'name' => 'Human'],
                ['code' => 'orc', 'type' => 'ability', 'name' => 'Orc'],
            ],
        ]);
        self::assertTrue($first['success']);
        self::assertSame(1, $first['data']['revision']);
        self::assertCount(2, $first['data']['rules']);
        self::assertSame('world', $first['data']['spaceCode']);
        self::assertSame([], $first['data']['sections']);
        self::assertArrayNotHasKey('changedCount', $first['data']);
        self::assertIsInt($first['data']['rules'][0]['createdAt']);
        self::assertTrue($first['data']['rules'][0]['active']);

        $space = $this->dispatch('ruleSpace.get', ['id' => $spaceId]);
        self::assertSame(1, $space['data']['revision']);
        self::assertSame(2, $space['data']['rulesCount']);

        $feed = $this->dispatch('ruleSpace.getRevisions', ['spaceId' => $spaceId]);
        self::assertTrue($feed['success']);
        self::assertCount(1, $feed['data']);
        self::assertSame(2, $feed['data'][0]['ruleCount']);
        self::assertArrayNotHasKey('changedCount', $feed['data'][0]);

        $second = $this->dispatch('ruleSpace.commitDraft', [
            'spaceId' => $spaceId,
            'rules' => [],
            'removedCodes' => ['human'],
        ]);
        self::assertTrue($second['success']);
        self::assertSame(2, $second['data']['revision']);
        self::assertFalse($second['data']['rules'][0]['active']);
        self::assertSame('orc', $second['data']['rules'][1]['code']);

        $missing = $this->dispatch('ruleSpace.get', ['id' => 999]);
        self::assertFalse($missing['success']);
        self::assertSame('RULESPACE_NOT_FOUND', $missing['error']['code']);
    }

    /**
     * commitDraft каталог-only владельцем; без sections шаринг.
     *
     * @return void
     */
    public function testCommitDraftCatalog(): void
    {
        $this->setActor($this->addUser('alice'), [RuleSpacePermissionKeys::CREATE]);
        $spaceId = $this->dispatch('ruleSpace.create', ['name' => 'World', 'code' => 'cat-world'])['data']['id'];
        $first = $this->dispatch('ruleSpace.commitDraft', [
            'spaceId' => $spaceId,
            'rules' => [[
                'code' => 'human',
                'type' => 'ability',
                'name' => 'Human',
                'catalogSection' => 'combat',
                'catalogSortOrder' => 1,
            ]],
            'sections' => [['code' => 'combat', 'name' => 'Бой', 'sortOrder' => 0]],
        ]);
        self::assertTrue($first['success']);
        self::assertSame('combat', $first['data']['sections'][0]['code']);
        self::assertSame('combat', $first['data']['rules'][0]['catalogSection']);
        self::assertSame(1, $first['data']['rules'][0]['catalogSortOrder']);

        $shared = $this->dispatch('ruleSpace.commitDraft', [
            'spaceId' => $spaceId,
            'rules' => [['code' => 'orc', 'type' => 'ability', 'name' => 'Orc']],
        ]);
        self::assertTrue($shared['success']);
        self::assertSame(2, $shared['data']['revision']);
        self::assertSame('combat', $shared['data']['sections'][0]['code']);
        self::assertNull($shared['data']['rules'][1]['catalogSection']);

        $only = $this->dispatch('ruleSpace.commitDraft', [
            'spaceId' => $spaceId,
            'rules' => [],
            'sections' => [['code' => 'magic', 'name' => 'Магия', 'sortOrder' => 0]],
        ]);
        self::assertTrue($only['success']);
        self::assertSame(3, $only['data']['revision']);
        self::assertSame('magic', $only['data']['sections'][0]['code']);
        self::assertNull($only['data']['rules'][0]['catalogSection']);

        $noop = $this->dispatch('ruleSpace.commitDraft', [
            'spaceId' => $spaceId,
            'rules' => [],
            'sections' => [['code' => 'magic', 'name' => 'Магия', 'sortOrder' => 0]],
        ]);
        self::assertFalse($noop['success']);
        self::assertSame('RULESPACE_INVALID', $noop['error']['code']);
    }

    /**
     * Владелец без *_all видит и правит своё; чужой — DENIED.
     *
     * @return void
     */
    public function testOwnerWithoutGlobalKeys(): void
    {
        $bob = $this->addUser('bob');
        $alice = $this->addUser('alice');
        $this->setActor($bob, [RuleSpacePermissionKeys::CREATE]);
        $spaceId = $this->dispatch('ruleSpace.create', ['name' => 'Bob World', 'code' => 'bob-world'])['data']['id'];
        $ownList = $this->dispatch('ruleSpace.getList', null);
        self::assertTrue($ownList['success']);
        self::assertCount(1, $ownList['data']);
        self::assertSame($bob, $ownList['data'][0]['ownerId']);
        $ownGet = $this->dispatch('ruleSpace.get', ['id' => $spaceId]);
        self::assertTrue($ownGet['success']);
        $ownUpdate = $this->dispatch('ruleSpace.update', ['id' => $spaceId, 'name' => 'Mine']);
        self::assertTrue($ownUpdate['success']);
        self::assertSame('Mine', $ownUpdate['data']['name']);

        $this->setActor($alice);
        $strangerList = $this->dispatch('ruleSpace.getList', null);
        self::assertTrue($strangerList['success']);
        self::assertSame([], $strangerList['data']);
        $strangerGet = $this->dispatch('ruleSpace.get', ['id' => $spaceId]);
        self::assertFalse($strangerGet['success']);
        self::assertSame('AUTH_DENIED', $strangerGet['error']['code']);
        $strangerEdit = $this->dispatch('ruleSpace.update', ['id' => $spaceId, 'name' => 'Hijack']);
        self::assertFalse($strangerEdit['success']);
        self::assertSame('AUTH_DENIED', $strangerEdit['error']['code']);

        $this->setActor($alice, [RuleSpacePermissionKeys::VIEW_ALL]);
        $adminList = $this->dispatch('ruleSpace.getList', null);
        self::assertTrue($adminList['success']);
        self::assertCount(1, $adminList['data']);
        $adminGet = $this->dispatch('ruleSpace.get', ['id' => $spaceId]);
        self::assertTrue($adminGet['success']);
        $viewOnlyEdit = $this->dispatch('ruleSpace.update', ['id' => $spaceId, 'name' => 'Nope']);
        self::assertFalse($viewOnlyEdit['success']);
        self::assertSame('AUTH_DENIED', $viewOnlyEdit['error']['code']);

        $this->setActor($alice, [RuleSpacePermissionKeys::EDIT_ALL]);
        $adminEdit = $this->dispatch('ruleSpace.update', ['id' => $spaceId, 'name' => 'Staff']);
        self::assertTrue($adminEdit['success']);
        self::assertSame('Staff', $adminEdit['data']['name']);

        $this->setActor($alice, [RuleSpacePermissionKeys::CREATE]);
        $inheritDenied = $this->dispatch('ruleSpace.create', [
            'name' => 'Copy',
            'code' => 'copy',
            'inheritFrom' => $spaceId,
        ]);
        self::assertFalse($inheritDenied['success']);
        self::assertSame('AUTH_DENIED', $inheritDenied['error']['code']);
        $this->setActor($alice, [RuleSpacePermissionKeys::CREATE, RuleSpacePermissionKeys::VIEW_ALL]);
        $inheritOk = $this->dispatch('ruleSpace.create', [
            'name' => 'Copy',
            'code' => 'copy',
            'inheritFrom' => $spaceId,
        ]);
        self::assertTrue($inheritOk['success']);
        self::assertSame($alice, $inheritOk['data']['ownerId']);
    }

    /**
     * boot и порты.
     *
     * @return void
     *
     * @throws DatabaseException Если MySQL недоступен.
     */
    private function connectPorts(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $this->application = $application;
        self::assertArrayHasKey('ruleSpace.getList', $application->getModuleManager()->getRoutes());
        $kernelContainer = $application->getLocator()->get(IKernelContainer::class);
        $requestContext = $kernelContainer->get(IRequestContext::class);
        self::assertInstanceOf(IRequestContext::class, $requestContext);
        $this->requestContext = $requestContext;
        $userAccounts = $application->getLocator()->get(IUserContainer::class)->get(IUserAccounts::class);
        self::assertInstanceOf(IUserAccounts::class, $userAccounts);
        $this->userAccounts = $userAccounts;
        $smartTableContainer = $application->getLocator()->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        self::assertInstanceOf(ISmartTableGateway::class, $smartTableGateway);
        $this->smartTableGateway = $smartTableGateway;
        $databaseConnection = $smartTableContainer->get(IDatabaseConnection::class);
        if (!$databaseConnection instanceof IlluminateDatabaseConnection) {
            self::markTestSkipped('test connection is not Illuminate');
        }

        $databaseConnection->ping();
        $cacheStore = $application->getLocator()->get(ICacheContainer::class)->get(ICacheStore::class);
        self::assertInstanceOf(ICacheStore::class, $cacheStore);
        $this->cacheStore = $cacheStore;
    }

    /**
     * User + кластер правил.
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
     * Кэш срезов.
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
     * Учётка.
     *
     * @param string $login Логин.
     *
     * @return int Id.
     */
    private function addUser(string $login): int
    {
        return $this->userAccounts()->addFromInput([
            'login' => $login,
            'name' => $login,
        ]);
    }

    /**
     * Актор HTTP.
     *
     * @param int $userId Учётка.
     * @param array<int, string> $permissionKeys Ключи.
     * @param bool $hasBypass Обход.
     *
     * @return void
     */
    private function setActor(int $userId, array $permissionKeys = [], bool $hasBypass = false): void
    {
        $this->requestContext()->setActor(new RequestActor($userId, $permissionKeys, $hasBypass));
    }

    /**
     * Ключи редактора мира.
     *
     * @return array<int, string> Ключи.
     */
    private function editorKeys(): array
    {
        return [
            RuleSpacePermissionKeys::CREATE,
            RuleSpacePermissionKeys::VIEW_ALL,
            RuleSpacePermissionKeys::EDIT_ALL,
        ];
    }

    /**
     * Action.
     *
     * @param string $action Код.
     * @param mixed $payload Тело.
     *
     * @return array<string, mixed> Конверт.
     */
    private function dispatch(string $action, mixed $payload): array
    {
        return $this->application()->dispatch($action, $payload)->toArray();
    }

    /**
     * Приложение.
     *
     * @return IApplication Приложение.
     */
    private function application(): IApplication
    {
        self::assertInstanceOf(IApplication::class, $this->application);

        return $this->application;
    }

    /**
     * Контекст.
     *
     * @return IRequestContext Контекст.
     */
    private function requestContext(): IRequestContext
    {
        self::assertInstanceOf(IRequestContext::class, $this->requestContext);

        return $this->requestContext;
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
