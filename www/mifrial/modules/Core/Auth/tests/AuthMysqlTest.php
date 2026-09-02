<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Tests;

use Mifrial\Core\Auth\Dto\AuthSettings;
use Mifrial\Core\Auth\Exception\AuthDuplicateException;
use Mifrial\Core\Auth\Exception\AuthInvalidException;
use Mifrial\Core\Auth\Exception\AuthPolicyException;
use Mifrial\Core\Auth\Interface\Container\IAuthContainer;
use Mifrial\Core\Auth\Repository\AuthSessionRepository;
use Mifrial\Core\Auth\Repository\UserIdentityRepository;
use Mifrial\Core\Auth\Schema\AuthSchema;
use Mifrial\Core\Auth\Service\AuthCookieIssuer;
use Mifrial\Core\Auth\Service\AuthService;
use Mifrial\Core\Auth\Service\AuthUserAssembler;
use Mifrial\Core\Auth\Setup\BootstrapGroupsStep;
use Mifrial\Core\Auth\Table\AuthSessionTable;
use Mifrial\Core\Auth\Table\UserIdentityTable;
use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Http\RequestContext;
use Mifrial\Core\Kernel\Interface\Http\IHttpRequest;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
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
use Mifrial\Core\User\Dto\NewGroup;
use Mifrial\Core\User\Dto\UserPatch;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Interface\Service\IUserGroups;
use Mifrial\Core\User\Repository\UserGroupMemberRepository;
use Mifrial\Core\User\Repository\UserGroupRepository;
use Mifrial\Core\User\Repository\UserRepository;
use Mifrial\Core\User\Schema\UserSchema;
use Mifrial\Core\User\Service\UserAccounts;
use Mifrial\Core\User\Service\UserGroups;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;
use PHPUnit\Framework\TestCase;

final class AuthMysqlTest extends TestCase
{
    private ?AuthService $authService = null;

    private ?IUserAccounts $userAccounts = null;

    private ?IUserGroups $userGroups = null;

    private ?IRequestContext $requestContext = null;

    private ?ISmartTableGateway $smartTableGateway = null;

    private ?UserIdentityRepository $identityRepository = null;

    private ?AuthSessionRepository $sessionRepository = null;

    /**
     * MySQL или skip; ставит схемы User и Auth.
     *
     * @return void
     */
    protected function setUp(): void
    {
        try {
            $this->connectAuth();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Auth tests');
        }

        $this->dropAuthAndUserTables();
        $this->installSchemas();
    }

