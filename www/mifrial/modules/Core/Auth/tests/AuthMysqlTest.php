<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Tests;

use Mifrial\Core\Auth\Dto\Action\UserCreateInput;
use Mifrial\Core\Auth\Dto\AuthSettings;
use Mifrial\Core\Auth\Exception\AuthDuplicateException;
use Mifrial\Core\Auth\Exception\AuthInvalidException;
use Mifrial\Core\Auth\Exception\AuthPolicyException;
use Mifrial\Core\Auth\Interface\Container\IAuthContainer;
use Mifrial\Core\Auth\Interface\Service\IPasswordResetNotifier;
use Mifrial\Core\Auth\Repository\AuthSessionRepository;
use Mifrial\Core\Auth\Repository\GroupSecurityPolicyRepository;
use Mifrial\Core\Auth\Repository\PasswordPolicyRepository;
use Mifrial\Core\Auth\Repository\PasswordResetRepository;
use Mifrial\Core\Auth\Repository\UserIdentityRepository;
use Mifrial\Core\Auth\Schema\AuthSchema;
use Mifrial\Core\Auth\Service\AuthCookieIssuer;
use Mifrial\Core\Auth\Service\AuthService;
use Mifrial\Core\Auth\Service\AuthSessionBinder;
use Mifrial\Core\Auth\Service\AuthSessionRuntime;
use Mifrial\Core\Auth\Service\LogPasswordResetNotifier;
use Mifrial\Core\Auth\Service\PasswordPolicyService;
use Mifrial\Core\Auth\Service\PasswordResetService;
use Mifrial\Core\Auth\Service\SetPasswordService;
use Mifrial\Core\Auth\Service\UserCreateService;
use Mifrial\Core\Auth\Setup\BootstrapGroupsStep;
use Mifrial\Core\Auth\Table\AuthGroupSecurityPolicyTable;
use Mifrial\Core\Auth\Table\AuthPasswordResetTable;
use Mifrial\Core\Auth\Table\AuthSecurityPolicyTable;
use Mifrial\Core\Auth\Table\AuthSessionTable;
use Mifrial\Core\Auth\Table\UserIdentityTable;
use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Http\RequestContext;
use Mifrial\Core\Kernel\Interface\Http\IHttpRequest;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\SmartTable\Dto\ListQuery;
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
use Mifrial\Core\User\Exception\UserNotFoundException;
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

final class AuthMysqlTest extends TestCase
{
    private ?AuthService $authService = null;

    private ?IUserAccounts $userAccounts = null;

    private ?IUserGroups $userGroups = null;

    private ?IRequestContext $requestContext = null;

    private ?ISmartTableGateway $smartTableGateway = null;

    private ?UserIdentityRepository $identityRepository = null;

    private ?AuthSessionRepository $sessionRepository = null;

    private ?PasswordPolicyService $passwordPolicyService = null;

    private ?PasswordResetService $passwordResetService = null;

