<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Tests;

use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Http\RequestContext;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\Kernel\Interface\Service\IApplication;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Value\Optional\OptionalArray;
use Mifrial\Core\Kernel\Value\Optional\OptionalBool;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Tests\GatewayHarness;
use Mifrial\Core\User\Dto\Action\CreateGroupInput;
use Mifrial\Core\User\Dto\Action\DeactivateUserInput;
use Mifrial\Core\User\Dto\Action\FindPageInput;
use Mifrial\Core\User\Dto\Action\GetGroupMembersInput;
use Mifrial\Core\User\Dto\Action\UpdateGroupInput;
use Mifrial\Core\User\Dto\Action\UpdateUserInput;
use Mifrial\Core\User\Dto\NewGroup;
use Mifrial\Core\User\Dto\NewUser;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Exception\UserLastBypassException;
use Mifrial\Core\User\Exception\UserNotFoundException;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Interface\Service\IUserGroups;
use Mifrial\Core\User\Repository\UserGroupMemberRepository;
use Mifrial\Core\User\Repository\UserGroupRepository;
use Mifrial\Core\User\Repository\UserRepository;
use Mifrial\Core\User\Schema\UserSchema;
use Mifrial\Core\User\Service\GroupHttpService;
use Mifrial\Core\User\Service\GroupInputNormalizer;
use Mifrial\Core\User\Service\UserAccess;
use Mifrial\Core\User\Service\UserAccounts;
use Mifrial\Core\User\Service\UserGroups;
use Mifrial\Core\User\Service\UserHttpService;
use Mifrial\Core\User\Service\UserInputNormalizer;
use Mifrial\Core\User\Service\UserMembershipSync;
use Mifrial\Core\User\Service\UserViewAssembler;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;
use PHPUnit\Framework\TestCase;

final class UserHttpMysqlTest extends TestCase
{
    private ?IUserAccounts $userAccounts = null;

    private ?IUserGroups $userGroups = null;

    private ?UserSchema $userSchema = null;

    private ?ISmartTableGateway $smartTableGateway = null;

    private ?IApplication $application = null;

    private ?IRequestContext $requestContext = null;

