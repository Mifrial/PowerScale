<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Tests;

use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Tests\GatewayHarness;
use Mifrial\Core\User\Dto\NewGroup;
use Mifrial\Core\User\Dto\NewUser;
use Mifrial\Core\User\Exception\UserDuplicateException;
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
use Mifrial\Core\User\Service\GroupInputNormalizer;
use Mifrial\Core\User\Service\UserAccounts;
use Mifrial\Core\User\Service\UserGroups;
use Mifrial\Core\User\Service\UserInputNormalizer;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;
use PHPUnit\Framework\TestCase;

final class UserGroupsMysqlTest extends TestCase
{
    private ?IUserAccounts $userAccounts = null;

    private ?IUserGroups $userGroups = null;

    private ?UserSchema $userSchema = null;

    private ?ISmartTableGateway $smartTableGateway = null;

    /**
     * Подключается к MySQL или skip; сносит таблицы модуля User.
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
     * Удаляет таблицы модуля User.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropUserModuleTables();
    }

    /**
     * Две группы, сумма ключей, неактивная не даёт права и bypass.
     *
     * @return void
     */
    public function testPermissionUnionAndInactiveGroup(): void
    {
        $userGroups = $this->userGroups();
        $userId = $this->userAccounts()->add($this->newUser(['login' => 'alice', 'name' => 'Alice']));
        $firstId = $userGroups->add($this->newGroup([
            'name' => 'One',
            'permissions' => ['user.view', 'user.edit'],
        ]));
        $secondId = $userGroups->add($this->newGroup([
            'name' => 'Two',
            'permissions' => ['user.edit', 'chat.message'],
        ]));
        $userGroups->addMember($userId, $firstId);
        $userGroups->addMember($userId, $secondId);
        $keys = $userGroups->getPermissionKeys($userId);
        sort($keys);
        self::assertSame(['chat.message', 'user.edit', 'user.view'], $keys);
        $userGroups->update($secondId, (new GroupInputNormalizer())->patch(['active' => false]));
        $after = $userGroups->getPermissionKeys($userId);
        sort($after);
        self::assertSame(['user.edit', 'user.view'], $after);
        self::assertContains($userId, $userGroups->getMemberIds($secondId));
        self::assertFalse($userGroups->hasBypass($userId));
        $byIds = $userGroups->getByIds([$secondId, $firstId, $firstId]);
        self::assertCount(2, $byIds);
        self::assertSame('One', $byIds[$firstId]->getName());
        $listedGroups = $userGroups->findPage(500, 0, null, null)->getRecords();
        self::assertCount(2, $listedGroups);
        self::assertSame($firstId, $listedGroups[0]->getId());
        $userGroups->replaceMembership($userId, [$firstId, $firstId]);
        self::assertSame([$firstId], $userGroups->getGroupIdsOfUser($userId));
    }