    private ?SetPasswordService $setPasswordService = null;

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
        $this->passwordPolicyService()->ensureDefaults();
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
        self::assertContains(
            (int) $this->userGroups()->findByName('Игрок')?->getId(),
            $login['user']['groups'],
        );
        self::assertArrayHasKey('lastLogin', $login['user']);
        self::assertIsInt($login['user']['lastLogin']);
        self::assertArrayNotHasKey('super_admin', $login['user']);
        self::assertArrayHasKey('bypass', $login['user']);
        $rawToken = $this->takeSessionToken();
        $this->bindIncomingSession($rawToken);
        $current = $this->authService()->getCurrentUser();
        self::assertIsArray($current);
        self::assertSame('user', $current['kind']);
        self::assertSame('alice', $current['user']['login']);
        $this->authService()->logout();
        $this->bindIncomingSession($rawToken);
        self::assertNull($this->authService()->getCurrentUser());
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
        self::assertNull($this->authService()->getCurrentUser());
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
        self::assertNull($withoutEmail['user']['email']);
    }

    /**
     * Автовыдача по флагу, не по имени «Игрок».
     *
     * @return void
     */
    public function testRegisterUsesAssignOnRegisterFlag(): void
    {
        $this->userGroups()->add(NewGroup::fromNormalized([
            'name' => 'Игрок',
            'active' => true,
            'bypass' => false,
            'assign_on_register' => false,
            'permissions' => [],
        ]));
        try {
            $this->authService()->register('guest1', 'g1@x.test', 'abcd');
            self::fail('player without flag must fail');
        } catch (AuthInvalidException $exception) {
            self::assertSame('AUTH_INVALID', $exception->getErrorCode());
        }

        $this->userGroups()->add(NewGroup::fromNormalized([
            'name' => 'Гость',
            'active' => true,
            'bypass' => false,
            'assign_on_register' => true,
            'permissions' => [],
        ]));
        $registered = $this->authService()->register('guest2', 'g2@x.test', 'abcd');
        $guestId = (int) $this->userGroups()->findByName('Гость')?->getId();
        self::assertSame([$guestId], $registered['user']['groups']);
    }

    /**
     * Binder кладёт актора; create без сессии созданного.
     *
     * @return void
     */
    public function testResolveActorAndAdminCreate(): void
    {
        $this->seedPlayerGroup();
        $this->createPasswordUser('alice', 'secret');
        $this->authService()->login('alice', 'secret', false);
        $this->bindIncomingSession($this->takeSessionToken());
        $requestActor = $this->authService()->resolveActor();
        self::assertNotNull($requestActor);
        self::assertSame('alice', $this->userAccounts()->getById($requestActor->getUserId())->getLogin());
        (new AuthSessionBinder($this->authService()))->bind($this->requestContext());
        self::assertNotNull($this->requestContext()->getActor());

        $this->userGroups()->add(NewGroup::fromNormalized([
            'name' => 'Админ',
            'active' => true,
            'bypass' => false,
            'permissions' => ['user.create', 'user.view'],
        ]));
        $adminGroup = $this->userGroups()->findByName('Админ');
        self::assertNotNull($adminGroup);
        $this->userGroups()->addMember($requestActor->getUserId(), $adminGroup->getId());
        $this->requestContext()->setActor($this->authService()->resolveActor());
        $created = $this->userCreateService()->create(new UserCreateInput(
            'Bob',
            'bob',
            'abcd',
            [],
            'b@x.test',
            'Sur',
            null,
        ));
        self::assertSame('bob', $created['login']);
        self::assertSame('Sur', $created['surname']);
        self::assertArrayNotHasKey('super_admin', $created);
        self::assertArrayHasKey('bypass', $created);
        self::assertContains(
            (int) $this->userGroups()->findByName('Игрок')?->getId(),
            $created['groups'],
        );
        self::assertArrayNotHasKey('lastLogin', $created);
        self::assertNull($this->identityRepository()->findPassword((int) $created['id'])['last_used_at'] ?? null);
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
        self::assertSame('user', $sessionRow['kind']);
    }

    /**
     * Гость: cookie, нет актора, AUTH_REQUIRED, login поднимает user.
     *
     * @return void
     */
    public function testGuestSession(): void
    {
        $this->seedPlayerGroup();
        $this->createPasswordUser('alice', 'secret');
        $opened = $this->authService()->openGuest();
        self::assertSame('guest', $opened['kind']);
        $queuedNames = [];
        foreach ($this->requestContext()->takeQueuedCookies() as $outgoingCookie) {
            $queuedNames[] = $outgoingCookie->name();
            if ($outgoingCookie->name() === 'mifrial-session' && $outgoingCookie->value() !== '') {
                $rawToken = $outgoingCookie->value();
            }
        }

        self::assertContains('mifrial-session', $queuedNames);
        self::assertContains('csrf-token', $queuedNames);
        self::assertIsString($rawToken ?? null);
        $this->bindIncomingSession($rawToken);
        self::assertSame(['kind' => 'guest'], $this->authService()->getCurrentUser());
        self::assertNull($this->authService()->resolveActor());
        (new AuthSessionBinder($this->authService()))->bind($this->requestContext());
        self::assertNull($this->requestContext()->getActor());
        try {
            (new UserAccess($this->requestContext()))->requireKey('user.view');
            self::fail('guest must be AUTH_REQUIRED');
        } catch (ActionException $exception) {
            self::assertSame('AUTH_REQUIRED', $exception->getErrorCode());
        }

        $sessionRow = $this->sessionRepository()->findByTokenHash(hash('sha256', $rawToken));
        self::assertNotNull($sessionRow);
        self::assertNull($sessionRow['user_id']);
        self::assertSame('guest', $sessionRow['kind']);
        $this->authService()->login('alice', 'secret', false);
        $userToken = $this->takeSessionToken();
        $this->bindIncomingSession($userToken);
        $current = $this->authService()->getCurrentUser();
        self::assertIsArray($current);
        self::assertSame('user', $current['kind']);
        self::assertSame('alice', $current['user']['login']);
        self::assertNull($this->sessionRepository()->findByTokenHash(hash('sha256', $rawToken)));
    }

    /**
     * Повтор guest ротирует токен; user-сессия guest не сбивает; logout сносит guest.
     *
     * @return void
     */
    public function testGuestReplaceLogoutAndBrokenKind(): void
    {
        $this->authService()->openGuest();
        $firstToken = $this->takeSessionToken();
        $this->bindIncomingSession($firstToken);
        $this->authService()->openGuest();
        $secondToken = $this->takeSessionToken();
        self::assertNotSame($firstToken, $secondToken);
        $this->bindIncomingSession($firstToken);
        self::assertNull($this->authService()->getCurrentUser());
        $this->bindIncomingSession($secondToken);
        self::assertSame(['kind' => 'guest'], $this->authService()->getCurrentUser());
        $this->authService()->logout();
        $this->bindIncomingSession($secondToken);
        self::assertNull($this->authService()->getCurrentUser());

        $this->seedPlayerGroup();
        $this->createPasswordUser('alice', 'secret');
        $this->authService()->login('alice', 'secret', false);
        $userToken = $this->takeSessionToken();
        $this->bindIncomingSession($userToken);
        try {
            $this->authService()->openGuest();
            self::fail('user session must block guest');
        } catch (AuthInvalidException $exception) {
            self::assertSame('AUTH_INVALID', $exception->getErrorCode());
        }

        $this->bindIncomingSession($userToken);
        $stillUser = $this->authService()->getCurrentUser();
        self::assertIsArray($stillUser);
        self::assertSame('user', $stillUser['kind']);
        $liveRow = $this->sessionRepository()->findByTokenHash(hash('sha256', $userToken));
        self::assertNotNull($liveRow);
        $this->smartTableGateway()->open(AuthSessionTable::class)->records()->update(
            (int) $liveRow['id'],
            ['kind' => 'nope'],
        );
        $this->bindIncomingSession($userToken);
        self::assertNull($this->authService()->getCurrentUser());
        self::assertNull($this->sessionRepository()->findByTokenHash(hash('sha256', $userToken)));

        $expiredHash = hash('sha256', 'expired-guest');
        $this->sessionRepository()->add(null, $expiredHash, DateTime::fromUnix(time() - 10), 'guest');
        $httpRequest = $this->createStub(IHttpRequest::class);
        $httpRequest->method('getCookieMap')->willReturn(['mifrial-session' => 'expired-guest']);
        $this->requestContext()->bindIncoming($httpRequest);
        self::assertNull($this->authService()->getCurrentUser());
        self::assertNull($this->sessionRepository()->findByTokenHash($expiredHash));
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
        self::assertTrue($admin->isBypass());
        self::assertFalse($admin->isAssignOnRegister());
        self::assertNotNull($player);
        self::assertTrue($player->isAssignOnRegister());
        $operator = $this->userAccounts()->findByLogin('admin');
        self::assertNotNull($operator);
        $operatorId = $operator->getId();
        self::assertNotNull($this->identityRepository()->findPassword($operatorId));
        $groupIds = $this->userGroups()->getGroupIdsOfUser($operatorId);
        sort($groupIds);
        $expected = [$admin->getId(), $player->getId()];
        sort($expected);
        self::assertSame($expected, $groupIds);
        self::assertTrue($this->userGroups()->hasBypass($operatorId));
        $this->authService()->login('admin', 'changeme', false);
    }

    /**
     * Политика: default, effective групп, USER_NOT_FOUND.
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
        ], $this->authService()->getPasswordPolicy());
        try {
            $this->authService()->getPasswordPolicy(9_999_999);
            self::fail('expected USER_NOT_FOUND');
        } catch (UserNotFoundException $exception) {
            self::assertSame('USER_NOT_FOUND', $exception->getErrorCode());
        }

        $defaultRow = $this->smartTableGateway()->open(AuthSecurityPolicyTable::class)->records()->getFirst(
            ListQuery::fromOptions([
                'filter' => ['is_default' => true],
                'sort' => ['id' => 'asc'],
                'limit' => 1,
            ]),
        );
        self::assertIsArray($defaultRow);
        $this->smartTableGateway()->open(AuthSecurityPolicyTable::class)->records()->update(
            (int) $defaultRow['id'],
            ['min_length' => 8],
        );
        self::assertSame(8, $this->authService()->getPasswordPolicy()['minLength']);
        $this->seedPlayerGroup();
        try {
            $this->authService()->register('policyu', 'p@x.test', 'abcd');
            self::fail('expected AUTH_POLICY');
        } catch (AuthPolicyException $exception) {
            self::assertSame('AUTH_POLICY', $exception->getErrorCode());
        }

        $this->userGroups()->add(NewGroup::fromNormalized([
            'name' => 'Строгая',
            'active' => true,
            'bypass' => false,
            'permissions' => [],
        ]));
        $strictGroup = $this->userGroups()->findByName('Строгая');
        self::assertNotNull($strictGroup);
        $strictPolicyId = $this->smartTableGateway()->open(AuthSecurityPolicyTable::class)->records()->add([
            'name' => 'Строгая',
            'min_length' => 12,
            'require_mixed_case' => false,
            'require_digit' => true,
            'require_special_char' => false,
            'is_default' => false,
        ]);
        $this->smartTableGateway()->open(AuthGroupSecurityPolicyTable::class)->records()->add([
            'group_id' => $strictGroup->getId(),
            'policy_id' => $strictPolicyId,
        ]);
        $aliceId = $this->createPasswordUser('policyalice', 'secret12');
        $this->userGroups()->addMember($aliceId, $strictGroup->getId());
        $effective = $this->authService()->getPasswordPolicy($aliceId);
        self::assertSame(12, $effective['minLength']);
        self::assertTrue($effective['requireDigit']);
        try {
            $this->passwordPolicyService()->assertPasswordForUser($aliceId, 'abcdefghij');
            self::fail('expected AUTH_POLICY');
        } catch (AuthPolicyException $exception) {
            self::assertSame('AUTH_POLICY', $exception->getErrorCode());
        }
    }

    /**
     * start/final: статусы, одноразовость, чужой login, политика, сессии.
     *
     * @return void
     */
    public function testPasswordReset(): void
    {
        $this->seedPlayerGroup();
        $aliceId = $this->createPasswordUser('alice', 'secret', 'alice@x.test');
        $this->createPasswordUser('bob', 'secret', 'bob@x.test');
        $this->createPasswordUser('nomail', 'secret');
        self::assertSame(['status' => 'not_found'], $this->passwordResetService()->startPasswordReset('ghost'));
        self::assertSame(['status' => 'no_email'], $this->passwordResetService()->startPasswordReset('nomail'));
        $firstStart = $this->passwordResetService()->startPasswordReset('alice@x.test');
        self::assertSame('sent', $firstStart['status']);
        self::assertSame('alice', $firstStart['login']);
        self::assertArrayHasKey('resetToken', $firstStart);
        $secondStart = $this->passwordResetService()->startPasswordReset('alice');
        self::assertNotSame($firstStart['resetToken'], $secondStart['resetToken']);
        try {
            $this->passwordResetService()->finalPasswordReset('alice', $firstStart['resetToken'], 'newpass');
            self::fail('expected AUTH_INVALID for replaced token');
        } catch (AuthInvalidException $exception) {
            self::assertSame('AUTH_INVALID', $exception->getErrorCode());
        }

        try {
            $this->passwordResetService()->finalPasswordReset('bob', $secondStart['resetToken'], 'newpass');
            self::fail('expected AUTH_INVALID for wrong login');
        } catch (AuthInvalidException $exception) {
            self::assertSame('AUTH_INVALID', $exception->getErrorCode());
        }

        try {
            $this->passwordResetService()->finalPasswordReset('alice', $secondStart['resetToken'], 'ab');
            self::fail('expected AUTH_POLICY');
        } catch (AuthPolicyException $exception) {
            self::assertSame('AUTH_POLICY', $exception->getErrorCode());
        }

        $this->authService()->login('alice', 'secret', false);
        self::assertTrue($this->passwordResetService()->finalPasswordReset(
            'alice',
            $secondStart['resetToken'],
            'newpass',
        ));
        $sessionList = $this->smartTableGateway()->open(AuthSessionTable::class)->records()->getList(ListQuery::fromOptions([
            'filter' => ['user_id' => $aliceId],
            'limit' => 10,
        ]));
        self::assertSame([], $sessionList->rows());
        try {
            $this->authService()->login('alice', 'secret', false);
            self::fail('expected AUTH_INVALID for old password');
        } catch (AuthInvalidException $exception) {
            self::assertSame('AUTH_INVALID', $exception->getErrorCode());
        }

        $this->authService()->login('alice', 'newpass', false);
        try {
            $this->passwordResetService()->finalPasswordReset('alice', $secondStart['resetToken'], 'newerpass');
            self::fail('expected AUTH_INVALID for used token');
        } catch (AuthInvalidException $exception) {
            self::assertSame('AUTH_INVALID', $exception->getErrorCode());
        }

        $this->resetRepository()->add($aliceId, hash('sha256', 'expired-raw'), DateTime::fromUnix(time() - 10));
        try {
            $this->passwordResetService()->finalPasswordReset('alice', 'expired-raw', 'newpass');
            self::fail('expected AUTH_INVALID for expired token');
        } catch (AuthInvalidException $exception) {
            self::assertSame('AUTH_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Notifier упал — строки reset нет.
     *
     * @return void
     */
    public function testPasswordResetDoesNotStoreTokenIfNotifyFails(): void
    {
        $this->seedPlayerGroup();
        $aliceId = $this->createPasswordUser('alice', 'secret', 'alice@x.test');
        $notifier = new class () implements IPasswordResetNotifier {
            public function notify(string $login, string $rawToken, string $email): void
            {
                throw new RuntimeException('mail down');
            }

            public function shouldExposeRawToken(): bool
            {
                return false;
            }
        };
        $resetService = new PasswordResetService(
            $this->userAccounts(),
            $this->identityRepository(),
            $this->resetRepository(),
            $this->sessionRepository(),
            $this->passwordPolicyService(),
            $notifier,
        );
        try {
            $resetService->startPasswordReset('alice');
            self::fail('notify');
        } catch (RuntimeException $exception) {
            self::assertSame('mail down', $exception->getMessage());
        }

        $rows = $this->smartTableGateway()->open(AuthPasswordResetTable::class)->records()->getList(
            ListQuery::fromOptions(['filter' => ['user_id' => $aliceId], 'limit' => 10]),
        )->rows();
        self::assertSame([], $rows);
    }

    /**
     * setPassword: себя, отказ, bypass, ключ, неактивный, нет учётки.
     *
     * @return void
     */
    public function testSetPassword(): void
    {
        $this->seedPlayerGroup();
        $aliceId = $this->createPasswordUser('alice', 'secret');
        $bobId = $this->createPasswordUser('bob', 'secret');
        try {
            $this->setPasswordService()->setPassword($aliceId, 'newpass', 'secret');
            self::fail('expected AUTH_REQUIRED');
        } catch (ActionException $exception) {
            self::assertSame('AUTH_REQUIRED', $exception->getErrorCode());
        }

        $this->loginAs('alice', 'secret');
        try {
            $this->setPasswordService()->setPassword($aliceId, 'newpass', null);
            self::fail('expected AUTH_INVALID without current');
        } catch (AuthInvalidException $exception) {
            self::assertSame('AUTH_INVALID', $exception->getErrorCode());
        }

        self::assertTrue($this->setPasswordService()->setPassword($aliceId, 'newpass', 'secret'));
        self::assertNotNull($this->authService()->getCurrentUser());
        try {
            $this->authService()->login('alice', 'secret', false);
            self::fail('expected AUTH_INVALID for old password');
        } catch (AuthInvalidException $exception) {
            self::assertSame('AUTH_INVALID', $exception->getErrorCode());
        }

        $this->loginAs('alice', 'newpass');
        try {
            $this->setPasswordService()->setPassword($bobId, 'taken', null);
            self::fail('expected AUTH_DENIED');
        } catch (ActionException $exception) {
            self::assertSame('AUTH_DENIED', $exception->getErrorCode());
        }

        $adminGroupId = $this->userGroups()->add(NewGroup::fromNormalized([
            'name' => 'Админы',
            'active' => true,
            'bypass' => true,
            'assign_on_register' => false,
            'permissions' => [],
        ]));
        $adminId = $this->createPasswordUser('root', 'secret');
        $this->userGroups()->addMember($adminId, $adminGroupId);
        $this->loginAs('root', 'secret');
        self::assertTrue($this->setPasswordService()->setPassword($bobId, 'adminpass', null));
        $this->authService()->login('bob', 'adminpass', false);
        $this->takeSessionToken();

        $keyGroupId = $this->userGroups()->add(NewGroup::fromNormalized([
            'name' => 'Пароли',
            'active' => true,
            'bypass' => false,
            'assign_on_register' => false,
            'permissions' => ['auth.user.edit'],
        ]));
        $carolId = $this->createPasswordUser('carol', 'secret');
        $this->userGroups()->addMember($carolId, $keyGroupId);
        $this->loginAs('carol', 'secret');
        self::assertTrue($this->setPasswordService()->setPassword($bobId, 'carolpass', null));
        $this->userAccounts()->update($bobId, UserPatch::fromNormalized(['active' => false]));
        self::assertTrue($this->setPasswordService()->setPassword($bobId, 'stillok', null));
        try {
            $this->setPasswordService()->setPassword(999999, 'newpass', null);
            self::fail('expected USER_NOT_FOUND');
        } catch (UserNotFoundException $exception) {
            self::assertSame('USER_NOT_FOUND', $exception->getErrorCode());
        }
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
        $passwordPolicyService = new PasswordPolicyService(
            new PasswordPolicyRepository($smartTableGateway->open(AuthSecurityPolicyTable::class)->records()),
            new GroupSecurityPolicyRepository(
                $smartTableGateway->open(AuthGroupSecurityPolicyTable::class)->records(),
            ),
            $userGroups,
        );
        $this->requestContext = $requestContext;
        $this->identityRepository = $identityRepository;
        $this->sessionRepository = $sessionRepository;
        $this->passwordPolicyService = $passwordPolicyService;
        $this->passwordResetService = new PasswordResetService(
            $userAccounts,
            $identityRepository,
            new PasswordResetRepository($smartTableGateway->open(AuthPasswordResetTable::class)->records()),
            $sessionRepository,
            $passwordPolicyService,
            new LogPasswordResetNotifier(AuthSettings::fromSection(['expose_reset_token' => true])),
        );
        $cookieIssuer = new AuthCookieIssuer($requestContext, $settings);
        $this->setPasswordService = new SetPasswordService(
            new UserAccess($requestContext),
            $userAccounts,
            $identityRepository,
            $sessionRepository,
            $passwordPolicyService,
            $cookieIssuer,
        );
        $this->authService = new AuthService(
            $userAccounts,
            $userGroups,
            $identityRepository,
            new AuthSessionRuntime($sessionRepository, $cookieIssuer),
            $this->userViewAssembler($smartTableGateway),
            $passwordPolicyService,
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
            $gateway->open(AuthSecurityPolicyTable::class)->schema(),
            $gateway->open(AuthGroupSecurityPolicyTable::class)->schema(),
            $gateway->open(AuthPasswordResetTable::class)->schema(),
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
            'assign_on_register' => true,
            'permissions' => [],
        ]));
        $this->passwordPolicyService()->ensureDefaults();
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
        $this->userGroups()->addMember($userId, $player->getId());

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
     * Сборщик JSON с репозиториев шлюза.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     *
     * @return UserViewAssembler Сборщик.
     */
    private function userViewAssembler(ISmartTableGateway $smartTableGateway): UserViewAssembler
    {
        return new UserViewAssembler(
            new UserGroupRepository($smartTableGateway->open(UserGroupTable::class)->records()),
            new UserGroupMemberRepository($smartTableGateway->open(UserGroupMemberTable::class)->records()),
        );
    }

    /**
     * @return UserCreateService Admin-create.
     */
    private function userCreateService(): UserCreateService
    {
        return new UserCreateService(
            new UserAccess($this->requestContext()),
            $this->userViewAssembler($this->smartTableGateway()),
            $this->userAccounts(),
            $this->userGroups(),
            $this->identityRepository(),
            $this->passwordPolicyService(),
        );
    }

    /**
     * Входит, кладёт cookie и актора в контекст.
     *
     * @param string $login Логин.
     * @param string $password Пароль.
     *
     * @return void
     */
    private function loginAs(string $login, string $password): void
    {
        $this->authService()->login($login, $password, false);
        $this->bindIncomingSession($this->takeSessionToken());
        $this->requestContext()->setActor($this->authService()->resolveActor());
    }

    /**
     * @return PasswordResetService Сброс.
     */
    private function passwordResetService(): PasswordResetService
    {
        self::assertInstanceOf(PasswordResetService::class, $this->passwordResetService);

        return $this->passwordResetService;
    }

    /**
     * @return SetPasswordService Смена пароля.
     */
    private function setPasswordService(): SetPasswordService
    {
        self::assertInstanceOf(SetPasswordService::class, $this->setPasswordService);

        return $this->setPasswordService;
    }

    /**
     * @return PasswordResetRepository Токены.
     */
    private function resetRepository(): PasswordResetRepository
    {
        return new PasswordResetRepository(
            $this->smartTableGateway()->open(AuthPasswordResetTable::class)->records(),
        );
    }

    /**
     * @return PasswordPolicyService Политика.
     */
    private function passwordPolicyService(): PasswordPolicyService
    {
        self::assertInstanceOf(PasswordPolicyService::class, $this->passwordPolicyService);

        return $this->passwordPolicyService;
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
