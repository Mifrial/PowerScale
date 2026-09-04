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
use Mifrial\Core\User\Exception\UserDuplicateException;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Interface\Service\IUserGroups;
use Mifrial\Core\User\Repository\UserGroupMemberRepository;
use Mifrial\Core\User\Repository\UserGroupRepository;
use Mifrial\Core\User\Repository\UserRepository;
use Mifrial\Core\User\Service\GroupInputNormalizer;
use Mifrial\Core\User\Service\UserAccounts;
use Mifrial\Core\User\Service\UserGroups;
use Mifrial\Core\User\Service\UserInputNormalizer;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;
use Mifrial\Core\User\Tests\Fixture\UserGroupMemberLegacyTable;
use PHPUnit\Framework\TestCase;

final class UserMemberUniqueMysqlTest extends TestCase
{
    private ?IUserAccounts $userAccounts = null;

    private ?IUserGroups $userGroups = null;

    private ?ISmartTableGateway $smartTableGateway = null;

    private ?IlluminateDatabaseConnection $databaseConnection = null;

    /**
     * Подключается к MySQL или skip.
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
    }

    /**
     * Сносит таблицы модуля.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropUserModuleTables();
    }

    /**
     * force снимает leftover member_key и ставит составной unique.
     *
     * @return void
     */
    public function testForceDropsMemberKeyAndKeepsPairUnique(): void
    {
        $gateway = $this->smartTableGateway();
        $gateway->open(UserTable::class)->schema()->createTable();
        $gateway->open(UserGroupTable::class)->schema()->createTable();
        $gateway->open(UserGroupMemberLegacyTable::class)->schema()->createTable();
        $this->bindFacades($gateway);
        [$userId, $groupId] = $this->seedLegacyMembership($gateway);
        $gateway->open(UserGroupMemberTable::class)->schema()->forceUpdateTable();
        $columns = $this->databaseConnection()
            ->illuminateConnection()
            ->getSchemaBuilder()
            ->getColumnListing('user_group_member');
        $indexNames = $this->indexNames();
        self::assertNotContains('member_key', $columns);
        self::assertNotContains('user_group_member_member_key_unq', $indexNames);
        self::assertContains('user_group_member_user_id_group_id_unq', $indexNames);
        try {
            $this->userGroups()->addMember($userId, $groupId);
            self::fail('duplicate pair after force');
        } catch (UserDuplicateException $exception) {
            self::assertSame('USER_DUPLICATE', $exception->getErrorCode());
        }
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
            $this->databaseConnection = new IlluminateDatabaseConnection(
                new IlluminateConnectionFactory(),
                $this->settingsFromEnv($envHost),
            );
            $this->databaseConnection->ping();
            $this->smartTableGateway = GatewayHarness::make($this->databaseConnection);

            return;
        }

        $app = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $userContainer = $app->getLocator()->get(IUserContainer::class);
        $this->userAccounts = $userContainer->get(IUserAccounts::class);
        $this->userGroups = $userContainer->get(IUserGroups::class);
        $smartTableContainer = $app->getLocator()->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        self::assertInstanceOf(ISmartTableGateway::class, $smartTableGateway);
        $this->smartTableGateway = $smartTableGateway;
        $databaseConnection = $smartTableContainer->get(IDatabaseConnection::class);
        if (!$databaseConnection instanceof IlluminateDatabaseConnection) {
            throw new DbConfigInvalidException('test connection is not Illuminate');
        }

        $databaseConnection->ping();
        $this->databaseConnection = $databaseConnection;
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
    }

    /**
     * Учётка, группа и строка member_key на legacy-карте.
     *
     * @param ISmartTableGateway $gateway Шлюз.
     *
     * @return array{0: int, 1: int} user id и group id.
     */
    private function seedLegacyMembership(ISmartTableGateway $gateway): array
    {
        $userId = $this->userAccounts()->add(
            (new UserInputNormalizer())->newUser(['login' => 'alice', 'name' => 'Alice']),
        );
        $groupId = $this->userGroups()->add((new GroupInputNormalizer())->newGroup(['name' => 'G']));
        $gateway->open(UserGroupMemberLegacyTable::class)->records()->add([
            'user_id' => $userId,
            'group_id' => $groupId,
            'member_key' => $userId . ':' . $groupId,
        ]);

        return [$userId, $groupId];
    }

    /**
     * Имена индексов членства.
     *
     * @return array<int, string> Имена.
     */
    private function indexNames(): array
    {
        $indexes = $this->databaseConnection()
            ->illuminateConnection()
            ->getSchemaBuilder()
            ->getIndexes('user_group_member');
        $indexNames = [];
        foreach ($indexes as $index) {
            if (isset($index['name']) && is_string($index['name'])) {
                $indexNames[] = $index['name'];
            }
        }

        return $indexNames;
    }

    /**
     * Фасад учётки.
     *
     * @return IUserAccounts Фасад.
     */
    private function userAccounts(): IUserAccounts
    {
        self::assertInstanceOf(IUserAccounts::class, $this->userAccounts);

        return $this->userAccounts;
    }

    /**
     * Фасад групп.
     *
     * @return IUserGroups Фасад.
     */
    private function userGroups(): IUserGroups
    {
        self::assertInstanceOf(IUserGroups::class, $this->userGroups);

        return $this->userGroups;
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

    /**
     * Соединение.
     *
     * @return IlluminateDatabaseConnection Адаптер.
     */
    private function databaseConnection(): IlluminateDatabaseConnection
    {
        self::assertInstanceOf(IlluminateDatabaseConnection::class, $this->databaseConnection);

        return $this->databaseConnection;
    }

    /**
     * Сносит таблицы User.
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
     * Настройки из env теста.
     *
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
