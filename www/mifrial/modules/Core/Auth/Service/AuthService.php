<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Service;

use Mifrial\Core\Auth\Exception\AuthDuplicateException;
use Mifrial\Core\Auth\Exception\AuthInvalidException;
use Mifrial\Core\Auth\Exception\AuthPolicyException;
use Mifrial\Core\Auth\Repository\UserIdentityRepository;
use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\User\Dto\UserRecord;
use Mifrial\Core\User\Exception\UserDuplicateException;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Exception\UserNotFoundException;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Interface\Service\IUserGroups;
use Mifrial\Core\User\Interface\Service\IUserViews;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassComplexityTooHigh
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

/**
 * Вход, регистрация, сессия и «кто я».
 *
 * resolveActor делит поиск сессии с getCurrentUser; сложность чуть выше лимита класса.
 */
final class AuthService
{
    private const DEFAULT_TTL = 86400;

    private const REMEMBER_TTL = 2592000;

    /**
     * Создаёт сценарий Auth.
     *
     * @param IUserAccounts $userAccounts Учётки.
     * @param IUserGroups $userGroups Группы.
     * @param UserIdentityRepository $identityRepository Identity.
     * @param AuthSessionRuntime $sessionRuntime Cookie и строки сессии.
     * @param IUserViews $userAssembler JSON User.
     * @param PasswordPolicyService $passwordPolicyService Политика пароля.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccounts $userAccounts,
        private readonly IUserGroups $userGroups,
        private readonly UserIdentityRepository $identityRepository,
        private readonly AuthSessionRuntime $sessionRuntime,
        private readonly IUserViews $userAssembler,
        private readonly PasswordPolicyService $passwordPolicyService,
    ) {
    }

    /**
     * Вход по login или email.
     *
     * @param string $loginOrEmail Идентификатор.
     * @param string $password Пароль.
     * @param bool $remember Долгий TTL.
     *
     * @return array{user: array<string, mixed>} Сборка User.
     *
     * @throws AuthInvalidException Если вход отклонён.
     */
    public function login(string $loginOrEmail, string $password, bool $remember = false): array
    {
        $userRecord = $this->resolveActiveUser($loginOrEmail);
        $identityRow = $this->verifiedPasswordIdentity($userRecord, $password);
        $this->openSession($userRecord, $identityRow, $remember);

        return ['user' => $this->userAssembler->assemble($userRecord, DateTime::now())];
    }

    /**
     * Открытая регистрация и сразу сессия.
     *
     * @param string $login Логин.
     * @param string $email Почта.
     * @param string $password Пароль.
     *
     * @return array{user: array<string, mixed>} Сборка User.
     *
     * @throws AuthPolicyException Если пароль слабый.
     * @throws AuthInvalidException Если нет групп с assign_on_register или поля битые.
     * @throws AuthDuplicateException Если login/email заняты.
     */
    public function register(string $login, string $email, string $password): array
    {
        $this->passwordPolicyService->assertPassword($password);
        $assignGroupIds = $this->assignOnRegisterIds();
        $userId = $this->createRegisteredUser($login, $email);
        $this->identityRepository->addPassword($userId, password_hash($password, PASSWORD_DEFAULT));
        $this->addMembershipsById($userId, $assignGroupIds);

        return $this->login($login, $password, false);
    }

    /**
     * Завершает текущую сессию, если cookie есть.
     *
     * @return void
     */
    public function logout(): void
    {
        $sessionRow = $this->sessionRuntime->liveRow();
        if ($sessionRow !== null) {
            $this->sessionRuntime->deleteById((int) $sessionRow['id']);
        }

        $this->sessionRuntime->expire();
    }

    /**
     * Открывает гостевую сессию.
     *
     * @return array{kind: string} Конверт.
     *
     * @throws AuthInvalidException Если жива user-сессия.
     */
    public function openGuest(): array
    {
        $sessionRow = $this->sessionRuntime->liveRow();
        if ($sessionRow !== null && $this->sessionRuntime->kind($sessionRow) === 'user') {
            throw new AuthInvalidException();
        }

        $this->sessionRuntime->dropIncoming();
        $this->sessionRuntime->issue(null, 'guest', self::DEFAULT_TTL);

        return ['kind' => 'guest'];
    }

