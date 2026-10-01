<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Service;

use Mifrial\Core\Auth\Exception\AuthInvalidException;
use Mifrial\Core\Auth\Exception\AuthPolicyException;
use Mifrial\Core\Auth\Interface\Service\IPasswordResetNotifier;
use Mifrial\Core\Auth\Repository\AuthSessionRepository;
use Mifrial\Core\Auth\Repository\PasswordResetRepository;
use Mifrial\Core\Auth\Repository\UserIdentityRepository;
use Mifrial\Core\Kernel\Interface\Service\ITransactionRunner;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\User\Dto\UserRecord;
use Mifrial\Core\User\Interface\Service\IUserAccounts;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

/**
 * Старт и завершение сброса пароля.
 */
final class PasswordResetService
{
    private const TOKEN_TTL_SECONDS = 3600;

    /**
     * Создаёт сценарий.
     *
     * @param IUserAccounts $userAccounts Учётки.
     * @param UserIdentityRepository $identityRepository Identity.
     * @param PasswordResetRepository $resetRepository Токены.
     * @param AuthSessionRepository $sessionRepository Сессии.
     * @param PasswordPolicyService $passwordPolicyService Политика.
     * @param IPasswordResetNotifier $resetNotifier Доставка токена.
     * @param ITransactionRunner $transactionRunner Единица работы.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccounts $userAccounts,
        private readonly UserIdentityRepository $identityRepository,
        private readonly PasswordResetRepository $resetRepository,
        private readonly AuthSessionRepository $sessionRepository,
        private readonly PasswordPolicyService $passwordPolicyService,
        private readonly IPasswordResetNotifier $resetNotifier,
        private readonly ITransactionRunner $transactionRunner,
    ) {
    }

    /**
     * Ищет учётку и при наличии email выпускает токен.
     *
     * @param string $loginOrEmail Логин или почта.
     *
     * @return array{status: string, login?: string, resetToken?: string} Исход.
     *
     * @throws AuthInvalidException Если идентификатор пуст.
     */
    public function startPasswordReset(string $loginOrEmail): array
    {
        $identifier = trim($loginOrEmail);
        if ($identifier === '') {
            throw new AuthInvalidException();
        }

        $userRecord = $this->findResetTarget($identifier);
        if ($userRecord === null || !$userRecord->isActive()) {
            return ['status' => 'not_found'];
        }

        if ($userRecord->getEmail() === null) {
            return ['status' => 'no_email'];
        }

        $rawToken = $this->newRawToken();
        $login = $userRecord->getLogin();
        $email = (string) $userRecord->getEmail();
        $userId = $userRecord->getId();
        $jobId = $this->transactionRunner->run(function () use ($userId, $rawToken, $login, $email): int {
            $this->storeToken($userId, $rawToken);

            return $this->resetNotifier->enqueue($login, $rawToken, $email);
        });
        $this->resetNotifier->deliver($jobId);
        $payload = ['status' => 'sent', 'login' => $userRecord->getLogin()];
        if ($this->resetNotifier->shouldExposeRawToken()) {
            $payload['resetToken'] = $rawToken;
        }

        return $payload;
    }

    /**
     * Меняет пароль по токену и гасит сессии.
     *
     * @param string $login Логин.
     * @param string $resetToken Сырой токен.
     * @param string $newPassword Новый пароль.
     *
     * @return true Успех.
     *
     * @throws AuthInvalidException Если токен/логин неверны.
     * @throws AuthPolicyException Если пароль слабый.
     */
    public function finalPasswordReset(string $login, string $resetToken, string $newPassword): bool
    {
        $normalizedLogin = trim($login);
        $rawToken = trim($resetToken);
        $userRecord = $this->activeUserByLogin($normalizedLogin);
        $this->passwordPolicyService->assertPasswordForUser($userRecord->getId(), $newPassword);
        $resetRow = $this->liveResetRow($rawToken, $userRecord->getId());
        $identityRow = $this->identityRepository->findPassword($userRecord->getId());
        if ($identityRow === null) {
            throw new AuthInvalidException();
        }

        $secretHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $resetId = (int) $resetRow['id'];
        $identityId = (int) $identityRow['id'];
        $userId = $userRecord->getId();
        $this->transactionRunner->run(function () use ($resetId, $identityId, $secretHash, $userId): mixed {
            $this->resetRepository->consume($resetId);
            $this->identityRepository->updateSecretHash($identityId, $secretHash);
            $this->sessionRepository->deleteByUserId($userId);

            return null;
        });

        return true;
    }

    /**
     * Login, иначе email.
     *
     * @param string $identifier Trim-идентификатор.
     *
     * @return UserRecord|null Учётка.
     */
    private function findResetTarget(string $identifier): ?UserRecord
    {
        $byLogin = $this->userAccounts->findByLogin($identifier);
        if ($byLogin !== null) {
            return $byLogin;
        }

        return $this->userAccounts->findByEmail($identifier);
    }

    /**
     * Живая учётка по login.
     *
     * @param string $login Логин.
     *
     * @return UserRecord Учётка.
     *
     * @throws AuthInvalidException Если нет или неактивна.
     */
    private function activeUserByLogin(string $login): UserRecord
    {
        if ($login === '') {
            throw new AuthInvalidException();
        }

        $userRecord = $this->userAccounts->findByLogin($login);
        if ($userRecord === null || !$userRecord->isActive()) {
            throw new AuthInvalidException();
        }

        return $userRecord;
    }

    /**
     * Живой неиспользованный токен этой учётки.
     *
     * @param string $rawToken Сырой токен.
     * @param int $userId Учётка.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws AuthInvalidException Если токен битый.
     */
    private function liveResetRow(string $rawToken, int $userId): array
    {
        if ($rawToken === '') {
            throw new AuthInvalidException();
        }

        $resetRow = $this->resetRepository->findByTokenHash(hash('sha256', $rawToken));
        if ($resetRow === null || !$this->isLiveResetRow($resetRow, $userId)) {
            throw new AuthInvalidException();
        }

        return $resetRow;
    }

    /**
     * Проверяет принадлежность, срок и used_at.
     *
     * @param array<string, mixed> $resetRow Строка токена.
     * @param int $userId Учётка.
     *
     * @return bool true, если токен живой.
     */
    private function isLiveResetRow(array $resetRow, int $userId): bool
    {
        $expiresAt = $resetRow['expires_at'] ?? null;
        $usedAt = $resetRow['used_at'] ?? null;
        if ((int) $resetRow['user_id'] !== $userId || $usedAt !== null) {
            return false;
        }

        return $expiresAt instanceof DateTime && $expiresAt->toUnix() >= time();
    }

    /**
     * Сырой токен без записи в БД.
     *
     * @return string Токен.
     */
    private function newRawToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Пишет хеш; старые неиспользованные удаляет.
     *
     * @param int $userId Учётка.
     * @param string $rawToken Сырой токен.
     *
     * @return void
     */
    private function storeToken(int $userId, string $rawToken): void
    {
        $this->resetRepository->deleteUnusedForUser($userId);
        $this->resetRepository->add(
            $userId,
            hash('sha256', $rawToken),
            DateTime::fromUnix(time() + self::TOKEN_TTL_SECONDS),
        );
    }
}
