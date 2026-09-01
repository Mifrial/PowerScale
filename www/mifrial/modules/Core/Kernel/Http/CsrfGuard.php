<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Http;

use Mifrial\Core\Kernel\Interface\Http\IHttpRequest;

/**
 * Проверка double-submit CSRF.
 */
final class CsrfGuard
{
    /**
     * Проверяет double-submit токен куки и заголовка.
     *
     * @param IHttpRequest $httpRequest Снимок запроса.
     *
     * @return bool true, если токены совпадают и не пусты.
     */
    public function isValid(IHttpRequest $httpRequest): bool
    {
        $cookieToken = $httpRequest->getCookieValue('csrf-token');
        $headerToken = $httpRequest->getHeader('X-CSRF-Token');
        if (!is_string($cookieToken) || $cookieToken === '') {
            return false;
        }

        if (!is_string($headerToken) || $headerToken === '') {
            return false;
        }

        return hash_equals($cookieToken, $headerToken);
    }
}
