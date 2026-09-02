<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Http;

use Mifrial\Core\Kernel\Dto\OutgoingCookie;
use Mifrial\Core\Kernel\Interface\Http\IHttpRequest;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;

/**
 * Изменяемый контекст cookie текущего процесса.
 */
final class RequestContext implements IRequestContext
{
    /**
     * @var array<string, string>
     */
    private array $incomingCookies = [];

    /**
     * @var array<int, OutgoingCookie>
     */
    private array $queuedCookies = [];

    /**
     * Сбрасывает входящие cookie и очередь исходящих.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->incomingCookies = [];
        $this->queuedCookies = [];
    }

    /**
     * Берёт входящие cookie из HTTP-снимка.
     *
     * @param IHttpRequest $httpRequest Снимок запроса.
     *
     * @return void
     */
    public function bindIncoming(IHttpRequest $httpRequest): void
    {
        $this->incomingCookies = [];
        $this->queuedCookies = [];
        foreach ($httpRequest->getCookieMap() as $cookieName => $cookieValue) {
            if (is_string($cookieName) && is_string($cookieValue) && $cookieValue !== '') {
                $this->incomingCookies[$cookieName] = $cookieValue;
            }
        }
    }

    /**
     * Возвращает входящую cookie.
     *
     * @param string $name Имя.
     *
     * @return string|null Значение или null.
     */
    public function incomingCookie(string $name): ?string
    {
        return $this->incomingCookies[$name] ?? null;
    }

    /**
     * Кладёт исходящую cookie в очередь ответа.
     *
     * @param OutgoingCookie $outgoingCookie Cookie.
     *
     * @return void
     */
    public function queueCookie(OutgoingCookie $outgoingCookie): void
    {
        $this->queuedCookies[] = $outgoingCookie;
    }

    /**
     * Отдаёт и очищает очередь исходящих cookie.
     *
     * @return array<int, OutgoingCookie> Очередь.
     */
    public function takeQueuedCookies(): array
    {
        $queuedCookies = $this->queuedCookies;
        $this->queuedCookies = [];

        return $queuedCookies;
    }
}
