<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Tests;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassTooLong
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassComplexityTooHigh

use Closure;
use Mifrial\Core\Auth\Dto\Action\UserCreateInput;
use Mifrial\Core\Auth\Dto\AuthSettings;
use Mifrial\Core\Auth\Exception\AuthInvalidException;
use Mifrial\Core\Auth\Repository\AuthSessionRepository;
use Mifrial\Core\Auth\Repository\GroupSecurityPolicyRepository;
use Mifrial\Core\Auth\Repository\PasswordPolicyRepository;
use Mifrial\Core\Auth\Repository\PasswordResetRepository;
use Mifrial\Core\Auth\Repository\UserIdentityRepository;
use Mifrial\Core\Auth\Schema\AuthSchema;
use Mifrial\Core\Auth\Service\AuthCookieIssuer;
use Mifrial\Core\Auth\Service\AuthService;
use Mifrial\Core\Auth\Service\AuthSessionRuntime;
use Mifrial\Core\Auth\Service\MailPasswordResetNotifier;
use Mifrial\Core\Auth\Service\PasswordPolicyService;
use Mifrial\Core\Auth\Service\PasswordResetService;
use Mifrial\Core\Auth\Service\SetPasswordService;
use Mifrial\Core\Auth\Service\UserCreateService;
use Mifrial\Core\Auth\Table\AuthGroupSecurityPolicyTable;
use Mifrial\Core\Auth\Table\AuthPasswordResetTable;
use Mifrial\Core\Auth\Table\AuthSecurityPolicyTable;
use Mifrial\Core\Auth\Table\AuthSessionTable;
use Mifrial\Core\Auth\Table\UserIdentityTable;
use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Http\RequestContext;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\Mail\Dto\MailSettings;
use Mifrial\Core\Mail\Interface\Service\IMail;
use Mifrial\Core\Mail\Repository\MailEventRepository;
use Mifrial\Core\Mail\Repository\MailJobRepository;
use Mifrial\Core\Mail\Repository\MailTemplateRepository;
use Mifrial\Core\Mail\Schema\MailSchema;
use Mifrial\Core\Mail\Service\MailFlushService;
use Mifrial\Core\Mail\Service\MailService;
use Mifrial\Core\Mail\Service\PlaceholderRenderer;
use Mifrial\Core\Mail\Table\MailEventTable;
use Mifrial\Core\Mail\Table\MailJobTable;
use Mifrial\Core\Mail\Table\MailTemplateTable;
use Mifrial\Core\Mail\Tests\RecordingLogger;
use Mifrial\Core\Mail\Tests\RecordingMailTransport;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Service\SmartTableTransactionRunner;
use Mifrial\Core\SmartTable\Tests\GatewayHarness;
use Mifrial\Core\User\Dto\NewGroup;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Interface\Service\IUserGroups;
use Mifrial\Core\User\Repository\UserGroupMemberRepository;
use Mifrial\Core\User\Repository\UserGroupRepository;
use Mifrial\Core\User\Repository\UserRepository;
use Mifrial\Core\User\Schema\UserSchema;
use Mifrial\Core\User\Service\UserAccess;
use Mifrial\Core\User\Service\UserAccounts;
use Mifrial\Core\User\Service\UserGroups;
use Mifrial\Core\User\Service\UserViewAssembler;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Откат составных Auth workflow на одной транзакции шлюза.
 */
final class AuthWorkflowAtomicityMysqlTest extends TestCase
{
    private ?ISmartTableGateway $smartTableGateway = null;

    private ?IUserAccounts $userAccounts = null;

    private ?IUserGroups $userGroups = null;

    private ?RequestContext $requestContext = null;

    private ?PasswordPolicyService $passwordPolicyService = null;

