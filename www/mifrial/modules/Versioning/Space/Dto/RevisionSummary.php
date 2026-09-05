<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Dto;

use Mifrial\Core\Kernel\Value\DateTime;

/**
 * Ревизия в ленте: номер и размер состава, без пунктов.
 */
final class RevisionSummary
{
    /**
     * Создаёт сводку.
     *
     * @param int $id Строка ревизии.
     * @param int $spaceId Пространство.
     * @param int $revision Номер.
     * @param DateTime $publishedAt Публикация.
     * @param int $itemCount Пункты состава.
     *
     * @return void
     */
    public function __construct(
        private readonly int $id,
        private readonly int $spaceId,
        private readonly int $revision,
        private readonly DateTime $publishedAt,
        private readonly int $itemCount,
    ) {
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

    /**
     * Число пунктов состава.
     *
     * @return int Count.
     */
    public function getItemCount(): int
    {
        return $this->itemCount;
    }
}
