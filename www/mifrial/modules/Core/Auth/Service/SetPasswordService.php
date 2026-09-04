<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Service;

use Mifrial\Core\Auth\Exception\AuthInvalidException;
use Mifrial\Core\Auth\Exception\AuthPolicyException;
use Mifrial\Core\Auth\Repository\AuthSessionRepository;
use Mifrial\Core\Auth\Repository\UserIdentityRepository;
use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Exception\UserNotFoundException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Core\User\Interface\Service\IUserAccounts;

/**
 * Смена пароля своей или чужой учётки.
 */
final class SetPasswordService
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Guard актора.
     * @param IUserAccounts $userAccounts Учётки.
     * @param UserIdentityRepository $identityRepository Identity.
     * @param AuthSessionRepository $sessionRepository Сессии.
     * @param PasswordPolicyService $passwordPolicyService Политика.
     * @param AuthCookieIssuer $cookieIssuer Входящая cookie.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IUserAccounts $userAccounts,
        private readonly UserIdentityRepository $identityRepository,
        private readonly AuthSessionRepository $sessionRepository,
        private readonly PasswordPolicyService $passwordPolicyService,
        private readonly AuthCookieIssuer $cookieIssuer,
    ) {
    }

    /**
     * Меняет password-identity и гасит лишние сессии цели.
     *
     * @param int $userId Целевая учётка.
     * @param string $newPassword Новый пароль.
     * @param string|null $currentPassword Текущий; себе обязателен.
     *
     * @return true Успех.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     * @throws AuthInvalidException Нет identity или неверный текущий пароль.
     * @throws AuthPolicyException Слабый новый пароль.
     * @throws UserNotFoundException Нет учётки.
     */
    public function setPassword(int $userId, string $newPassword, ?string $currentPassword): bool
    {
        $requestActor = $this->userAccess->requireActor();
        $this->userAccounts->getById($userId);
        $this->assertCaller($requestActor, $userId);
        $this->passwordPolicyService->assertPasswordForUser($userId, $newPassword);
        $identityRow = $this->identityRepository->findPassword($userId);
        if ($identityRow === null) {
            throw new AuthInvalidException();
        }

        $this->assertCurrentIfSelf(
            $requestActor,
            $userId,
            $currentPassword,
            (string) $identityRow['secret_hash'],
        );
        $this->identityRepository->updateSecretHash(
            (int) $identityRow['id'],
            password_hash($newPassword, PASSWORD_DEFAULT),
        );
        $this->sessionRepository->deleteByUserId($userId, $this->keepSessionId($requestActor, $userId));

        return true;
    }

    /**
     * Себе — всегда; чужому — bypass или `auth.user.edit`.
     *
     * @param RequestActor $requestActor Актор.
     * @param int $userId Цель.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     */
    private function assertCaller(RequestActor $requestActor, int $userId): void
    {
        if ($requestActor->getUserId() === $userId) {
            return;
        }

        if ($requestActor->hasKey('auth.user.edit')) {
            return;
        }

        throw new ActionException('AUTH_DENIED', 'Permission denied');
    }

    /**
     * Себе требует верный текущий пароль.
     *
     * @param RequestActor $requestActor Актор.
     * @param int $userId Цель.
     * @param string|null $currentPassword Текущий.
     * @param string $secretHash Hash identity.
     *
     * @return void
     *
     * @throws AuthInvalidException Если себе и пароль не сошёлся.
     */
    private function assertCurrentIfSelf(
        RequestActor $requestActor,
        int $userId,
        ?string $currentPassword,
        string $secretHash,
    ): void {
        if ($requestActor->getUserId() !== $userId) {
            return;
        }

        $plain = is_string($currentPassword) ? $currentPassword : '';
        if ($plain === '' || !password_verify($plain, $secretHash)) {
            throw new AuthInvalidException();
        }
    }

    /**
     * Своя сессия остаётся; чужой цели — снести все.
     *
     * @param RequestActor $requestActor Актор.
     * @param int $userId Цель.
     *
     * @return int|null Id сессии, которую оставить.
     */
    private function keepSessionId(RequestActor $requestActor, int $userId): ?int
    {
        $rawToken = $this->cookieIssuer->incomingSessionToken();
        if ($requestActor->getUserId() !== $userId || $rawToken === null || $rawToken === '') {
            return null;
        }

        $sessionRow = $this->sessionRepository->findByTokenHash(hash('sha256', $rawToken));

        return $sessionRow === null ? null : (int) $sessionRow['id'];
    }
}