    /**
     * MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        try {
            $this->connect();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Auth tests');
        }

        $this->dropTables();
        $this->installSchemas();
        $this->passwordPolicy()->ensureDefaults();
        $this->seedPlayerGroup();
    }

    /**
     * Сносит таблицы сценария.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropTables();
    }

    /**
     * Сбой identity откатывает user.
     *
     * @return void
     */
    public function testRegisterRollsBackUserWhenIdentityAddFails(): void
    {
        $authService = $this->authService($this->failing(UserIdentityTable::class, 'add', 1), null, null);
        $this->expectWriteFailure(function () use ($authService): void {
            $authService->register('carol', 'c@x.test', 'abcd');
        });
        self::assertNull($this->userAccounts()->findByLogin('carol'));
        self::assertSame([], $this->requestContext()->takeQueuedCookies());
    }

    /**
     * Сбой membership откатывает user и identity.
     *
     * @return void
     */
    public function testRegisterRollsBackUserAndIdentityWhenMembershipFails(): void
    {
        $authService = $this->authService(null, $this->failing(UserGroupMemberTable::class, 'add', 1), null);
        $this->expectWriteFailure(function () use ($authService): void {
            $authService->register('carol', 'c@x.test', 'abcd');
        });
        self::assertNull($this->userAccounts()->findByLogin('carol'));
        self::assertSame([], $this->rows(UserIdentityTable::class));
    }

    /**
     * Сбой второй membership откатывает уже добавленную.
     *
     * @return void
     */
    public function testRegisterRollsBackFirstMembershipWhenSecondFails(): void
    {
        $this->userGroups()->add(NewGroup::fromNormalized([
            'name' => 'Вторая',
            'active' => true,
            'bypass' => false,
            'assign_on_register' => true,
            'permissions' => [],
        ]));
        $authService = $this->authService(null, $this->failing(UserGroupMemberTable::class, 'add', 2), null);
        $this->expectWriteFailure(function () use ($authService): void {
            $authService->register('carol', 'c@x.test', 'abcd');
        });
        self::assertSame([], $this->rows(UserGroupMemberTable::class));
        self::assertNull($this->userAccounts()->findByLogin('carol'));
    }

    /**
     * Сбой insert сессии откатывает профиль.
     *
     * @return void
     */
    public function testRegisterRollsBackProfileWhenSessionInsertFails(): void
    {
        $authService = $this->authService(null, null, $this->failing(AuthSessionTable::class, 'add', 1));
        $this->expectWriteFailure(function () use ($authService): void {
            $authService->register('carol', 'c@x.test', 'abcd');
        });
        self::assertNull($this->userAccounts()->findByLogin('carol'));
        self::assertSame([], $this->rows(AuthSessionTable::class));
        self::assertSame([], $this->requestContext()->takeQueuedCookies());
    }

    /**
     * Отказ bypass до insert не создаёт user.
     *
     * @return void
     */
    public function testCreateRejectsBypassBeforeInsert(): void
    {
        $actorId = $this->createUser('admin');
        $this->requestContext()->setActor(new RequestActor($actorId, ['user.create'], false));
        $plainId = $this->addGroup('Обычная', false);
        $bypassId = $this->addGroup('Обход', true);
        try {
            $this->userCreateService(null)->create(new UserCreateInput('Bob', 'bob', 'abcd', [$plainId, $bypassId]));
            self::fail('bypass');
        } catch (ActionException $exception) {
            self::assertSame('AUTH_DENIED', $exception->getErrorCode());
        }

        self::assertNull($this->userAccounts()->findByLogin('bob'));
    }

    /**
     * Сбой второго членства откатывает create целиком.
     *
     * @return void
     */
    public function testCreateRollsBackWhenSecondMembershipFails(): void
    {
        $actorId = $this->createUser('admin');
        $this->requestContext()->setActor(new RequestActor($actorId, ['user.create'], false));
        $firstId = $this->addGroup('Одна', false);
        $secondId = $this->addGroup('Другая', false);
        $createService = $this->userCreateService($this->failing(UserGroupMemberTable::class, 'add', 2));
        $this->expectWriteFailure(function () use ($createService, $firstId, $secondId): void {
            $createService->create(new UserCreateInput('Bob', 'bob', 'abcd', [$firstId, $secondId]));
        });
        self::assertNull($this->userAccounts()->findByLogin('bob'));
        self::assertSame([], $this->rows(UserIdentityTable::class));
        self::assertSame([], $this->rows(UserGroupMemberTable::class));
    }