    /**
     * Сносит таблицы Auth и User.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropAuthAndUserTables();
    }

    /**
     * Login, getCurrentUser, logout; плохой пароль — AUTH_INVALID.
     *
     * @return void
     */
    public function testLoginLogoutAndInvalidPassword(): void
    {
        $this->seedPlayerGroup();
        $this->createPasswordUser('alice', 'secret');
        $login = $this->authService()->login('alice', 'secret', false);
        self::assertSame('alice', $login['user']['login']);
        self::assertContains('Игрок', $login['user']['groups']);
        self::assertArrayHasKey('lastLogin', $login['user']);
        $rawToken = $this->takeSessionToken();
        $this->bindIncomingSession($rawToken);
        $current = $this->authService()->currentUser();
        self::assertIsArray($current);
        self::assertSame('alice', $current['login']);
        $this->authService()->logout();
        $this->bindIncomingSession($rawToken);
        self::assertNull($this->authService()->currentUser());
        try {
            $this->authService()->login('alice', 'wrong', false);
            self::fail('bad password must fail');
        } catch (AuthInvalidException $exception) {
            self::assertSame('AUTH_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Пустой идентификатор и неактивный — AUTH_INVALID.
     *
     * @return void
     */
    public function testEmptyIdentifierAndInactive(): void
    {
        try {
            $this->authService()->login('  ', 'x', false);
            self::fail('empty login must fail');
        } catch (AuthInvalidException $exception) {
            self::assertSame('AUTH_INVALID', $exception->getErrorCode());
        }

        $this->seedPlayerGroup();
        $userId = $this->createPasswordUser('bob', 'secret');
        $this->authService()->login('bob', 'secret', false);
        $rawToken = $this->takeSessionToken();
        $this->userAccounts()->update($userId, UserPatch::fromNormalized(['active' => false]));
        $this->bindIncomingSession($rawToken);
        self::assertNull($this->authService()->currentUser());
        try {
            $this->authService()->login('bob', 'secret', false);
            self::fail('inactive must fail');
        } catch (AuthInvalidException $exception) {
            self::assertSame('AUTH_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Register, политика, дубль, нет группы Игрок.
     *
     * @return void
     */
    public function testRegisterPolicyAndDuplicate(): void
    {
        try {
            $this->authService()->register('n', 'n@x.test', 'abc');
            self::fail('short password must fail');
        } catch (AuthPolicyException $exception) {
            self::assertSame('AUTH_POLICY', $exception->getErrorCode());
        }

        try {
            $this->authService()->register('n', 'n@x.test', 'abcd');
            self::fail('missing player group must fail');
        } catch (AuthInvalidException $exception) {
            self::assertSame('AUTH_INVALID', $exception->getErrorCode());
        }

        $this->seedPlayerGroup();
        $registered = $this->authService()->register('carol', 'c@x.test', 'abcd');
        self::assertSame('carol', $registered['user']['login']);
        self::assertSame('c@x.test', $registered['user']['email']);
        try {
            $this->authService()->register('carol', 'other@x.test', 'abcd');
            self::fail('duplicate login must fail');
        } catch (AuthDuplicateException $exception) {
            self::assertSame('AUTH_DUPLICATE', $exception->getErrorCode());
        }

        $withoutEmail = $this->authService()->register('erin', '  ', 'abcd');
        self::assertSame('', $withoutEmail['user']['email']);
    }

    /**
     * Вход по email и remember (TTL ~ 30 суток).
     *
     * @return void
     */
    public function testLoginByEmailAndRemember(): void
    {
        $this->seedPlayerGroup();
        $this->createPasswordUser('dave', 'secret', 'd@x.test');
        $login = $this->authService()->login('d@x.test', 'secret', true);
        self::assertSame('dave', $login['user']['login']);
        $rawToken = $this->takeSessionToken();
        $sessionRow = $this->sessionRepository()->findByTokenHash(hash('sha256', $rawToken));
        self::assertNotNull($sessionRow);
        $expiresAt = $sessionRow['expires_at'];
        self::assertInstanceOf(DateTime::class, $expiresAt);
        self::assertGreaterThan(time() + 29 * 86400, $expiresAt->toUnix());
    }

    /**
     * Порт AuthService в контейнере один на процесс.
     *
     * @return void
     */
    public function testContainerSharesAuthService(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $authContainer = $application->getLocator()->get(IAuthContainer::class);
        $first = $authContainer->get(AuthService::class);
        $second = $authContainer->get(AuthService::class);
        self::assertInstanceOf(AuthService::class, $first);
        self::assertSame($first, $second);
    }

    /**
     * Seed групп идемпотентен; оператор в двух группах.
     *
     * @return void
     */
    public function testBootstrapGroupsIdempotent(): void
    {
        $settings = AuthSettings::fromSection([
            'operator_login' => 'admin',
            'operator_password' => 'changeme',
            'operator_name' => 'Администратор',
        ]);
        $step = new BootstrapGroupsStep(
            $this->userAccounts(),
            $this->userGroups(),
            $this->identityRepository(),
            $settings,
        );
        $step->run();
        $step->run();
        $admin = $this->userGroups()->findByName('Администраторы');
        $player = $this->userGroups()->findByName('Игрок');
        self::assertNotNull($admin);
        self::assertTrue($admin->values()['bypass']);
        self::assertNotNull($player);
        $operator = $this->userAccounts()->findByLogin('admin');
        self::assertNotNull($operator);
        $operatorId = (int) $operator->values()['id'];
        self::assertNotNull($this->identityRepository()->findPassword($operatorId));
        $groupIds = $this->userGroups()->groupsOfUser($operatorId);
        sort($groupIds);
        $expected = [(int) $admin->values()['id'], (int) $player->values()['id']];
        sort($expected);
        self::assertSame($expected, $groupIds);
        self::assertTrue($this->userGroups()->hasBypass($operatorId));
        $this->authService()->login('admin', 'changeme', false);
    }

    /**
     * Политика пароля v1.
     *
     * @return void
     */
    public function testPasswordPolicy(): void
    {
        self::assertSame([
            'minLength' => 4,
            'requireMixedCase' => false,
            'requireDigit' => false,
            'requireSpecialChar' => false,
        ], $this->authService()->passwordPolicy());
    }

    /**
     * Соединение и фасады.
     *
     * @return void
     *
     * @throws DatabaseException Если MySQL недоступен.
     */
    private function connectAuth(): void
    {
        $envHost = getenv('MIFRIAL_TEST_DB_HOST');
        if (is_string($envHost) && $envHost !== '') {
            $databaseConnection = new IlluminateDatabaseConnection(
                new IlluminateConnectionFactory(),
                $this->settingsFromEnv($envHost),
            );
            $databaseConnection->ping();
            $this->bindOnGateway(GatewayHarness::make($databaseConnection));

            return;
        }

        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $userContainer = $application->getLocator()->get(IUserContainer::class);
        $userAccounts = $userContainer->get(IUserAccounts::class);
        $userGroups = $userContainer->get(IUserGroups::class);
        $smartTableContainer = $application->getLocator()->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        self::assertInstanceOf(IUserAccounts::class, $userAccounts);
        self::assertInstanceOf(IUserGroups::class, $userGroups);
        self::assertInstanceOf(ISmartTableGateway::class, $smartTableGateway);
        $this->userAccounts = $userAccounts;
        $this->userGroups = $userGroups;
        $this->smartTableGateway = $smartTableGateway;
        $this->bindAuthOnGateway($smartTableGateway, $userAccounts, $userGroups);
        $databaseConnection = $smartTableContainer->get(IDatabaseConnection::class);
        if (!$databaseConnection instanceof IlluminateDatabaseConnection) {
            throw new DbConfigInvalidException('test connection is not Illuminate');
        }

        $databaseConnection->ping();
    }

    /**
     * Фасады на шлюзе из env.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     *
     * @return void
     */
    private function bindOnGateway(ISmartTableGateway $smartTableGateway): void
    {
        $userRecords = $smartTableGateway->open(UserTable::class)->records();
        $userAccounts = new UserAccounts(new UserRepository($userRecords));
        $userGroups = new UserGroups(
            new UserGroupRepository($smartTableGateway->open(UserGroupTable::class)->records()),
            new UserGroupMemberRepository($smartTableGateway->open(UserGroupMemberTable::class)->records()),
            new UserRepository($userRecords),
        );
        $this->userAccounts = $userAccounts;
        $this->userGroups = $userGroups;
        $this->smartTableGateway = $smartTableGateway;
        $this->bindAuthOnGateway($smartTableGateway, $userAccounts, $userGroups);
    }

    /**
     * AuthService и identity на шлюзе.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     * @param IUserAccounts $userAccounts Учётки.
     * @param IUserGroups $userGroups Группы.
     *
     * @return void
     */
    private function bindAuthOnGateway(
        ISmartTableGateway $smartTableGateway,
        IUserAccounts $userAccounts,
        IUserGroups $userGroups,
    ): void {
        $requestContext = new RequestContext();
        $settings = AuthSettings::fromSection(['cookie_secure' => false]);
        $identityRepository = new UserIdentityRepository(
            $smartTableGateway->open(UserIdentityTable::class)->records(),
        );
        $sessionRepository = new AuthSessionRepository(
            $smartTableGateway->open(AuthSessionTable::class)->records(),
        );
        $this->requestContext = $requestContext;
        $this->identityRepository = $identityRepository;
        $this->sessionRepository = $sessionRepository;
        $this->authService = new AuthService(
            $userAccounts,
            $userGroups,
            $identityRepository,
            $sessionRepository,
            new AuthCookieIssuer($requestContext, $settings),
            new AuthUserAssembler($userGroups),
        );
    }

    /**
     * Ставит схемы User и Auth.
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
        (new AuthSchema(
            $gateway->open(UserIdentityTable::class)->schema(),
            $gateway->open(AuthSessionTable::class)->schema(),
        ))->install();
    }

    /**
     * Группа «Игрок».
     *
     * @return void
     */
    private function seedPlayerGroup(): void
    {
        $this->userGroups()->add(NewGroup::fromNormalized([
            'name' => 'Игрок',
            'active' => true,
            'bypass' => false,
            'permissions' => [],
        ]));
    }

    /**
     * Учётка с password-identity и членством в «Игрок».
     *
     * @param string $login Логин.
     * @param string $password Пароль.
     * @param string|null $email Почта или нет.
     *
     * @return int Id.
     */
    private function createPasswordUser(string $login, string $password, ?string $email = null): int
    {
        $profile = [
            'login' => $login,
            'name' => $login,
            'active' => true,
        ];
        if (is_string($email)) {
            $profile['email'] = $email;
        }

        $userId = $this->userAccounts()->addFromInput($profile);
        $this->identityRepository()->addPassword($userId, password_hash($password, PASSWORD_DEFAULT));
        $player = $this->userGroups()->findByName('Игрок');
        self::assertNotNull($player);
        $this->userGroups()->addMember($userId, (int) $player->values()['id']);

        return $userId;
    }

    /**
     * Забирает сырой токен сессии из очереди.
     *
     * @return string Токен.
     */
    private function takeSessionToken(): string
    {
        foreach ($this->requestContext()->takeQueuedCookies() as $outgoingCookie) {
            if ($outgoingCookie->name() === 'mifrial-session' && $outgoingCookie->value() !== '') {
                return $outgoingCookie->value();
            }
        }

        self::fail('session cookie missing');
    }

    /**
     * Кладёт сессионную cookie во входящий контекст.
     *
     * @param string $rawToken Сырой токен.
     *
     * @return void
     */
    private function bindIncomingSession(string $rawToken): void
    {
        $httpRequest = $this->createStub(IHttpRequest::class);
        $httpRequest->method('getCookieMap')->willReturn(['mifrial-session' => $rawToken]);
        $this->requestContext()->bindIncoming($httpRequest);
    }

    /**
     * Сносит таблицы Auth, затем User.
     *
     * @return void
     */
    private function dropAuthAndUserTables(): void
    {
        if (!$this->smartTableGateway instanceof ISmartTableGateway) {
            return;
        }

        $tableClasses = [
            AuthSessionTable::class,
            UserIdentityTable::class,
            UserGroupMemberTable::class,
            UserGroupTable::class,
            UserTable::class,
        ];
        foreach ($tableClasses as $tableClass) {
            $openedTable = $this->smartTableGateway->open($tableClass);
            if ($openedTable->schema()->exists()) {
                $openedTable->schema()->deleteTable();
            }
        }
    }

    /**
     * @return AuthService Сценарий.
     */
    private function authService(): AuthService
    {
        self::assertInstanceOf(AuthService::class, $this->authService);

        return $this->authService;
    }

    /**
     * @return IUserAccounts Учётки.
     */
    private function userAccounts(): IUserAccounts
    {
        self::assertInstanceOf(IUserAccounts::class, $this->userAccounts);

        return $this->userAccounts;
    }

    /**
     * @return IUserGroups Группы.
     */
    private function userGroups(): IUserGroups
    {
        self::assertInstanceOf(IUserGroups::class, $this->userGroups);

        return $this->userGroups;
    }

    /**
     * @return IRequestContext Контекст.
     */
    private function requestContext(): IRequestContext
    {
        self::assertInstanceOf(IRequestContext::class, $this->requestContext);

        return $this->requestContext;
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
     * @return UserIdentityRepository Identity.
     */
    private function identityRepository(): UserIdentityRepository
    {
        self::assertInstanceOf(UserIdentityRepository::class, $this->identityRepository);

        return $this->identityRepository;
    }

    /**
     * @return AuthSessionRepository Сессии.
     */
    private function sessionRepository(): AuthSessionRepository
    {
        self::assertInstanceOf(AuthSessionRepository::class, $this->sessionRepository);

        return $this->sessionRepository;
    }

    /**
     * Настройки из env.
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