    /**
     * Конверт текущей сессии или null.
     *
     * @return array<string, mixed>|null Guest, user+JSON или нет сессии.
     */
    public function getCurrentUser(): ?array
    {
        $sessionRow = $this->sessionRuntime->liveRow();
        if ($sessionRow === null) {
            return null;
        }

        $sessionKind = $this->sessionRuntime->kind($sessionRow);
        $userView = $sessionKind === 'guest'
            ? ['kind' => 'guest']
            : $this->userEnvelopeOrNull($sessionRow, $sessionKind);
        if ($userView === null) {
            $this->sessionRuntime->deleteById((int) $sessionRow['id']);
        }

        return $userView;
    }

    /**
     * Снимок актора по живой сессии, без JSON User.
     *
     * @return RequestActor|null Актор или null.
     */
    public function resolveActor(): ?RequestActor
    {
        $sessionRow = $this->sessionRuntime->liveRow();
        $sessionKind = $sessionRow === null ? null : $this->sessionRuntime->kind($sessionRow);
        if ($sessionRow === null || $sessionKind === 'guest') {
            return null;
        }

        $requestActor = $sessionKind === 'user' ? $this->actorFromSessionRow($sessionRow) : null;
        if ($requestActor === null) {
            $this->sessionRuntime->deleteById((int) $sessionRow['id']);
        }

        return $requestActor;
    }

    /**
     * Политика пароля: default или effective учётки.
     *
     * @param int|null $userId Учётка; null — default.
     *
     * @return array{minLength: int, requireMixedCase: bool, requireDigit: bool, requireSpecialChar: bool}
     *
     * @throws UserNotFoundException Если userId задан и учётки нет.
     */
    public function getPasswordPolicy(?int $userId = null): array
    {
        if ($userId !== null && $userId > 0) {
            $this->userAccounts->getById($userId);
        }

        return $this->passwordPolicyService->getPasswordPolicy($userId);
    }

    /**
     * Сборка JSON; нет учётки в группах → null.
     *
     * @param UserRecord $userRecord Профиль.
     *
     * @return array<string, mixed>|null Сборка.
     */
    private function tryAssembleCurrentUser(UserRecord $userRecord): ?array
    {
        try {
            return $this->assembleCurrentUser($userRecord);
        } catch (UserNotFoundException) {
            return null;
        }
    }

    /**
     * JSON User плюс last_used_at identity.
     *
     * @param UserRecord $userRecord Профиль.
     *
     * @return array<string, mixed> Сборка.
     *
     * @throws UserNotFoundException Если группы/bypass не нашли учётку.
     */
    private function assembleCurrentUser(UserRecord $userRecord): array
    {
        $identityRow = $this->identityRepository->findPassword($userRecord->getId());
        $lastLogin = $identityRow['last_used_at'] ?? null;

        return $this->userAssembler->assemble(
            $userRecord,
            $lastLogin instanceof DateTime ? $lastLogin : null,
        );
    }

    /**
     * Ищет активную учётку по login, иначе по email.
     *
     * @param string $loginOrEmail Идентификатор.
     *
     * @return UserRecord Учётка.
     *
     * @throws AuthInvalidException Если нет или неактивна.
     */
    private function resolveActiveUser(string $loginOrEmail): UserRecord
    {
        $identifier = trim($loginOrEmail);
        $userRecord = $this->findByLoginOrEmail($identifier);
        if ($userRecord === null || !$userRecord->isActive()) {
            throw new AuthInvalidException();
        }

        return $userRecord;
    }

    /**
     * findByLogin, при null — findByEmail; пустой login — AUTH_INVALID.
     *
     * @param string $identifier Trim-идентификатор.
     *
     * @return UserRecord|null Учётка.
     *
     * @throws AuthInvalidException Если login пуст.
     */
    private function findByLoginOrEmail(string $identifier): ?UserRecord
    {
        try {
            $byLogin = $this->userAccounts->findByLogin($identifier);
        } catch (UserInvalidException $exception) {
            throw new AuthInvalidException('Authentication failed', $exception);
        }

        if ($byLogin !== null) {
            return $byLogin;
        }

        return $this->userAccounts->findByEmail($identifier);
    }

    /**
     * Проверяет password-identity.
     *
     * @param UserRecord $userRecord Учётка.
     * @param string $password Пароль.
     *
     * @return array<string, mixed> Строка identity.
     *
     * @throws AuthInvalidException Если нет identity или пароль неверен.
     */
    private function verifiedPasswordIdentity(UserRecord $userRecord, string $password): array
    {
        $identityRow = $this->identityRepository->findPassword($userRecord->getId());
        $secretHash = is_array($identityRow) ? ($identityRow['secret_hash'] ?? null) : null;
        if (!is_string($secretHash) || !password_verify($password, $secretHash)) {
            throw new AuthInvalidException();
        }

        return $identityRow;
    }