    /**
     * MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        try {
            $this->connectUser();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for User tests');
        }

        $this->dropUserModuleTables();
        $this->userSchema()->install();
    }

    /**
     * Сносит таблицы.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropUserModuleTables();
    }

    /**
     * Guards, self get, deactivate until, dispatch без второго login.
     *
     * @return void
     */
    public function testHttpGuardsListGetUpdateDeactivate(): void
    {
        $viewerId = $this->userAccounts()->add($this->newUser(['login' => 'ann', 'name' => 'Ann']));
        $otherId = $this->userAccounts()->add($this->newUser(['login' => 'bob', 'name' => 'Bob']));
        $this->userGroups()->add($this->newGroup([
            'name' => 'Смотр',
            'active' => true,
            'bypass' => false,
            'permissions' => ['user.view', 'user.edit', 'user.deactivate'],
        ]));
        $group = $this->userGroups()->findByName('Смотр');
        self::assertNotNull($group);
        $watchGroupId = $group->getId();
        $this->userGroups()->addMember($viewerId, $watchGroupId);
        $requestContext = $this->requestContext();
        $userHttp = $this->userHttpService($requestContext);
        try {
            $userHttp->findPage(new FindPageInput(
                50,
                0,
                OptionalString::absent(),
                OptionalBool::absent(),
            ));
            self::fail('no actor');
        } catch (ActionException $exception) {
            self::assertSame('AUTH_REQUIRED', $exception->getErrorCode());
        }

        $requestContext->setActor(new RequestActor($otherId, [], false));
        $own = $userHttp->get($otherId);
        self::assertSame('bob', $own['login']);
        self::assertArrayNotHasKey('lastLogin', $own);
        try {
            $userHttp->get($viewerId);
            self::fail('stranger get');
        } catch (ActionException $exception) {
            self::assertSame('AUTH_DENIED', $exception->getErrorCode());
        }

        $requestContext->setActor(new RequestActor(
            $viewerId,
            ['user.view', 'user.edit', 'user.deactivate'],
            false,
        ));
        $listPage = $userHttp->findPage(new FindPageInput(
            50,
            0,
            OptionalString::absent(),
            OptionalBool::absent(),
        ));
        self::assertSame(2, $listPage['total']);
        $list = $listPage['items'];
        $listByLogin = [];
        foreach ($list as $userView) {
            $listByLogin[$userView['login']] = $userView;
        }

        self::assertSame([$watchGroupId], $listByLogin['ann']['groups']);
        self::assertSame(['user.deactivate', 'user.edit', 'user.view'], $listByLogin['ann']['permissions']);
        self::assertSame([], $listByLogin['bob']['groups']);
        self::assertFalse($listByLogin['bob']['bypass']);
        self::assertArrayNotHasKey('lastLogin', $listByLogin['bob']);
        self::assertArrayNotHasKey('super_admin', $listByLogin['bob']);
        self::assertArrayNotHasKey('avatar_file_id', $listByLogin['bob']);
        self::assertNull($listByLogin['bob']['email']);
        self::assertNull($listByLogin['bob']['deactivatedUntil']);
        self::assertSame([], $userHttp->getByIds([]));
        $updated = $userHttp->update(new UpdateUserInput(
            $otherId,
            OptionalString::present('Robert'),
            OptionalString::absent(),
            OptionalString::absent(),
            OptionalString::absent(),
            OptionalArray::absent(),
            OptionalBool::absent(),
        ));
        self::assertSame('Robert', $updated['name']);
        $userHttp->deactivate(new DeactivateUserInput(
            $otherId,
            OptionalString::present('pause'),
            OptionalString::present('2026-12-01'),
        ));
        $paused = $userHttp->get($otherId);
        self::assertIsInt($paused['deactivatedUntil']);
        self::assertSame('pause', $paused['deactivateReason']);
        $other = $this->userAccounts()->getById($otherId);
        self::assertFalse($other->isActive());
        self::assertSame('pause', $other->getDeactivateReason());
        $reactivated = $userHttp->update(new UpdateUserInput(
            $otherId,
            OptionalString::absent(),
            OptionalString::absent(),
            OptionalString::absent(),
            OptionalString::absent(),
            OptionalArray::absent(),
            OptionalBool::present(true),
        ));
        self::assertTrue($reactivated['active']);
        $other = $this->userAccounts()->getById($otherId);
        self::assertTrue($other->isActive());
        self::assertNull($other->getDeactivateReason());
        self::assertNull($other->getDeactivatedUntil());
        try {
            $userHttp->deactivate(new DeactivateUserInput(
                $viewerId,
                OptionalString::absent(),
                OptionalString::absent(),
            ));
            self::fail('self deactivate');
        } catch (ActionException $exception) {
            self::assertSame('AUTH_DENIED', $exception->getErrorCode());
        }

        try {
            $userHttp->get(999999);
            self::fail('missing');
        } catch (UserNotFoundException $exception) {
            self::assertSame('USER_NOT_FOUND', $exception->getErrorCode());
        }

        $this->assertDispatchGetList($viewerId);
    }

