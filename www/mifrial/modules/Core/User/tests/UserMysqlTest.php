<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Tests;

use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Tests\GatewayHarness;
use Mifrial\Core\User\Dto\NewUser;
use Mifrial\Core\User\Dto\UserPatch;
use Mifrial\Core\User\Dto\UserRecord;
use Mifrial\Core\User\Exception\UserDuplicateException;
use Mifrial\Core\User\Exception\UserNotFoundException;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Repository\UserRepository;
use Mifrial\Core\User\Schema\UserSchema;
use Mifrial\Core\User\Service\UserAccounts;
use Mifrial\Core\User\Service\UserInputNormalizer;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;
use PHPUnit\Framework\TestCase;

final class UserMysqlTest extends TestCase
{
    private ?IUserAccounts $userAccounts = null;

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
     * Удаляет таблицу `user`.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropUserModuleTables();
    }

    /**
     * Две учётки без email; повторный install; miss find.
     *
     * @return void
     */
    public function testTwoUsersWithoutEmailAndReinstall(): void
    {
        $userAccounts = $this->userAccounts();
        $firstId = $userAccounts->add($this->newUser(['login' => 'alice', 'name' => 'Alice']));
        $secondId = $userAccounts->add($this->newUser(['login' => 'bob', 'name' => 'Bob']));
        $this->userSchema()->install();
        $alice = $userAccounts->getById($firstId);
        $bob = $userAccounts->getById($secondId);
        self::assertSame('alice', $alice->getLogin());
        self::assertNull($alice->getEmail());
        self::assertSame('bob', $bob->getLogin());
        self::assertNull($userAccounts->findByLogin('carol'));
        $listed = $userAccounts->findPage(500, 0, null, null)->getRecords();
        self::assertCount(2, $listed);
        self::assertSame($firstId, $listed[0]->getId());
        self::assertSame([], $userAccounts->getByIds([]));
        $byIds = $userAccounts->getByIds([$secondId, $firstId, $secondId]);
        self::assertSame([$secondId, $firstId], array_map(
            static fn (UserRecord $userRecord): int => $userRecord->getId(),
            $byIds,
        ));
        self::assertInstanceOf(DateTime::class, $alice->getRegisteredAt());
        $registeredUnix = $alice->getRegisteredAt()->toUnix();
        self::assertGreaterThanOrEqual(time() - 5, $registeredUnix);
        self::assertLessThanOrEqual(time(), $registeredUnix);
    }

    /**
     * Дубль login; два null email ок; дубль email.
     *
     * @return void
     */
    public function testDuplicates(): void
    {
        $userAccounts = $this->userAccounts();
        $userAccounts->add($this->newUser(['login' => 'alice', 'name' => 'Alice', 'email' => 'a@x.test']));
        $userAccounts->add($this->newUser(['login' => 'bob', 'name' => 'Bob']));
        try {
            $userAccounts->add($this->newUser(['login' => 'alice', 'name' => 'Other']));
            self::fail('duplicate login must fail');
        } catch (UserDuplicateException $exception) {
            self::assertSame('USER_DUPLICATE', $exception->getErrorCode());
        }

        try {
            $userAccounts->add($this->newUser(['login' => 'carol', 'name' => 'Carol', 'email' => 'a@x.test']));
            self::fail('duplicate email must fail');
        } catch (UserDuplicateException $exception) {
            self::assertSame('USER_DUPLICATE', $exception->getErrorCode());
        }
    }

    /**
     * Пустой email как null; getById; findByLogin.
     *
     * @return void
     */
    public function testEmptyEmailNullAndGetById(): void
    {
        $userAccounts = $this->userAccounts();
        $userId = $userAccounts->add($this->newUser([
            'login' => 'alice',
            'name' => 'Alice',
            'email' => '',
        ]));
        $record = $userAccounts->getById($userId);
        self::assertInstanceOf(UserRecord::class, $record);
        self::assertNull($record->getEmail());
        self::assertSame($userId, $userAccounts->findByLogin(' alice ')?->getId());
        self::assertNull($userAccounts->findByEmail('  '));
        $userAccounts->update($userId, $this->userPatch([
            'nickname' => ' Ali ',
            'email' => 'a@x.test',
        ]));
        self::assertSame('Ali', $userAccounts->getById($userId)->getNickname());
        self::assertSame($userId, $userAccounts->findByEmail(' a@x.test ')?->getId());
        try {
            $userAccounts->getById(999999);
            self::fail('missing id must fail');
        } catch (UserNotFoundException $exception) {
            self::assertSame('USER_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * Собирает фасад из env или boot.
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
            $this->bindUserModule($this->smartTableGateway);

            return;
        }

        $app = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $userContainer = $app->getLocator()->get(IUserContainer::class);
        $userAccounts = $userContainer->get(IUserAccounts::class);
        self::assertInstanceOf(IUserAccounts::class, $userAccounts);
        $this->userAccounts = $userAccounts;
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
     * Возвращает фасад после setUp.
     *
     * @return IUserAccounts Фасад учётки.
     */
    private function userAccounts(): IUserAccounts
    {
        self::assertInstanceOf(IUserAccounts::class, $this->userAccounts);

        return $this->userAccounts;
    }

    /**
     * Возвращает установщик схемы после setUp.
     *
     * @return UserSchema Схема `user`.
     */
    private function userSchema(): UserSchema
    {
        self::assertInstanceOf(UserSchema::class, $this->userSchema);

        return $this->userSchema;
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
     * Собирает UserPatch через нормализатор.
     *
     * @param array<string, mixed> $values Вход.
     *
     * @return UserPatch DTO.
     */
    private function userPatch(array $values): UserPatch
    {
        return (new UserInputNormalizer())->patch($values);
    }

    /**
     * Собирает фасад учётки и схему модуля.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     *
     * @return void
     */
    private function bindUserModule(ISmartTableGateway $smartTableGateway): void
    {
        $userOpened = $smartTableGateway->open(UserTable::class);
        $this->userAccounts = new UserAccounts(new UserRepository($userOpened->records()));
        $this->userSchema = $this->makeUserSchema($smartTableGateway);
    }

    /**
     * Собирает установщик трёх карт.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     *
     * @return UserSchema Схема модуля.
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