    /**
     * Страница членов: порядок id членства, bounds, нет группы.
     *
     * @return void
     */
    public function testFindMemberPage(): void
    {
        $userGroups = $this->userGroups();
        $firstId = $this->userAccounts()->add($this->newUser(['login' => 'aa', 'name' => 'Aa']));
        $secondId = $this->userAccounts()->add($this->newUser(['login' => 'bb', 'name' => 'Bb']));
        $groupId = $userGroups->add($this->newGroup(['name' => 'Crew']));
        $emptyId = $userGroups->add($this->newGroup(['name' => 'Empty']));
        $userGroups->addMember($firstId, $groupId);
        $userGroups->addMember($secondId, $groupId);
        $firstPage = $userGroups->findMemberPage($groupId, 1, 0);
        self::assertSame([$firstId], $firstPage->getIds());
        self::assertSame(2, $firstPage->getTotal());
        $secondPage = $userGroups->findMemberPage($groupId, 1, 1);
        self::assertSame([$secondId], $secondPage->getIds());
        self::assertSame(2, $secondPage->getTotal());
        $emptyPage = $userGroups->findMemberPage($emptyId, 10, 0);
        self::assertSame([], $emptyPage->getIds());
        self::assertSame(0, $emptyPage->getTotal());
        try {
            $userGroups->findMemberPage(9_999_999, 10, 0);
            self::fail('missing group');
        } catch (UserNotFoundException $exception) {
            self::assertSame('USER_NOT_FOUND', $exception->getErrorCode());
        }

        try {
            $userGroups->findMemberPage($groupId, 0, 0);
            self::fail('limit 0');
        } catch (UserInvalidException $exception) {
            self::assertSame('USER_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Дубль имени и дубль членства.
     *
     * @return void
     */
    public function testDuplicateNameAndMember(): void
    {
        $userGroups = $this->userGroups();
        $userId = $this->userAccounts()->add($this->newUser(['login' => 'alice', 'name' => 'Alice']));
        $groupId = $userGroups->add($this->newGroup(['name' => 'G']));
        try {
            $userGroups->add($this->newGroup(['name' => 'G']));
            self::fail('duplicate name must fail');
        } catch (UserDuplicateException $exception) {
            self::assertSame('USER_DUPLICATE', $exception->getErrorCode());
        }

        $userGroups->addMember($userId, $groupId);
        try {
            $userGroups->addMember($userId, $groupId);
            self::fail('duplicate member must fail');
        } catch (UserDuplicateException $exception) {
            self::assertSame('USER_DUPLICATE', $exception->getErrorCode());
        }
    }

    /**
     * Поиск группы по имени, trim и пустая строка.
     *
     * @return void
     */
    public function testFindByName(): void
    {
        $userGroups = $this->userGroups();
        $groupId = $userGroups->add($this->newGroup(['name' => 'Игрок']));

        self::assertSame($groupId, $userGroups->findByName(' Игрок ')?->getId());
        self::assertNull($userGroups->findByName('missing'));
        self::assertNull($userGroups->findByName('  '));
    }

    /**
     * Автовыдача: default false, флаг даёт id.
     *
     * @return void
     */
    public function testAssignOnRegisterIds(): void
    {
        $userGroups = $this->userGroups();
        $userGroups->add($this->newGroup(['name' => 'Игрок']));
        self::assertSame([], $userGroups->getAssignOnRegisterIds());
        $guestId = $userGroups->add($this->newGroup([
            'name' => 'Гость',
            'assign_on_register' => true,
        ]));
        self::assertSame([$guestId], $userGroups->getAssignOnRegisterIds());
        $userGroups->update($guestId, (new GroupInputNormalizer())->patch(['assign_on_register' => false]));
        self::assertSame([], $userGroups->getAssignOnRegisterIds());
    }

    /**
     * Последний bypass-член и выключение единственной живой bypass-группы.
     *
     * @return void
     */
    public function testLastBypassProtectedThenSecondMemberFreesFirst(): void
    {
        $userGroups = $this->userGroups();
        $firstUser = $this->userAccounts()->add($this->newUser(['login' => 'alice', 'name' => 'Alice']));
        $secondUser = $this->userAccounts()->add($this->newUser(['login' => 'bob', 'name' => 'Bob']));
        $groupId = $userGroups->add($this->newGroup(['name' => 'Admins', 'bypass' => true]));
        $userGroups->addMember($firstUser, $groupId);
        self::assertTrue($userGroups->hasBypass($firstUser));
        try {
            $userGroups->removeMember($firstUser, $groupId);
            self::fail('last bypass member must fail');
        } catch (UserLastBypassException $exception) {
            self::assertSame('USER_LAST_BYPASS', $exception->getErrorCode());
        }

        self::assertSame([$firstUser], $userGroups->getMemberIds($groupId));
        try {
            $userGroups->update($groupId, (new GroupInputNormalizer())->patch(['bypass' => false]));
            self::fail('clear last bypass flag must fail');
        } catch (UserLastBypassException $exception) {
            self::assertSame('USER_LAST_BYPASS', $exception->getErrorCode());
        }

        self::assertTrue($userGroups->getById($groupId)->isBypass());
        try {
            $userGroups->update($groupId, (new GroupInputNormalizer())->patch(['active' => false]));
            self::fail('deactivate last bypass group must fail');
        } catch (UserLastBypassException $exception) {
            self::assertSame('USER_LAST_BYPASS', $exception->getErrorCode());
        }

        $userGroups->addMember($secondUser, $groupId);
        $userGroups->removeMember($firstUser, $groupId);
        self::assertSame([$secondUser], $userGroups->getMemberIds($groupId));
        self::assertTrue($userGroups->hasBypass($secondUser));
        self::assertFalse($userGroups->hasBypass($firstUser));
    }

    /**
     * Одна bypass-группа; пустые permissions всё равно обходят ACL.
     *
     * @return void
     */
    public function testSingleBypassSlotAndEmptyPermissions(): void
    {
        $userGroups = $this->userGroups();
        $userId = $this->userAccounts()->add($this->newUser(['login' => 'alice', 'name' => 'Alice']));
        $adminId = $userGroups->add($this->newGroup(['name' => 'Admins', 'bypass' => true]));
        try {
            $userGroups->add($this->newGroup(['name' => 'Shadow', 'bypass' => true]));
            self::fail('second bypass group must fail');
        } catch (UserInvalidException $exception) {
            self::assertSame('USER_INVALID', $exception->getErrorCode());
        }

        $otherId = $userGroups->add($this->newGroup(['name' => 'Other']));
        try {
            $userGroups->update($otherId, (new GroupInputNormalizer())->patch(['bypass' => true]));
            self::fail('steal bypass flag must fail');
        } catch (UserInvalidException $exception) {
            self::assertSame('USER_INVALID', $exception->getErrorCode());
        }

        $userGroups->update($adminId, (new GroupInputNormalizer())->patch(['bypass' => false]));
        $userGroups->update($otherId, (new GroupInputNormalizer())->patch(['bypass' => true]));
        $userGroups->addMember($userId, $otherId);
        self::assertTrue($userGroups->hasBypass($userId));
        self::assertSame([], $userGroups->getPermissionKeys($userId));
    }

    /**
     * install() на существующем user создаёт таблицы групп.
     *
     * @return void
     */
    public function testInstallCreatesGroupTablesWhenUserExists(): void
    {
        $this->dropUserModuleTables();
        $this->smartTableGateway()->open(UserTable::class)->schema()->createTable();
        self::assertFalse($this->smartTableGateway()->open(UserGroupTable::class)->schema()->exists());
        $this->userSchema()->install();
        self::assertTrue($this->smartTableGateway()->open(UserGroupTable::class)->schema()->exists());
        self::assertTrue($this->smartTableGateway()->open(UserGroupMemberTable::class)->schema()->exists());
    }

    /**
     * Собирает фасады из env или boot.
     *
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
        $userContainer = $app->getLocator()->get(IUserContainer::class);
        $userAccounts = $userContainer->get(IUserAccounts::class);
        $userGroups = $userContainer->get(IUserGroups::class);
        self::assertInstanceOf(IUserAccounts::class, $userAccounts);
        self::assertInstanceOf(IUserGroups::class, $userGroups);
        $this->userAccounts = $userAccounts;
        $this->userGroups = $userGroups;
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
     * Собирает фасады на шлюзе.
     *
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
     * Собирает установщик трёх карт.
     *
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
     * Возвращает фасад учётки после setUp.
     *
     * @return IUserAccounts Фасад учётки.
     */
    private function userAccounts(): IUserAccounts
    {
        self::assertInstanceOf(IUserAccounts::class, $this->userAccounts);

        return $this->userAccounts;
    }

    /**
     * Возвращает фасад групп после setUp.
     *
     * @return IUserGroups Фасад групп.
     */
    private function userGroups(): IUserGroups
    {
        self::assertInstanceOf(IUserGroups::class, $this->userGroups);

        return $this->userGroups;
    }

    /**
     * Возвращает установщик схемы после setUp.
     *
     * @return UserSchema Схема.
     */
    private function userSchema(): UserSchema
    {
        self::assertInstanceOf(UserSchema::class, $this->userSchema);

        return $this->userSchema;
    }

    /**
     * Возвращает шлюз после setUp.
     *
     * @return ISmartTableGateway Шлюз.
     */
    private function smartTableGateway(): ISmartTableGateway
    {
        self::assertInstanceOf(ISmartTableGateway::class, $this->smartTableGateway);

        return $this->smartTableGateway;
    }

    /**
     * Собирает NewUser через нормализатор.
     *
     * @param array<string, mixed> $values Вход.
     *
     * @return NewUser DTO.
     */
    private function newUser(array $values): NewUser
    {
        return (new UserInputNormalizer())->newUser($values);
    }

    /**
     * Собирает NewGroup через нормализатор.
     *
     * @param array<string, mixed> $values Вход.
     *
     * @return NewGroup DTO.
     */
    private function newGroup(array $values): NewGroup
    {
        return (new GroupInputNormalizer())->newGroup($values);
    }

    /**
     * Удаляет таблицы модуля User, если есть.
     *
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
     * Собирает настройки из переменных окружения теста.
     *
     * @param string $host Хост из MIFRIAL_TEST_DB_HOST.
     *
     * @return DatabaseSettings Настройки тестовой БД.
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
