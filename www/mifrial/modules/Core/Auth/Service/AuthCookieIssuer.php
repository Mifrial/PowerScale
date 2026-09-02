<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Service;

use Mifrial\Core\Auth\Dto\AuthSettings;
use Mifrial\Core\Kernel\Dto\OutgoingCookie;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;

/**
 * Очередь cookie сессии и CSRF.
 */
final class AuthCookieIssuer
{
    private const SESSION_NAME = 'mifrial-session';

    private const CSRF_NAME = 'csrf-token';

    /**
     * Создаёт issuer.
     *
     * @param IRequestContext $requestContext Контекст процесса.
     * @param AuthSettings $authSettings Secure-флаг.
     *
     * @return void
     */
    public function __construct(
        private readonly IRequestContext $requestContext,
        private readonly AuthSettings $authSettings,
    ) {
    }

    /**
     * Кладёт сессию и csrf-token с одним TTL.
     *
     * @param string $rawSessionToken Сырой токен сессии.
     * @param string $csrfToken Токен CSRF.
     * @param int $ttlSeconds TTL.
     *
     * @return void
     */
    public function issue(string $rawSessionToken, string $csrfToken, int $ttlSeconds): void
    {
        $this->requestContext->queueCookie($this->cookie(self::SESSION_NAME, $rawSessionToken, true, $ttlSeconds));
        $this->requestContext->queueCookie($this->cookie(self::CSRF_NAME, $csrfToken, false, $ttlSeconds));
    }

    /**
     * Гасит обе cookie.
     *
     * @return void
     */
    public function expire(): void
    {
        $this->requestContext->queueCookie($this->cookie(self::SESSION_NAME, '', true, 0));
        $this->requestContext->queueCookie($this->cookie(self::CSRF_NAME, '', false, 0));
    }

    /**
     * Сырой токен сессии из входящей cookie.
     *
     * @return string|null Токен или null.
     */
    public function incomingSessionToken(): ?string
    {
        return $this->requestContext->incomingCookie(self::SESSION_NAME);
    }

    /**
     * Собирает исходящую cookie.
     *
     * @param string $name Имя.
     * @param string $value Значение.
     * @param bool $httpOnly HttpOnly.
     * @param int $ttlSeconds Max-Age.
     *
     * @return OutgoingCookie Cookie.
     */
    private function cookie(string $name, string $value, bool $httpOnly, int $ttlSeconds): OutgoingCookie
    {
        return new OutgoingCookie(
            $name,
            $value,
            $httpOnly,
            $this->authSettings->cookieSecure(),
            $ttlSeconds,
        );
    }
}