    /**
     * HTTP групп: view/create/subset, members, deactivate LAST_BYPASS, extra bypass.
     *
     * @return void
     */
    public function testGroupHttpGuardsCreateMembersDeactivate(): void
    {
        $editorId = $this->userAccounts()->add($this->newUser(['login' => 'ed', 'name' => 'Ed']));
        $memberId = $this->userAccounts()->add($this->newUser(['login' => 'mo', 'name' => 'Mo']));
        $requestContext = $this->requestContext();
        $groupHttp = $this->groupHttpService($requestContext);
        try {
            $groupHttp->findPage(new FindPageInput(
                50,
                0,
                OptionalString::absent(),
                OptionalBool::absent(),
            ));
            self::fail('no actor');
        } catch (ActionException $exception) {
            self::assertSame('AUTH_REQUIRED', $exception->getErrorCode());
        }

        $requestContext->setActor(new RequestActor($editorId, ['user.view'], false));
        try {
            $groupHttp->findPage(new FindPageInput(
                50,
                0,
                OptionalString::absent(),
                OptionalBool::absent(),
            ));
            self::fail('no group view');
        } catch (ActionException $exception) {
            self::assertSame('AUTH_DENIED', $exception->getErrorCode());
        }

        $requestContext->setActor(new RequestActor(
            $editorId,
            ['user_group.view', 'user_group.create', 'user_group.edit', 'user.view'],
            false,
        ));
        try {
            $groupHttp->create(new CreateGroupInput(
                'Ops',
                ['user.edit'],
                OptionalBool::absent(),
                OptionalBool::absent(),
            ));
            self::fail('foreign key');
        } catch (ActionException $exception) {
            self::assertSame('AUTH_DENIED', $exception->getErrorCode());
        }

        $created = $groupHttp->create(new CreateGroupInput(
            'Ops',
            ['user.view'],
            OptionalBool::absent(),
            OptionalBool::present(true),
        ));
        self::assertSame('Ops', $created['name']);
        self::assertSame(0, $created['memberCount']);
        self::assertFalse($created['bypass']);
        self::assertTrue($created['assignOnRegister']);
        self::assertIsInt($created['createdAt']);
        $groupId = $created['id'];
        $this->userGroups()->addMember($memberId, $groupId);
        $listedPage = $groupHttp->findPage(new FindPageInput(
            50,
            0,
            OptionalString::absent(),
            OptionalBool::absent(),
        ));
        self::assertSame(1, $listedPage['total']);
        $listed = $listedPage['items'];
        self::assertCount(1, $listed);
        self::assertSame(1, $listed[0]['memberCount']);
        $membersPage = $groupHttp->getMembers(new GetGroupMembersInput($groupId, 50, 0));
        self::assertSame(1, $membersPage['total']);
        self::assertCount(1, $membersPage['items']);
        self::assertSame('Mo', $membersPage['items'][0]['name']);
        self::assertArrayNotHasKey('initials', $membersPage['items'][0]);
        self::assertArrayHasKey('assignOnRegister', $listed[0]);
        self::assertArrayNotHasKey('assign_on_register', $listed[0]);
        $updated = $groupHttp->update(new UpdateGroupInput(
            $groupId,
            OptionalString::present('Ops2'),
            OptionalArray::absent(),
            OptionalBool::absent(),
            OptionalBool::absent(),
        ));
        self::assertSame('Ops2', $updated['name']);
        try {
            $groupHttp->update(new UpdateGroupInput(
                $groupId,
                OptionalString::absent(),
                OptionalArray::absent(),
                OptionalBool::absent(),
                OptionalBool::absent(),
            ));
            self::fail('empty patch');
        } catch (UserInvalidException $exception) {
            self::assertSame('USER_INVALID', $exception->getErrorCode());
        }

        $requestContext->setActor(new RequestActor($editorId, ['user_group.deactivate'], true));
        $bypassId = $this->userGroups()->add($this->newGroup([
            'name' => 'Admins',
            'bypass' => true,
            'permissions' => [],
        ]));
        $this->userGroups()->addMember($editorId, $bypassId);
        try {
            $groupHttp->deactivate($bypassId);
            self::fail('last bypass group');
        } catch (UserLastBypassException $exception) {
            self::assertSame('USER_LAST_BYPASS', $exception->getErrorCode());
        }

        self::assertTrue($this->userGroups()->getById($bypassId)->isActive());
        $this->assertDispatchGroupList($editorId);
        $this->assertDispatchRejectsBypassKey($editorId);
    }