    /**
     * Создаёт сессию, cookie и last_used_at.
     *
     * @param UserRecord $userRecord Учётка.
     * @param array<string, mixed> $identityRow Identity.
     * @param bool $remember Долгий TTL.
     *
     * @return void
     */
    private function openSession(UserRecord $userRecord, array $identityRow, bool $remember): void
    {
        $this->sessionRuntime->dropIncoming();
        $ttlSeconds = $remember ? self::REMEMBER_TTL : self::DEFAULT_TTL;
        $this->sessionRuntime->issue($userRecord->getId(), 'user', $ttlSeconds);
        $this->identityRepository->markUsed((int) $identityRow['id'], DateTime::now());
    }

    /**
     * User-конверт или null, если kind не user / учётка мертва.
     *
     * @param array<string, mixed> $sessionRow Сессия.
     * @param string|null $sessionKind kind.
     *
     * @return array<string, mixed>|null Конверт.
     */
    private function userEnvelopeOrNull(array $sessionRow, ?string $sessionKind): ?array
    {
        if ($sessionKind !== 'user') {
            return null;
        }

        $userRecord = $this->activeUserFromSession($sessionRow);
        $userView = $userRecord === null ? null : $this->tryAssembleCurrentUser($userRecord);
        if ($userView === null) {
            return null;
        }

        return ['kind' => 'user', 'user' => $userView];
    }

    /**
     * Актор живой сессии или null, если учётка/группы недоступны.
     *
     * @param array<string, mixed> $sessionRow Сессия.
     *
     * @return RequestActor|null Актор.
     */
    private function actorFromSessionRow(array $sessionRow): ?RequestActor
    {
        $userRecord = $this->activeUserFromSession($sessionRow);
        if ($userRecord === null) {
            return null;
        }

        try {
            $userId = $userRecord->getId();

            return new RequestActor(
                $userId,
                $this->userGroups->getPermissionKeys($userId),
                $this->userGroups->hasBypass($userId),
            );
        } catch (UserNotFoundException) {
            return null;
        }
    }

    /**
     * Профиль сессии, если учётка жива.
     *
     * @param array<string, mixed> $sessionRow Сессия.
     *
     * @return UserRecord|null Учётка.
     */
    private function activeUserFromSession(array $sessionRow): ?UserRecord
    {
        try {
            $userRecord = $this->userAccounts->getById((int) $sessionRow['user_id']);
        } catch (UserNotFoundException) {
            return null;
        }

        if (!$userRecord->isActive()) {
            return null;
        }

        return $userRecord;
    }

    /**
     * Id групп автовыдачи; пусто недопустимо.
     *
     * @return array<int, int> Id.
     *
     * @throws AuthInvalidException Если таких групп нет.
     */
    private function assignOnRegisterIds(): array
    {
        $assignGroupIds = $this->userGroups->getAssignOnRegisterIds();
        if ($assignGroupIds === []) {
            throw new AuthInvalidException('Authentication failed');
        }

        return $assignGroupIds;
    }

    /**
     * Добавляет членства по id.
     *
     * @param int $userId Учётка.
     * @param array<int, int> $groupIds Id групп.
     *
     * @return void
     *
     * @throws AuthInvalidException Если группы нет.
     */
    private function addMembershipsById(int $userId, array $groupIds): void
    {
        foreach ($groupIds as $groupId) {
            try {
                $this->userGroups->addMember($userId, $groupId);
            } catch (UserNotFoundException) {
                throw new AuthInvalidException('Authentication failed');
            }
        }
    }

    /**
     * Создаёт учётку регистрации.
     *
     * @param string $login Логин.
     * @param string $email Почта.
     *
     * @return int Id.
     *
     * @throws AuthInvalidException Если поля битые.
     * @throws AuthDuplicateException Если unique.
     */
    private function createRegisteredUser(string $login, string $email): int
    {
        $normalizedEmail = trim($email);
        $profile = [
            'login' => $login,
            'name' => $login,
            'active' => true,
        ];
        if ($normalizedEmail !== '') {
            $profile['email'] = $normalizedEmail;
        }

        try {
            return $this->userAccounts->addFromInput($profile);
        } catch (UserDuplicateException $exception) {
            throw new AuthDuplicateException($exception);
        } catch (UserInvalidException $exception) {
            throw new AuthInvalidException('Authentication failed', $exception);
        }
    }
}
