<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Versioning\Space\Dto\RevisionSummary;

/**
 * Ревизия правил в ленте: номер и ruleCount, без пунктов.
 */
final class RuleRevisionSummary
{
    /**
     * Создаёт сводку.
     *
     * @param int $id Строка ревизии.
     * @param int $spaceId Пространство.
     * @param int $revision Номер.
     * @param DateTime $publishedAt Публикация.
     * @param int $ruleCount Пункты состава.
     *
     * @return void
     */
    public function __construct(
        private readonly int $id,
        private readonly int $spaceId,
        private readonly int $revision,
        private readonly DateTime $publishedAt,
        private readonly int $ruleCount,
    ) {
    }

    /**
     * Из сводки часов.
     *
     * @param RevisionSummary $revisionSummary Часы.
     *
     * @return self Лента.
     */
    public static function fromClock(RevisionSummary $revisionSummary): self
    {
        return new self(
            $revisionSummary->getId(),
            $revisionSummary->getSpaceId(),
            $revisionSummary->getRevision(),
            $revisionSummary->getPublishedAt(),
            $revisionSummary->getItemCount(),
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

    /**
     * Число пунктов состава, включая tombstone.
     *
     * @return int Count.
     */
    public function getRuleCount(): int
    {
        return $this->ruleCount;
    }
}
