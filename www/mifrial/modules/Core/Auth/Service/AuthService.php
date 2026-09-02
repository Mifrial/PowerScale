<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Service;

use Mifrial\Core\Auth\Exception\AuthDuplicateException;
use Mifrial\Core\Auth\Exception\AuthInvalidException;
use Mifrial\Core\Auth\Exception\AuthPolicyException;
use Mifrial\Core\Auth\Repository\AuthSessionRepository;
use Mifrial\Core\Auth\Repository\UserIdentityRepository;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\User\Dto\UserRecord;
use Mifrial\Core\User\Exception\UserDuplicateException;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Exception\UserNotFoundException;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Interface\Service\IUserGroups;

/**
 * Вход, регистрация, сессия и «кто я».
 */
final class AuthService
{
    private const DEFAULT_TTL = 86400;

    private const REMEMBER_TTL = 2592000;

    private const PLAYER_GROUP_NAME = 'Игрок';

    /**
     * Создаёт сценарий Auth.
     *
     * @param IUserAccounts $userAccounts Учётки.
     * @param IUserGroups $userGroups Группы.
     * @param UserIdentityRepository $identityRepository Identity.
     * @param AuthSessionRepository $sessionRepository Сессии.
     * @param AuthCookieIssuer $cookieIssuer Cookie.
     * @param AuthUserAssembler $userAssembler JSON User.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccounts $userAccounts,
        private readonly IUserGroups $userGroups,
        private readonly UserIdentityRepository $identityRepository,
        private readonly AuthSessionRepository $sessionRepository,
        private readonly AuthCookieIssuer $cookieIssuer,
        private readonly AuthUserAssembler $userAssembler,
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

        return ['user' => $this->userAssembler->view($userRecord, DateTime::now())];
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
     * @throws AuthInvalidException Если нет группы «Игрок» или поля битые.
     * @throws AuthDuplicateException Если login/email заняты.
     */
    public function register(string $login, string $email, string $password): array
    {
        $this->assertPasswordPolicy($password);
        $playerGroup = $this->userGroups->findByName(self::PLAYER_GROUP_NAME);
        if ($playerGroup === null) {
            throw new AuthInvalidException('Authentication failed');
        }

        $userId = $this->createRegisteredUser($login, $email);
        $this->identityRepository->addPassword($userId, password_hash($password, PASSWORD_DEFAULT));
        $this->userGroups->addMember($userId, (int) $playerGroup->values()['id']);

        return $this->login($login, $password, false);
    }

    /**
     * Завершает текущую сессию, если cookie есть.
     *
     * @return void
     */
    public function logout(): void
    {
        $sessionRow = $this->liveSessionRow();
        if ($sessionRow !== null) {
            $this->sessionRepository->deleteById((int) $sessionRow['id']);
        }

        $this->cookieIssuer->expire();
    }

    /**
     * Текущий пользователь или null.
     *
     * @return array<string, mixed>|null User JSON.
     */
    public function currentUser(): ?array
    {
        $sessionRow = $this->liveSessionRow();
        if ($sessionRow === null) {
            return null;
        }

        $userRecord = $this->activeUserFromSession($sessionRow);
        $userView = $userRecord === null ? null : $this->tryAssembleCurrentUser($userRecord);
        if ($userView === null) {
            $this->sessionRepository->deleteById((int) $sessionRow['id']);
        }

        return $userView;
    }

    /**
     * Политика пароля v1.
     *
     * @return array{minLength: int, requireMixedCase: bool, requireDigit: bool, requireSpecialChar: bool}
     */
    public function passwordPolicy(): array
    {
        return [
            'minLength' => 4,
            'requireMixedCase' => false,
            'requireDigit' => false,
            'requireSpecialChar' => false,
        ];
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
        $identityRow = $this->identityRepository->findPassword((int) $userRecord->values()['id']);
        $lastLogin = $identityRow['last_used_at'] ?? null;

        return $this->userAssembler->view(
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
        if ($userRecord === null || $userRecord->values()['active'] !== true) {
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
        $identityRow = $this->identityRepository->findPassword((int) $userRecord->values()['id']);
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
        $this->dropIncomingSession();
        $ttlSeconds = $remember ? self::REMEMBER_TTL : self::DEFAULT_TTL;
        $rawToken = bin2hex(random_bytes(32));
        $this->sessionRepository->add(
            (int) $userRecord->values()['id'],
            hash('sha256', $rawToken),
            DateTime::fromUnix(time() + $ttlSeconds),
        );
        $this->cookieIssuer->issue($rawToken, bin2hex(random_bytes(32)), $ttlSeconds);
        $this->identityRepository->markUsed((int) $identityRow['id'], DateTime::now());
    }

    /**
     * Сносит сессию текущей cookie, если она жива.
     *
     * @return void
     */
    private function dropIncomingSession(): void
    {
        $sessionRow = $this->liveSessionRow();
        if ($sessionRow !== null) {
            $this->sessionRepository->deleteById((int) $sessionRow['id']);
        }
    }

    /**
     * Живая сессия по входящей cookie.
     *
     * @return array<string, mixed>|null Строка.
     */
    private function liveSessionRow(): ?array
    {
        $rawToken = $this->cookieIssuer->incomingSessionToken();
        $sessionRow = ($rawToken === null || $rawToken === '')
            ? null
            : $this->sessionRepository->findByTokenHash(hash('sha256', $rawToken));
        if ($sessionRow === null) {
            return null;
        }

        $expiresAt = $sessionRow['expires_at'] ?? null;
        if (!$expiresAt instanceof DateTime || $expiresAt->toUnix() < time()) {
            $this->sessionRepository->deleteById((int) $sessionRow['id']);

            return null;
        }

        return $sessionRow;
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

        if ($userRecord->values()['active'] !== true) {
            return null;
        }

        return $userRecord;
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

    /**
     * Политика minLength 4.
     *
     * @param string $password Пароль.
     *
     * @return void
     *
     * @throws AuthPolicyException Если короче 4.
     */
    private function assertPasswordPolicy(string $password): void
    {
        if (strlen($password) < 4) {
            throw new AuthPolicyException();
        }
    }
}