    /**
     * Сбой consume не меняет пароль.
     *
     * @return void
     */
    public function testResetConsumeFailureLeavesPassword(): void
    {
        $userId = $this->createPasswordUser('alice', 'secret', 'alice@x.test');
        $this->storeResetToken($userId, 'raw-token');
        $resetService = $this->passwordResetService($this->failing(AuthPasswordResetTable::class, 'delete', 1), null, null);
        $this->expectWriteFailure(function () use ($resetService): void {
            $resetService->finalPasswordReset('alice', 'raw-token', 'newpass');
        });
        self::assertTrue($this->passwordMatches($userId, 'secret'));
        self::assertNotNull($this->resetRepository(null)->findByTokenHash(hash('sha256', 'raw-token')));
    }

    /**
     * Сбой смены hash возвращает token.
     *
     * @return void
     */
    public function testResetHashFailureRestoresToken(): void
    {
        $userId = $this->createPasswordUser('alice', 'secret', 'alice@x.test');
        $this->storeResetToken($userId, 'raw-token');
        $resetService = $this->passwordResetService(null, $this->failing(UserIdentityTable::class, 'update', 1), null);
        $this->expectWriteFailure(function () use ($resetService): void {
            $resetService->finalPasswordReset('alice', 'raw-token', 'newpass');
        });
        self::assertTrue($this->passwordMatches($userId, 'secret'));
        self::assertNotNull($this->resetRepository(null)->findByTokenHash(hash('sha256', 'raw-token')));
    }

    /**
     * Сбой удаления сессий откатывает token и hash.
     *
     * @return void
     */
    public function testResetSessionDeleteFailureRollsBackPasswordAndToken(): void
    {
        $userId = $this->createPasswordUser('alice', 'secret', 'alice@x.test');
        $this->storeResetToken($userId, 'raw-token');
        $this->sessionRepository(null)->add($userId, hash('sha256', 'live'), DateTime::fromUnix(time() + 3600), 'user');
        $resetService = $this->passwordResetService(null, null, $this->failing(AuthSessionTable::class, 'delete', 1));
        $this->expectWriteFailure(function () use ($resetService): void {
            $resetService->finalPasswordReset('alice', 'raw-token', 'newpass');
        });
        self::assertTrue($this->passwordMatches($userId, 'secret'));
        self::assertNotNull($this->resetRepository(null)->findByTokenHash(hash('sha256', 'raw-token')));
        self::assertCount(1, $this->rows(AuthSessionTable::class));
    }

    /**
     * Повторный token не меняет пароль второй раз.
     *
     * @return void
     */
    public function testSecondResetOfSameTokenFails(): void
    {
        $userId = $this->createPasswordUser('alice', 'secret', 'alice@x.test');
        $this->storeResetToken($userId, 'raw-token');
        $resetService = $this->passwordResetService(null, null, null);
        self::assertTrue($resetService->finalPasswordReset('alice', 'raw-token', 'newpass'));
        try {
            $resetService->finalPasswordReset('alice', 'raw-token', 'newerpass');
            self::fail('second consume');
        } catch (AuthInvalidException $exception) {
            self::assertSame('AUTH_INVALID', $exception->getErrorCode());
        }

        self::assertTrue($this->passwordMatches($userId, 'newpass'));
    }

    /**
     * Сбой удаления сессий откатывает setPassword.
     *
     * @return void
     */
    public function testSetPasswordRollsBackWhenSessionDeleteFails(): void
    {
        $userId = $this->createPasswordUser('alice', 'secret', null);
        $this->requestContext()->setActor(new RequestActor($userId, [], false));
        $this->sessionRepository(null)->add($userId, hash('sha256', 'self'), DateTime::fromUnix(time() + 3600), 'user');
        $setPasswordService = $this->setPasswordService($this->failing(AuthSessionTable::class, 'delete', 1));
        $this->expectWriteFailure(function () use ($setPasswordService, $userId): void {
            $setPasswordService->setPassword($userId, 'newpass', 'secret');
        });
        self::assertTrue($this->passwordMatches($userId, 'secret'));
    }

