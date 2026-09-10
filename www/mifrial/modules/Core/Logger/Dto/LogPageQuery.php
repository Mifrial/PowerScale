<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Параметры ctor — поля запроса страницы, не порты.

namespace Mifrial\Core\Logger\Dto;

/**
 * Проверенный запрос страницы журнала для репозитория.
 */
final class LogPageQuery
{
    /**
     * Собирает запрос.
     *
     * @param int $limit Размер 1…100.
     * @param int $offset Сдвиг.
     * @param string|null $level Уровень или null.
     * @param string|null $source Источник или null.
     * @param bool $sourceContains LIKE, иначе equals.
     * @param string|null $errorCode Код или null.
     * @param bool $errorCodeContains LIKE, иначе equals.
     * @param int|null $fromUnix Нижняя граница unix.
     * @param int|null $toUnix Верхняя граница unix.
     *
     * @return void
     */
    public function __construct(
        private readonly int $limit,
        private readonly int $offset,
        private readonly ?string $level,
        private readonly ?string $source,
        private readonly bool $sourceContains,
        private readonly ?string $errorCode,
        private readonly bool $errorCodeContains,
        private readonly ?int $fromUnix,
        private readonly ?int $toUnix,
    ) {
    }

    /**
     * Размер страницы.
     *
     * @return int Limit.
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Сдвиг.
     *
     * @return int Offset.
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * Фильтр уровня.
     *
     * @return string|null Уровень.
     */
    public function getLevel(): ?string
    {
        return $this->level;
    }

    /**
     * Фильтр источника.
     *
     * @return string|null Источник.
     */
    public function getSource(): ?string
    {
        return $this->source;
    }

    /**
     * Источник через LIKE.
     *
     * @return bool true, если contains.
     */
    public function isSourceContains(): bool
    {
        return $this->sourceContains;
    }

    /**
     * Фильтр кода.
     *
     * @return string|null Код.
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Код через LIKE.
     *
     * @return bool true, если contains.
     */
    public function isErrorCodeContains(): bool
    {
        return $this->errorCodeContains;
    }

    /**
     * Нижняя граница created_at.
     *
     * @return int|null Unix.
     */
    public function getFromUnix(): ?int
    {
        return $this->fromUnix;
    }

    /**
     * Верхняя граница created_at.
     *
     * @return int|null Unix.
     */
    public function getToUnix(): ?int
    {
        return $this->toUnix;
    }
}