    /**
     * Страница членов: total, offset, пустая группа, нет группы, bounds.
     *
     * @return void
     */
    public function testGroupHttpGetMembersPage(): void
    {
        $viewerId = $this->userAccounts()->add($this->newUser(['login' => 'vw', 'name' => 'Vw']));
        $firstId = $this->userAccounts()->add($this->newUser(['login' => 'aa', 'name' => 'Aa']));
        $secondId = $this->userAccounts()->add($this->newUser(['login' => 'bb', 'name' => 'Bb']));
        $thirdId = $this->userAccounts()->add($this->newUser(['login' => 'cc', 'name' => 'Cc']));
        $groupId = $this->userGroups()->add($this->newGroup(['name' => 'Crew']));
        $emptyId = $this->userGroups()->add($this->newGroup(['name' => 'Empty']));
        $this->userGroups()->addMember($firstId, $groupId);
        $this->userGroups()->addMember($secondId, $groupId);
        $this->userGroups()->addMember($thirdId, $groupId);
        $requestContext = $this->requestContext();
        $groupHttp = $this->groupHttpService($requestContext);
        $requestContext->setActor(new RequestActor($viewerId, ['user.view'], false));
        try {
            $groupHttp->getMembers(new GetGroupMembersInput($groupId, 2, 0));
            self::fail('no group view');
        } catch (ActionException $exception) {
            self::assertSame('AUTH_DENIED', $exception->getErrorCode());
        }

        $requestContext->setActor(new RequestActor($viewerId, ['user_group.view'], false));
        $firstPage = $groupHttp->getMembers(new GetGroupMembersInput($groupId, 2, 0));
        self::assertSame(3, $firstPage['total']);
        self::assertCount(2, $firstPage['items']);
        self::assertSame(['Aa', 'Bb'], [$firstPage['items'][0]['name'], $firstPage['items'][1]['name']]);
        $secondPage = $groupHttp->getMembers(new GetGroupMembersInput($groupId, 2, 2));
        self::assertSame(3, $secondPage['total']);
        self::assertCount(1, $secondPage['items']);
        self::assertSame('Cc', $secondPage['items'][0]['name']);
        $emptyPage = $groupHttp->getMembers(new GetGroupMembersInput($emptyId, 10, 0));
        self::assertSame(0, $emptyPage['total']);
        self::assertSame([], $emptyPage['items']);
        try {
            $groupHttp->getMembers(new GetGroupMembersInput(9_999_999, 10, 0));
            self::fail('missing group');
        } catch (UserNotFoundException $exception) {
            self::assertSame('USER_NOT_FOUND', $exception->getErrorCode());
        }

        try {
            $groupHttp->getMembers(new GetGroupMembersInput($groupId, 0, 0));
            self::fail('limit 0');
        } catch (UserInvalidException $exception) {
            self::assertSame('USER_INVALID', $exception->getErrorCode());
        }

        try {
            $groupHttp->getMembers(new GetGroupMembersInput($groupId, 501, 0));
            self::fail('limit 501');
        } catch (UserInvalidException $exception) {
            self::assertSame('USER_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * dispatch user.findPage с явным актором.
     *
     * @param int $viewerId Актор.
     *
     * @return void
     */
    private function assertDispatchGetList(int $viewerId): void
    {
        if (!$this->application instanceof IApplication) {
            return;
        }

        $kernelContainer = $this->application->getLocator()->get(IKernelContainer::class);
        $requestContext = $kernelContainer->get(IRequestContext::class);
        self::assertInstanceOf(IRequestContext::class, $requestContext);
        $requestContext->setActor(new RequestActor($viewerId, ['user.view'], false));
        $response = $this->application->dispatch('user.findPage', [
            'limit' => 50,
            'offset' => 0,
        ]);
        $payload = $response->toArray();
        self::assertTrue($payload['success']);
        self::assertIsArray($payload['data']);
        self::assertSame(2, $payload['data']['total'] ?? null);
        self::assertCount(2, $payload['data']['items'] ?? []);
    }

    /**
     * dispatch userGroup.findPage.
     *
     * @param int $editorId Актор.
     *
     * @return void
     */
    private function assertDispatchGroupList(int $editorId): void
    {
        if (!$this->application instanceof IApplication) {
            return;
        }

        $kernelContainer = $this->application->getLocator()->get(IKernelContainer::class);
        $requestContext = $kernelContainer->get(IRequestContext::class);
        self::assertInstanceOf(IRequestContext::class, $requestContext);
        $requestContext->setActor(new RequestActor($editorId, ['user_group.view'], false));
        $response = $this->application->dispatch('userGroup.findPage', [
            'limit' => 50,
            'offset' => 0,
        ]);
        $payload = $response->toArray();
        self::assertTrue($payload['success']);
        self::assertIsArray($payload['data']);
        self::assertArrayHasKey('items', $payload['data']);
        self::assertArrayHasKey('total', $payload['data']);
    }

    /**
     * Лишний bypass на create — INVALID_PARAMS.
     *
     * @param int $editorId Актор.
     *
     * @return void
     */
    private function assertDispatchRejectsBypassKey(int $editorId): void
    {
        if (!$this->application instanceof IApplication) {
            return;
        }

        $kernelContainer = $this->application->getLocator()->get(IKernelContainer::class);
        $requestContext = $kernelContainer->get(IRequestContext::class);
        self::assertInstanceOf(IRequestContext::class, $requestContext);
        $requestContext->setActor(new RequestActor($editorId, ['user_group.create'], true));
        $response = $this->application->dispatch('userGroup.create', [
            'name' => 'X',
            'permissions' => [],
            'bypass' => true,
        ]);
        $payload = $response->toArray();
        self::assertFalse($payload['success']);
        self::assertSame('INVALID_PARAMS', $payload['error']['code'] ?? null);
    }

    /**
     * @param IRequestContext $requestContext Контекст.
     *
     * @return GroupHttpService Сценарий групп.
     */
    private function groupHttpService(IRequestContext $requestContext): GroupHttpService
    {
        return new GroupHttpService(
            new UserAccess($requestContext),
            $this->userGroups(),
            $this->userAccounts(),
            new UserGroupMemberRepository(
                $this->smartTableGateway()->open(UserGroupMemberTable::class)->records(),
            ),
        );
    }

    /**
     * @param IRequestContext $requestContext Контекст.
     *
     * @return UserHttpService Сценарий.
     */
    private function userHttpService(IRequestContext $requestContext): UserHttpService
    {
        $userAccess = new UserAccess($requestContext);

        return new UserHttpService(
            $userAccess,
            new UserViewAssembler(
                new UserGroupRepository($this->smartTableGateway()->open(UserGroupTable::class)->records()),
                new UserGroupMemberRepository($this->smartTableGateway()->open(UserGroupMemberTable::class)->records()),
            ),
            $this->userAccounts(),
            new UserMembershipSync($userAccess, $this->userGroups()),
        );
    }

    /**
     * @return IRequestContext Контекст.
     */
    private function requestContext(): IRequestContext
    {
        if ($this->requestContext instanceof IRequestContext) {
            return $this->requestContext;
        }

        $this->requestContext = new RequestContext();

        return $this->requestContext;
    }

    /**
     * @return void
     *
     * @throws DatabaseException Если MySQL недоступен.
     */
    private function connectUser(): void
    {
        $envHost = getenv('MIFRIAL_TEST_DB_HOST');
        if (is_string($envHost) && $envHost !== '') {
            $databaseConnection = new IlluminateDatabaseConnection(
                new IlluminateConnectionFactory(),
                $this->settingsFromEnv($envHost),
            );
            $databaseConnection->ping();
            $this->smartTableGateway = GatewayHarness::make($databaseConnection);
            $this->bindFacades($this->smartTableGateway);

            return;
        }

        $app = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $this->application = $app;
        $userContainer = $app->getLocator()->get(IUserContainer::class);
        $userAccounts = $userContainer->get(IUserAccounts::class);
        $userGroups = $userContainer->get(IUserGroups::class);
        self::assertInstanceOf(IUserAccounts::class, $userAccounts);
        self::assertInstanceOf(IUserGroups::class, $userGroups);
        $this->userAccounts = $userAccounts;
        $this->userGroups = $userGroups;
        $kernelContainer = $app->getLocator()->get(IKernelContainer::class);
        $requestContext = $kernelContainer->get(IRequestContext::class);
        self::assertInstanceOf(IRequestContext::class, $requestContext);
        $this->requestContext = $requestContext;
        $smartTableContainer = $app->getLocator()->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        self::assertInstanceOf(ISmartTableGateway::class, $smartTableGateway);
        $this->smartTableGateway = $smartTableGateway;
        $this->userSchema = $this->makeUserSchema($smartTableGateway);
        $databaseConnection = $smartTableContainer->get(IDatabaseConnection::class);
        if (!$databaseConnection instanceof IlluminateDatabaseConnection) {
            throw new DbConfigInvalidException('test connection is not Illuminate');
        }

        $databaseConnection->ping();
    }

    /**
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     *
     * @return void
     */
    private function bindFacades(ISmartTableGateway $smartTableGateway): void
    {
        $userRecords = $smartTableGateway->open(UserTable::class)->records();
        $this->userAccounts = new UserAccounts(new UserRepository($userRecords));
        $this->userGroups = new UserGroups(
            new UserGroupRepository($smartTableGateway->open(UserGroupTable::class)->records()),
            new UserGroupMemberRepository($smartTableGateway->open(UserGroupMemberTable::class)->records()),
            new UserRepository($userRecords),
        );
        $this->userSchema = $this->makeUserSchema($smartTableGateway);
    }

    /**
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     *
     * @return UserSchema Схема.
     */
    private function makeUserSchema(ISmartTableGateway $smartTableGateway): UserSchema
    {
        return new UserSchema(
            $smartTableGateway->open(UserTable::class)->schema(),
            $smartTableGateway->open(UserGroupTable::class)->schema(),
            $smartTableGateway->open(UserGroupMemberTable::class)->schema(),
        );
    }

    /**
     * @return IUserAccounts Фасад.
     */
    private function userAccounts(): IUserAccounts
    {
        self::assertInstanceOf(IUserAccounts::class, $this->userAccounts);

        return $this->userAccounts;
    }

    /**
     * @return IUserGroups Фасад.
     */
    private function userGroups(): IUserGroups
    {
        self::assertInstanceOf(IUserGroups::class, $this->userGroups);

        return $this->userGroups;
    }

    /**
     * @return UserSchema Схема.
     */
    private function userSchema(): UserSchema
    {
        self::assertInstanceOf(UserSchema::class, $this->userSchema);

        return $this->userSchema;
    }

    /**
     * @return ISmartTableGateway Шлюз.
     */
    private function smartTableGateway(): ISmartTableGateway
    {
        self::assertInstanceOf(ISmartTableGateway::class, $this->smartTableGateway);

        return $this->smartTableGateway;
    }

    /**
     * @param array<string, mixed> $values Вход.
     *
     * @return NewUser DTO.
     */
    private function newUser(array $values): NewUser
    {
        return (new UserInputNormalizer())->newUser($values);
    }

    /**
     * @param array<string, mixed> $values Вход.
     *
     * @return NewGroup DTO.
     */
    private function newGroup(array $values): NewGroup
    {
        return (new GroupInputNormalizer())->newGroup($values);
    }

    /**
     * @return void
     */
    private function dropUserModuleTables(): void
    {
        if (!$this->smartTableGateway instanceof ISmartTableGateway) {
            return;
        }

        UserMysqlTables::drop($this->smartTableGateway);
    }

    /**
     * @param string $host Хост.
     *
     * @return DatabaseSettings Настройки.
     */
    private function settingsFromEnv(string $host): DatabaseSettings
    {
        $port = getenv('MIFRIAL_TEST_DB_PORT');
        $collation = getenv('MIFRIAL_TEST_DB_COLLATION');
        $timezone = getenv('MIFRIAL_TEST_DB_TIMEZONE');

        return DatabaseSettings::fromFields(
            $host,
            is_string($port) && ctype_digit($port) ? (int) $port : 3306,
            (string) getenv('MIFRIAL_TEST_DB_DATABASE'),
            (string) getenv('MIFRIAL_TEST_DB_USERNAME'),
            (string) getenv('MIFRIAL_TEST_DB_PASSWORD'),
            (string) getenv('MIFRIAL_TEST_DB_CHARSET'),
            false,
            is_string($collation) && $collation !== '' ? $collation : 'utf8mb4_unicode_ci',
            is_string($timezone) && $timezone !== '' ? $timezone : '+00:00',
        );
    }
}
