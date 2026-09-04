<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Interface\Http;

use Mifrial\Core\Kernel\Dto\OutgoingCookie;
use Mifrial\Core\Kernel\Dto\RequestActor;

/**
 * Процессный снимок входящих cookie и очередь исходящих.
 */
interface IRequestContext
{
    /**
     * Сбрасывает входящие cookie и очередь исходящих.
     *
     * @return void
     */
    public function reset(): void;

    /**
     * Берёт входящие cookie из HTTP-снимка.
     *
     * @param IHttpRequest $httpRequest Снимок запроса.
     *
     * @return void
     */
    public function bindIncoming(IHttpRequest $httpRequest): void;

    /**
     * Возвращает входящую cookie.
     *
     * @param string $name Имя.
     *
     * @return string|null Значение или null.
     */
    public function incomingCookie(string $name): ?string;

    /**
     * Кладёт исходящую cookie в очередь ответа.
     *
     * @param OutgoingCookie $outgoingCookie Cookie.
     *
     * @return void
     */
    public function queueCookie(OutgoingCookie $outgoingCookie): void;

    /**
     * Отдаёт и очищает очередь исходящих cookie.
     *
     * @return array<int, OutgoingCookie> Очередь.
     */
    public function takeQueuedCookies(): array;

    /**
     * Кладёт снимок актора сессии.
     *
     * @param RequestActor|null $requestActor Актор или null.
     *
     * @return void
     */
    public function setActor(?RequestActor $requestActor): void;

    /**
     * Возвращает актора текущего запроса.
     *
     * @return RequestActor|null Актор или null.
     */
    public function getActor(): ?RequestActor;
}
