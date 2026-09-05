<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Versioning\Space\Exception\SpaceInvalidException;

/**
 * Опубликованная ревизия пространства.
 */
final class RevisionRecord
{
    /**
     * Создаёт запись.
     *
     * @param int $id Строка ревизии.
     * @param int $spaceId Пространство.
     * @param int $revision Номер.
     * @param DateTime $publishedAt Момент публикации.
     *
     * @return void
     */
    public function __construct(
        private readonly int $id,
        private readonly int $spaceId,
        private readonly int $revision,
        private readonly DateTime $publishedAt,
    ) {
    }

    /**
     * Собирает Record из строки ST.
     *
     * @param array<string, mixed> $fields Колонки.
     *
     * @return self Ревизия.
     *
     * @throws SpaceInvalidException Если строка неполная.
     */
    public static function fromNormalized(array $fields): self
    {
        return new self(
            RecordValue::requireInt($fields, 'id'),
            RecordValue::requireInt($fields, 'space_id'),
            RecordValue::requireInt($fields, 'revision'),
            RecordValue::requireDateTime($fields, 'published_at'),
        );
    }

    /**
     * Id строки ревизии.
     *
     * @return int Id.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Пространство.
     *
     * @return int Space id.
     */
    public function getSpaceId(): int
    {
        return $this->spaceId;
    }

    /**
     * Номер внутри пространства.
     *
     * @return int Номер ≥ 1.
     */
    public function getRevision(): int
    {
        return $this->revision;
    }

    /**
     * Момент публикации.
     *
     * @return DateTime UTC.
     */
    public function getPublishedAt(): DateTime
    {
        return $this->publishedAt;
    }
}