    /**
     * Своя сессия остаётся, чужие сессии цели удаляются.
     *
     * @return void
     */
    public function testSetPasswordKeepsOnlySelfSession(): void
    {
        $userId = $this->createPasswordUser('alice', 'secret', null);
        $sessionRepository = $this->sessionRepository(null);
        $selfId = $sessionRepository->add($userId, hash('sha256', 'self-raw'), DateTime::fromUnix(time() + 3600), 'user');
        $sessionRepository->add($userId, hash('sha256', 'other-raw'), DateTime::fromUnix(time() + 3600), 'user');
        $this->bindSessionCookie('self-raw');
        $this->requestContext()->setActor(new RequestActor($userId, [], false));
        self::assertTrue($this->setPasswordService(null)->setPassword($userId, 'newpass', 'secret'));
        $rows = $this->rows(AuthSessionTable::class);
        self::assertCount(1, $rows);
        self::assertSame($selfId, (int) $rows[0]['id']);
        self::assertNotNull($this->sessionRepository(null)->findByTokenHash(hash('sha256', 'self-raw')));
    }

    /**
     * Смена чужого пароля удаляет все сессии цели.
     *
     * @return void
     */
    public function testSetPasswordByOtherDeletesEveryTargetSession(): void
    {
        $adminId = $this->createPasswordUser('admin', 'secret', null);
        $userId = $this->createPasswordUser('alice', 'secret', null);
        $this->sessionRepository(null)->add($userId, hash('sha256', 'one'), DateTime::fromUnix(time() + 3600), 'user');
        $this->requestContext()->setActor(new RequestActor($adminId, ['auth.user.edit'], false));
        self::assertTrue($this->setPasswordService(null)->setPassword($userId, 'newpass', null));
        self::assertSame([], $this->rows(AuthSessionTable::class));
        self::assertTrue($this->passwordMatches($userId, 'newpass'));
    }

    /**
     * Сбой постановки job не удаляет старый token и не создаёт новый.
     *
     * @return void
     */
    public function testResetEnqueueFailureKeepsPreviousToken(): void
    {
        $userId = $this->createPasswordUser('alice', 'secret', 'alice@x.test');
        $this->storeResetToken($userId, 'old-token');
        $this->installMail();
        $mail = $this->mailService($this->failing(MailJobTable::class, 'add', 1), false);
        $resetService = $this->passwordResetService(null, null, null, new MailPasswordResetNotifier(
            $mail,
            AuthSettings::fromSection(['expose_reset_token' => false]),
        ));
        $this->expectWriteFailure(function () use ($resetService): void {
            $resetService->startPasswordReset('alice');
        });
        self::assertNotNull($this->resetRepository(null)->findByTokenHash(hash('sha256', 'old-token')));
        self::assertCount(1, $this->rows(AuthPasswordResetTable::class));
        self::assertSame([], $this->rows(MailJobTable::class));
    }

    /**
     * Сбой flush после commit оставляет token и pending job.
     *
     * @return void
     */
    public function testResetFlushFailureLeavesTokenAndPendingJob(): void
    {
        $userId = $this->createPasswordUser('alice', 'secret', 'alice@x.test');
        $this->installMail();
        $inner = $this->mailService(null, true);
        $mail = new class ($inner) implements IMail {
            public function __construct(private readonly IMail $inner)
            {
            }

            public function trigger(string $eventCode, array $payload): void
            {
                $this->flush($this->enqueue($eventCode, $payload));
            }

            public function enqueue(string $eventCode, array $payload): int
            {
                return $this->inner->enqueue($eventCode, $payload);
            }

            public function flush(int $jobId): void
            {
                throw new RuntimeException('flush down');
            }
        };
        $resetService = $this->passwordResetService(null, null, null, new MailPasswordResetNotifier(
            $mail,
            AuthSettings::fromSection(['expose_reset_token' => true]),
        ));
        try {
            $resetService->startPasswordReset('alice');
            self::fail('flush');
        } catch (RuntimeException $exception) {
            self::assertSame('flush down', $exception->getMessage());
        }

        self::assertCount(1, $this->rows(AuthPasswordResetTable::class));
        $jobs = $this->rows(MailJobTable::class);
        self::assertCount(1, $jobs);
        self::assertSame('pending', $jobs[0]['status']);
        self::assertNotNull($this->userAccounts()->findByLogin('alice'));
        unset($userId);
    }

