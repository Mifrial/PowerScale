<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Http;

use JsonException;
use Mifrial\Core\Kernel\Dto\ActionResponse;

/**
 * Отправка JSON-конверта через PHP SAPI.
 */
final class ResponseEmitter
{
    /**
     * Создаёт отправитель JSON-ответа.
     *
     * @param HttpStatusMapper $statusMapper Карта конверта на HTTP-код.
     *
     * @return void
     */
    public function __construct(
        private readonly HttpStatusMapper $statusMapper = new HttpStatusMapper(),
    ) {
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
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($this->statusMapper->statusFor($response));
        echo $this->encodeJson($response);

        exit;
    }

    /**
     * Сериализует конверт в JSON.
     *
     * @param ActionResponse $response Конверт ответа.
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
}
