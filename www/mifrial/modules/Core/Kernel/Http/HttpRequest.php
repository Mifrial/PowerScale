<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Http;

use JsonException;
use Mifrial\Core\Kernel\Interface\Http\IHttpRequest;

/**
 * Неизменяемый снимок входящего HTTP-запроса.
 */
final readonly class HttpRequest implements IHttpRequest
{
    /**
     * Создаёт неизменяемый снимок входных данных HTTP-запроса.
     *
     * @param string $method HTTP-метод запроса.
     * @param string $path Путь запроса.
     * @param array{query: array<string, mixed>, post: array<string, mixed>, cookies: array<string, mixed>} $inputBags
     *     Query, POST и cookie запроса.
     * @param array<string, string> $headers HTTP-заголовки.
     * @param string|null $rawBody Необработанное тело запроса.
     *
     * @return void
     */
    private function __construct(
        private string $method,
        private string $path,
        private array $inputBags,
        private array $headers,
        private ?string $rawBody,
    ) {
    }

    /**
     * Создаёт запрос из глобального состояния PHP SAPI.
     *
     * @return self Read-only снимок HTTP-запроса.
     */
    public static function fromGlobals(): self
    {
        $rawBody = file_get_contents('php://input');

        return new self(
            is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET',
            self::resolvePath($_SERVER['REQUEST_URI'] ?? null),
            [
                'query' => is_array($_GET) ? $_GET : [],
                'post' => is_array($_POST) ? $_POST : [],
                'cookies' => is_array($_COOKIE) ? $_COOKIE : [],
            ],
            self::resolveHeaders(),
            $rawBody === false ? null : $rawBody,
        );
    }

    /**
     * Возвращает HTTP-метод запроса.
     *
     * @return string HTTP-метод.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Возвращает путь запроса без query string.
     *
     * @return string Путь запроса.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Возвращает значение query-параметра.
     *
     * @param string $name Имя параметра.
     *
     * @return mixed Значение параметра или null.
     */
    public function getQueryValue(string $name): mixed
    {
        return $this->inputBags['query'][$name] ?? null;
    }

    /**
     * Возвращает значение POST-параметра.
     *
     * @param string $name Имя параметра.
     *
     * @return mixed Значение параметра или null.
     */
    public function getPostValue(string $name): mixed
    {
        return $this->inputBags['post'][$name] ?? null;
    }

    /**
     * Возвращает значение HTTP-заголовка.
     *
     * @param string $name Имя заголовка.
     *
     * @return string|null Значение заголовка или null.
     */
    public function getHeader(string $name): ?string
    {
        $normalizedName = strtolower($name);

        foreach ($this->headers as $headerName => $headerValue) {
            if (strtolower($headerName) === $normalizedName) {
                return $headerValue;
            }
        }

        return null;
    }

    /**
     * Возвращает значение cookie.
     *
     * @param string $name Имя cookie.
     *
     * @return mixed Значение cookie или null.
     */
    public function getCookieValue(string $name): mixed
    {
        return $this->inputBags['cookies'][$name] ?? null;
    }

    /**
     * Возвращает карту входящих cookie.
     *
     * @return array<string, mixed> Имя => значение.
     */
    public function getCookieMap(): array
    {
        return $this->inputBags['cookies'];
    }

    /**
     * Декодирует JSON-тело запроса.
     *
     * @return mixed Декодированная полезная нагрузка или null для пустого тела.
     *
     * @throws JsonException Если тело содержит некорректный JSON.
     */
    public function getJsonPayload(): mixed
    {
        if ($this->rawBody === null || $this->rawBody === '') {
            return null;
        }

        return json_decode($this->rawBody, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Извлекает путь из URI.
     *
     * @param mixed $requestUri Значение REQUEST_URI.
     *
     * @return string Путь запроса.
     */
    private static function resolvePath(mixed $requestUri): string
    {
        if (!is_string($requestUri) || $requestUri === '') {
            return '/';
        }

        $path = parse_url($requestUri, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    /**
     * Собирает HTTP-заголовки из серверных переменных.
     *
     * @return array<string, string> Карта HTTP-заголовков.
     */
    private static function resolveHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $serverName => $serverValue) {
            if (
                is_string($serverValue)
                && (str_starts_with($serverName, 'HTTP_') || $serverName === 'CONTENT_TYPE')
            ) {
                $headerName = str_replace('_', '-', strtolower(preg_replace('/^HTTP_/', '', $serverName)));
                $headers[$headerName] = $serverValue;
            }
        }

        return $headers;
    }
}
