<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Http;

use JsonException;
use Mifrial\Core\Kernel\Dto\ActionResponse;
use Mifrial\Core\Kernel\Interface\Http\IHttpRequest;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;

/**
 * Отправка JSON-конверта через PHP SAPI.
 */
final class ResponseEmitter
{
    /**
     * Создаёт отправитель JSON-ответа.
     *
     * @param HttpStatusMapper $statusMapper Карта конверта на HTTP-код.
     * @param IRequestContext $requestContext Очередь исходящих cookie.
     *
     * @return void
     */
    public function __construct(
        private readonly HttpStatusMapper $statusMapper = new HttpStatusMapper(),
        private readonly IRequestContext $requestContext = new RequestContext(),
    ) {
    }

    /**
     * Сбрасывает контекст и копирует входящие cookie запроса.
     *
     * @param IHttpRequest $httpRequest Снимок запроса.
     *
     * @return void
     */
    public function beginRequest(IHttpRequest $httpRequest): void
    {
        $this->requestContext->reset();
        $this->requestContext->bindIncoming($httpRequest);
    }

    /**
     * Отправляет JSON-ответ через PHP SAPI.
     *
     * @param ActionResponse $response Ответ приложения.
     *
     * @return never Управление не возвращается в вызывающий код.
     */
    public function emitJson(ActionResponse $response): never
    {
        foreach ($this->queuedCookieHeaders() as $cookieHeader) {
            header('Set-Cookie: ' . $cookieHeader, false);
        }

        header('Content-Type: application/json; charset=utf-8');
        http_response_code($this->statusMapper->statusFor($response));
        echo $this->encodeJson($response);

        exit;
    }

    /**
     * Сериализует конверт в JSON.
     *
     * @param ActionResponse $response Ответ приложения.
     *
     * @return string JSON-тело.
     */
    public function encodeJson(ActionResponse $response): string
    {
        try {
            return json_encode($response->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return '{"success":false,"data":null,"error":{"code":"INTERNAL","message":"Internal error"}}';
        }
    }

    /**
     * Собирает значения Set-Cookie из очереди контекста.
     *
     * @return array<int, string> Строки без имени заголовка.
     */
    public function queuedCookieHeaders(): array
    {
        $cookieHeaders = [];
        foreach ($this->requestContext->takeQueuedCookies() as $outgoingCookie) {
            $cookieHeaders[] = $outgoingCookie->headerLine();
        }

        return $cookieHeaders;
    }
}
