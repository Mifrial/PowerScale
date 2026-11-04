<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Dto;

/**
 * Проверенный запрос страницы файлов для репозитория.
 */
final class FilePageQuery
{
    /**
     * Собирает запрос.
     *
     * @param int $limit Размер 1…100.
     * @param int $offset Сдвиг.
     * @param string|null $mime Тип или null.
     * @param int|null $ownerId Владелец или null.
     * @param bool|null $isArchived Архив или null.
     * @param int|null $fromUnix Нижняя граница created_at.
     * @param int|null $toUnix Верхняя граница created_at.
     *
     * @return void
     */
    public function __construct(
        private readonly int $limit,
        private readonly int $offset,
        private readonly ?string $mime,
        private readonly ?int $ownerId,
        private readonly ?bool $isArchived,
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
     * Фильтр типа.
     *
     * @return string|null Mime.
     */
    public function getMime(): ?string
    {
        return $this->mime;
    }

    /**
     * Фильтр владельца.
     *
     * @return int|null Id учётки.
     */
    public function getOwnerId(): ?int
    {
        return $this->ownerId;
    }

    /**
     * Фильтр архива.
     *
     * @return bool|null Флаг или null.
     */
    public function getIsArchived(): ?bool
    {
        return $this->isArchived;
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
