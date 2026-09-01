<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Interface\Http;

use JsonException;

/**
 * Контракт снимка HTTP-запроса.
 */
interface IHttpRequest
{
    /**
     * Возвращает HTTP-метод запроса.
     *
     * @return string HTTP-метод.
     */
    public function getMethod(): string;

    /**
     * Возвращает путь запроса без query string.
     *
     * @return string Путь запроса.
     */
    public function getPath(): string;

    /**
     * Возвращает значение query-параметра.
     *
     * @param string $name Имя параметра.
     *
     * @return mixed Значение параметра или null.
     */
    public function getQueryValue(string $name): mixed;

    /**
     * Возвращает значение POST-параметра.
     *
     * @param string $name Имя параметра.
     *
     * @return mixed Значение параметра или null.
     */
    public function getPostValue(string $name): mixed;

    /**
     * Возвращает значение HTTP-заголовка.
     *
     * @param string $name Имя заголовка.
     *
     * @return string|null Значение заголовка или null.
     */
    public function getHeader(string $name): ?string;

    /**
     * Возвращает значение cookie.
     *
     * @param string $name Имя cookie.
     *
     * @return mixed Значение cookie или null.
     */
    public function getCookieValue(string $name): mixed;

    /**
     * Декодирует JSON-тело запроса.
     *
     * @return mixed Декодированная полезная нагрузка или null для пустого тела.
     *
     * @throws JsonException Если тело содержит некорректный JSON.
     */
    public function getJsonPayload(): mixed;
}