    /**
     * Соединение.
     *
     * @return void
     */
    private function connect(): void
    {
        $envHost = getenv('MIFRIAL_TEST_DB_HOST');
        if (is_string($envHost) && $envHost !== '') {
            $databaseConnection = new IlluminateDatabaseConnection(
                new IlluminateConnectionFactory(),
                $this->settingsFromEnv($envHost),
            );
            $databaseConnection->ping();
            $this->bindGateway(GatewayHarness::make($databaseConnection));

            return;
        }

        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $userContainer = $application->getLocator()->get(IUserContainer::class);
        $smartTableContainer = $application->getLocator()->get(ISmartTableContainer::class);
        $userAccounts = $userContainer->get(IUserAccounts::class);
        $userGroups = $userContainer->get(IUserGroups::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        self::assertInstanceOf(IUserAccounts::class, $userAccounts);
        self::assertInstanceOf(IUserGroups::class, $userGroups);
        self::assertInstanceOf(ISmartTableGateway::class, $smartTableGateway);
        $this->userAccounts = $userAccounts;
        $this->userGroups = $userGroups;
        $this->smartTableGateway = $smartTableGateway;
        $this->requestContext = new RequestContext();
        $this->passwordPolicyService = new PasswordPolicyService(
            new PasswordPolicyRepository($smartTableGateway->open(AuthSecurityPolicyTable::class)->records()),
            new GroupSecurityPolicyRepository($smartTableGateway->open(AuthGroupSecurityPolicyTable::class)->records()),
            $userGroups,
        );
        $databaseConnection = $smartTableContainer->get(IDatabaseConnection::class);
        if (!$databaseConnection instanceof IlluminateDatabaseConnection) {
            throw new DbConfigInvalidException('test connection is not Illuminate');
        }

        $databaseConnection->ping();
    }

    /**
     * Фасады env-шлюза.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     *
     * @return void
     */
    private function bindGateway(ISmartTableGateway $smartTableGateway): void
    {
        $userRecords = $smartTableGateway->open(UserTable::class)->records();
        $this->userAccounts = new UserAccounts(new UserRepository($userRecords));
        $this->userGroups = new UserGroups(
            new UserGroupRepository($smartTableGateway->open(UserGroupTable::class)->records()),
            new UserGroupMemberRepository($smartTableGateway->open(UserGroupMemberTable::class)->records()),
            new UserRepository($userRecords),
        );
        $this->smartTableGateway = $smartTableGateway;
        $this->requestContext = new RequestContext();
        $this->passwordPolicyService = new PasswordPolicyService(
            new PasswordPolicyRepository($smartTableGateway->open(AuthSecurityPolicyTable::class)->records()),
            new GroupSecurityPolicyRepository($smartTableGateway->open(AuthGroupSecurityPolicyTable::class)->records()),
            $this->userGroups,
        );
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

    /**
     * DDL User и Auth.
     *
     * @return void
     */
    private function installSchemas(): void
    {
        $gateway = $this->gateway();
        (new UserSchema(
            $gateway->open(UserTable::class)->schema(),
            $gateway->open(UserGroupTable::class)->schema(),
            $gateway->open(UserGroupMemberTable::class)->schema(),
        ))->install();
        (new AuthSchema(
            $gateway->open(UserIdentityTable::class)->schema(),
            $gateway->open(AuthSessionTable::class)->schema(),
            $gateway->open(AuthSecurityPolicyTable::class)->schema(),
            $gateway->open(AuthGroupSecurityPolicyTable::class)->schema(),
            $gateway->open(AuthPasswordResetTable::class)->schema(),
        ))->install();
    }

    /**
     * DDL Mail.
     *
     * @return void
     */
    private function installMail(): void
    {
        $gateway = $this->gateway();
        (new MailSchema(
            $gateway->open(MailEventTable::class)->schema(),
            $gateway->open(MailTemplateTable::class)->schema(),
            $gateway->open(MailJobTable::class)->schema(),
        ))->install();
        (new MailEventRepository($gateway->open(MailEventTable::class)->records()))->add(
            'auth.password_reset',
            'Сброс пароля',
        );
    }

    /**
     * Сносит таблицы.
     *
     * @return void
     */
    private function dropTables(): void
    {
        if (!$this->smartTableGateway instanceof ISmartTableGateway) {
            return;
        }

        $tableClasses = [
            MailJobTable::class,
            MailTemplateTable::class,
            MailEventTable::class,
            AuthPasswordResetTable::class,
            AuthSessionTable::class,
            UserIdentityTable::class,
            AuthGroupSecurityPolicyTable::class,
            AuthSecurityPolicyTable::class,
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
     * Группа регистрации.
     *
     * @return void
     */
    private function seedPlayerGroup(): void
    {
        $this->userGroups()->add(NewGroup::fromNormalized([
            'name' => 'Игрок',
            'active' => true,
            'bypass' => false,
            'assign_on_register' => true,
            'permissions' => [],
        ]));
    }

    /**
     * @param string $name Имя.
     * @param bool $bypass Обход.
     *
     * @return int Id.
     */
    private function addGroup(string $name, bool $bypass): int
    {
        return $this->userGroups()->add(NewGroup::fromNormalized([
            'name' => $name,
            'active' => true,
            'bypass' => $bypass,
            'assign_on_register' => false,
            'permissions' => [],
        ]));
    }

    /**
     * @param string $login Логин.
     *
     * @return int Id.
     */
    private function createUser(string $login): int
    {
        return $this->userAccounts()->addFromInput([
            'login' => $login,
            'name' => $login,
            'active' => true,
        ]);
    }

    /**
     * @param string $login Логин.
     * @param string $password Пароль.
     * @param string|null $email Почта.
     *
     * @return int Id.
     */
    private function createPasswordUser(string $login, string $password, ?string $email): int
    {
        $profile = [
            'login' => $login,
            'name' => $login,
            'active' => true,
        ];
        if ($email !== null) {
            $profile['email'] = $email;
        }

        $userId = $this->userAccounts()->addFromInput($profile);

        $this->identityRepository(null)->addPassword($userId, password_hash($password, PASSWORD_DEFAULT));
        $player = $this->userGroups()->findByName('Игрок');
        self::assertNotNull($player);
        $this->userGroups()->addMember($userId, $player->getId());

        return $userId;
    }

    /**
     * @param int $userId Учётка.
     * @param string $rawToken Сырой token.
     *
     * @return void
     */
    private function storeResetToken(int $userId, string $rawToken): void
    {
        $this->resetRepository(null)->add($userId, hash('sha256', $rawToken), DateTime::fromUnix(time() + 3600));
    }

    /**
     * @param string $rawToken Сырой токен.
     *
     * @return void
     */
    private function bindSessionCookie(string $rawToken): void
    {
        $httpRequest = $this->createStub(\Mifrial\Core\Kernel\Interface\Http\IHttpRequest::class);
        $httpRequest->method('getCookieMap')->willReturn(['mifrial-session' => $rawToken]);
        $this->requestContext()->bindIncoming($httpRequest);
    }

    /**
     * @param class-string $tableClass Карта.
     * @param string $operation Мутация.
     * @param int $failAt Номер.
     *
     * @return IOpenedRecords Обёртка.
     */
    private function failing(string $tableClass, string $operation, int $failAt): IOpenedRecords
    {
        return new FailingOpenedRecords($this->gateway()->open($tableClass)->records(), $operation, $failAt);
    }

    /**
     * @param IOpenedRecords|null $identityRecords Identity.
     * @param IOpenedRecords|null $memberRecords Членства.
     * @param IOpenedRecords|null $sessionRecords Сессии.
     *
     * @return AuthService Сценарий.
     */
    private function authService(
        ?IOpenedRecords $identityRecords,
        ?IOpenedRecords $memberRecords,
        ?IOpenedRecords $sessionRecords,
    ): AuthService {
        $gateway = $this->gateway();
        $userGroups = $memberRecords === null
            ? $this->userGroups()
            : new UserGroups(
                new UserGroupRepository($gateway->open(UserGroupTable::class)->records()),
                new UserGroupMemberRepository($memberRecords),
                new UserRepository($gateway->open(UserTable::class)->records()),
            );
        $cookieIssuer = new AuthCookieIssuer($this->requestContext(), AuthSettings::fromSection(['cookie_secure' => false]));

        return new AuthService(
            $this->userAccounts(),
            $userGroups,
            $this->identityRepository($identityRecords),
            new AuthSessionRuntime($this->sessionRepository($sessionRecords), $cookieIssuer),
            new UserViewAssembler(
                new UserGroupRepository($gateway->open(UserGroupTable::class)->records()),
                new UserGroupMemberRepository($gateway->open(UserGroupMemberTable::class)->records()),
            ),
            $this->passwordPolicy(),
            new SmartTableTransactionRunner($gateway),
        );
    }

    /**
     * @param IOpenedRecords|null $memberRecords Членства.
     *
     * @return UserCreateService Сценарий.
     */
    private function userCreateService(?IOpenedRecords $memberRecords): UserCreateService
    {
        $gateway = $this->gateway();
        $userGroups = $memberRecords === null
            ? $this->userGroups()
            : new UserGroups(
                new UserGroupRepository($gateway->open(UserGroupTable::class)->records()),
                new UserGroupMemberRepository($memberRecords),
                new UserRepository($gateway->open(UserTable::class)->records()),
            );

        return new UserCreateService(
            new UserAccess($this->requestContext()),
            new UserViewAssembler(
                new UserGroupRepository($gateway->open(UserGroupTable::class)->records()),
                new UserGroupMemberRepository($gateway->open(UserGroupMemberTable::class)->records()),
            ),
            $this->userAccounts(),
            $userGroups,
            $this->identityRepository(null),
            $this->passwordPolicy(),
            new SmartTableTransactionRunner($gateway),
        );
    }

    /**
     * @param IOpenedRecords|null $resetRecords Токены.
     * @param IOpenedRecords|null $identityRecords Identity.
     * @param IOpenedRecords|null $sessionRecords Сессии.
     * @param MailPasswordResetNotifier|null $notifier Почта.
     *
     * @return PasswordResetService Сценарий.
     */
    private function passwordResetService(
        ?IOpenedRecords $resetRecords,
        ?IOpenedRecords $identityRecords,
        ?IOpenedRecords $sessionRecords,
        ?MailPasswordResetNotifier $notifier = null,
    ): PasswordResetService {
        return new PasswordResetService(
            $this->userAccounts(),
            $this->identityRepository($identityRecords),
            $this->resetRepository($resetRecords),
            $this->sessionRepository($sessionRecords),
            $this->passwordPolicy(),
            $notifier ?? new MailPasswordResetNotifier(
                new RecordingMail(),
                AuthSettings::fromSection(['expose_reset_token' => true]),
            ),
            new SmartTableTransactionRunner($this->gateway()),
        );
    }

    /**
     * @param IOpenedRecords|null $sessionRecords Сессии.
     *
     * @return SetPasswordService Сценарий.
     */
    private function setPasswordService(?IOpenedRecords $sessionRecords): SetPasswordService
    {
        return new SetPasswordService(
            new UserAccess($this->requestContext()),
            $this->userAccounts(),
            $this->identityRepository(null),
            $this->sessionRepository($sessionRecords),
            $this->passwordPolicy(),
            new AuthCookieIssuer($this->requestContext(), AuthSettings::fromSection(['cookie_secure' => false])),
            new SmartTableTransactionRunner($this->gateway()),
        );
    }

    /**
     * @param IOpenedRecords|null $jobRecords Очередь.
     * @param bool $flushInline Inline flush.
     *
     * @return MailService Очередь.
     */
    private function mailService(?IOpenedRecords $jobRecords, bool $flushInline): MailService
    {
        $gateway = $this->gateway();
        $jobRepository = new MailJobRepository($jobRecords ?? $gateway->open(MailJobTable::class)->records());

        return new MailService(
            new MailEventRepository($gateway->open(MailEventTable::class)->records()),
            $jobRepository,
            MailSettings::fromSection(['flush_inline' => $flushInline]),
            new MailFlushService(
                $jobRepository,
                new MailTemplateRepository($gateway->open(MailTemplateTable::class)->records()),
                new PlaceholderRenderer(),
                new RecordingMailTransport(),
                new RecordingLogger(),
            ),
        );
    }

    /**
     * @param IOpenedRecords|null $identityRecords Identity.
     *
     * @return UserIdentityRepository Репозиторий.
     */
    private function identityRepository(?IOpenedRecords $identityRecords): UserIdentityRepository
    {
        return new UserIdentityRepository($identityRecords ?? $this->gateway()->open(UserIdentityTable::class)->records());
    }

    /**
     * @param IOpenedRecords|null $sessionRecords Сессии.
     *
     * @return AuthSessionRepository Репозиторий.
     */
    private function sessionRepository(?IOpenedRecords $sessionRecords): AuthSessionRepository
    {
        return new AuthSessionRepository($sessionRecords ?? $this->gateway()->open(AuthSessionTable::class)->records());
    }

    /**
     * @param IOpenedRecords|null $resetRecords Токены.
     *
     * @return PasswordResetRepository Репозиторий.
     */
    private function resetRepository(?IOpenedRecords $resetRecords): PasswordResetRepository
    {
        return new PasswordResetRepository(
            $resetRecords ?? $this->gateway()->open(AuthPasswordResetTable::class)->records(),
        );
    }

    /**
     * @param Closure $work Запись.
     *
     * @return void
     */
    private function expectWriteFailure(Closure $work): void
    {
        try {
            $work();
            self::fail('write');
        } catch (RuntimeException $exception) {
            self::assertSame('injected write failure', $exception->getMessage());
        }
    }

    /**
     * @param int $userId Учётка.
     * @param string $password Пароль.
     *
     * @return bool true, если hash совпал.
     */
    private function passwordMatches(int $userId, string $password): bool
    {
        $identityRow = $this->identityRepository(null)->findPassword($userId);
        $secretHash = is_array($identityRow) ? ($identityRow['secret_hash'] ?? null) : null;

        return is_string($secretHash) && password_verify($password, $secretHash);
    }

    /**
     * @param class-string $tableClass Карта.
     *
     * @return array<int, array<string, mixed>> Строки.
     */
    private function rows(string $tableClass): array
    {
        return $this->gateway()->open($tableClass)->records()->getList(ListQuery::fromOptions([
            'limit' => 50,
        ]))->rows();
    }

    /**
     * @return ISmartTableGateway Шлюз.
     */
    private function gateway(): ISmartTableGateway
    {
        self::assertInstanceOf(ISmartTableGateway::class, $this->smartTableGateway);

        return $this->smartTableGateway;
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
     * @return RequestContext Контекст.
     */
    private function requestContext(): RequestContext
    {
        self::assertInstanceOf(RequestContext::class, $this->requestContext);

        return $this->requestContext;
    }

    /**
     * @return PasswordPolicyService Политика.
     */
    private function passwordPolicy(): PasswordPolicyService
    {
        self::assertInstanceOf(PasswordPolicyService::class, $this->passwordPolicyService);

        return $this->passwordPolicyService;
    }
}
