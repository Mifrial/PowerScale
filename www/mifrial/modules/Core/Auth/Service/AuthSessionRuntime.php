<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Service;

use Mifrial\Core\Auth\Repository\AuthSessionRepository;
use Mifrial\Core\Kernel\Value\DateTime;

/**
 * Cookie и строки `auth_session`.
 */
final class AuthSessionRuntime
{
    /**
     * Создаёт контур сессии.
     *
     * @param AuthSessionRepository $sessionRepository Строки.
     * @param AuthCookieIssuer $cookieIssuer Cookie.
     *
     * @return void
     */
    public function __construct(
        private readonly AuthSessionRepository $sessionRepository,
        private readonly AuthCookieIssuer $cookieIssuer,
    ) {
    }

    /**
     * Живая сессия по входящей cookie.
     *
     * @return array<string, mixed>|null Строка.
     */
    public function liveRow(): ?array
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
     * Сносит сессию текущей cookie, если она жива.
     *
     * @return void
     */
    public function dropIncoming(): void
    {
        $sessionRow = $this->liveRow();
        if ($sessionRow !== null) {
            $this->sessionRepository->deleteById((int) $sessionRow['id']);
        }
    }

    /**
     * Пишет строку сессии и cookie.
     *
     * @param int|null $userId Учётка или null у гостя.
     * @param string $kind `user` или `guest`.
     * @param int $ttlSeconds TTL cookie.
     *
     * @return void
     */
    public function issue(?int $userId, string $kind, int $ttlSeconds): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $this->sessionRepository->add(
            $userId,
            hash('sha256', $rawToken),
            DateTime::fromUnix(time() + $ttlSeconds),
            $kind,
        );
        $this->cookieIssuer->issue($rawToken, bin2hex(random_bytes(32)), $ttlSeconds);
    }

    /**
     * Гасит cookie сессии и CSRF.
     *
     * @return void
     */
    public function expire(): void
    {
        $this->cookieIssuer->expire();
    }

    /**
     * Удаляет строку по id.
     *
     * @param int $sessionId Id.
     *
     * @return void
     */
    public function deleteById(int $sessionId): void
    {
        $this->sessionRepository->deleteById($sessionId);
    }

    /**
     * Согласованный kind строки или null, если пара битая.
     *
     * @param array<string, mixed> $sessionRow Сессия.
     *
     * @return string|null `user`, `guest` или битая.
     */
    public function kind(array $sessionRow): ?string
    {
        $userId = $sessionRow['user_id'] ?? null;
        $hasUser = is_int($userId) && $userId > 0;
        $kindValue = $sessionRow['kind'] ?? null;
        $sessionKind = is_string($kindValue) && $kindValue !== '' ? $kindValue : null;
        if ($sessionKind === null && $hasUser) {
            $sessionKind = 'user';
        }

        if ($sessionKind === 'user' && $hasUser) {
            return 'user';
        }

        if ($sessionKind === 'guest' && !$hasUser) {
            return 'guest';
        }

        return null;
    }
}
