<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Dto;

use Mifrial\Core\Kernel\Interface\Http\IHttpRequest;

/**
 * Снимок принятого multipart-файла из PHP.
 */
final class IncomingUpload
{
    /**
     * Собирает снимок.
     *
     * @param string|null $temporaryPath Временный файл PHP.
     * @param string $originalName Имя от клиента.
     * @param int $size Байт.
     * @param int $uploadError Код UPLOAD_ERR_*.
     * @param string|null $clientMime Клиентский тип.
     * @param int $contentLength Content-Length запроса.
     *
     * @return void
     */
    private function __construct(
        private readonly ?string $temporaryPath,
        private readonly string $originalName,
        private readonly int $size,
        private readonly int $uploadError,
        private readonly ?string $clientMime,
        private readonly int $contentLength,
    ) {
    }

    /**
     * Читает поле `file` и Content-Length из запроса.
     *
     * @param IHttpRequest $httpRequest Снимок запроса.
     *
     * @return self Вход.
     */
    public static function fromRequest(IHttpRequest $httpRequest): self
    {
        return self::fromFileMap($httpRequest->getFileMap(), self::contentLength($httpRequest));
    }

    /**
     * Собирает вход из карты $_FILES.
     *
     * @param array<string, mixed> $fileMap Поле file.
     * @param int $contentLength Content-Length.
     *
     * @return self Вход.
     */
    public static function fromFileMap(array $fileMap, int $contentLength): self
    {
        $entry = $fileMap['file'] ?? null;
        if (!is_array($entry)) {
            return new self(null, '', 0, UPLOAD_ERR_NO_FILE, null, $contentLength);
        }

        return new self(
            self::optionalString($entry['tmp_name'] ?? null),
            self::stringValue($entry['name'] ?? null),
            self::intValue($entry['size'] ?? null),
            self::intValue($entry['error'] ?? null, UPLOAD_ERR_NO_FILE),
            self::optionalString($entry['type'] ?? null),
            $contentLength,
        );
    }

    /**
     * Временный файл PHP.
     *
     * @return string|null Путь или null.
     */
    public function getTemporaryPath(): ?string
    {
        return $this->temporaryPath;
    }

    /**
     * Имя от клиента.
     *
     * @return string Имя.
     */
    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    /**
     * Размер принятого файла.
     *
     * @return int Байт.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Код UPLOAD_ERR_*.
     *
     * @return int Код.
     */
    public function getUploadError(): int
    {
        return $this->uploadError;
    }

    /**
     * Клиентский тип (не доверять).
     *
     * @return string|null Тип.
     */
    public function getClientMime(): ?string
    {
        return $this->clientMime;
    }

    /**
     * Content-Length запроса.
     *
     * @return int Байт.
     */
    public function getContentLength(): int
    {
        return $this->contentLength;
    }

    /**
     * Content-Length из заголовка.
     *
     * @param IHttpRequest $httpRequest Снимок.
     *
     * @return int Байт или 0.
     */
    private static function contentLength(IHttpRequest $httpRequest): int
    {
        $headerValue = $httpRequest->getHeader('Content-Length');

        return is_string($headerValue) && ctype_digit($headerValue) ? (int) $headerValue : 0;
    }

    /**
     * Строка или пусто.
     *
     * @param mixed $value Значение.
     *
     * @return string Текст.
     */
    private static function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * Необязательная строка.
     *
     * @param mixed $value Значение.
     *
     * @return string|null Текст или null.
     */
    private static function optionalString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Целое из скаляра.
     *
     * @param mixed $value Значение.
     * @param int $default Дефолт.
     *
     * @return int Число.
     */
    private static function intValue(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && ctype_digit($value) ? (int) $value : $default;
    }
}
