<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Versioning\Space\Dto\RevisionRecord;

/**
 * Опубликованная ревизия пространства правил.
 */
final class RuleRevisionRecord
{
    /**
     * Создаёт запись.
     *
     * @param int $id Строка ревизии.
     * @param int $spaceId Пространство.
     * @param int $revision Номер.
     * @param DateTime $publishedAt Публикация.
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
     * Из ревизии часов.
     *
     * @param RevisionRecord $revisionRecord Часы.
     *
     * @return self Ревизия.
     */
    public static function fromClock(RevisionRecord $revisionRecord): self
    {
        return new self(
            $revisionRecord->getId(),
            $revisionRecord->getSpaceId(),
            $revisionRecord->getRevision(),
            $revisionRecord->getPublishedAt(),
        );
    }

    /**
     * Id строки.
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
     * Номер ревизии.
     *
     * @return int Номер.
     */
    public function getRevision(): int
    {
        return $this->revision;
    }

    /**
     * Публикация.
     *
     * @return DateTime UTC.
     */
    public function getPublishedAt(): DateTime
    {
        return $this->publishedAt;
    }
}
