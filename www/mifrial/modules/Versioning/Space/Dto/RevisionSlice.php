<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Dto;

/**
 * Опубликованный срез: ревизия и экземпляры в порядке состава.
 */
final class RevisionSlice
{
    /**
     * Создаёт срез.
     *
     * @param RevisionRecord $revisionRecord Ревизия.
     * @param array<int, VersionRecord> $items Экземпляры.
     *
     * @return void
     */
    public function __construct(
        private readonly RevisionRecord $revisionRecord,
        private readonly array $items,
    ) {
    }

    /**
     * Ревизия среза.
     *
     * @return RevisionRecord Ревизия.
     */
    public function getRevision(): RevisionRecord
    {
        return $this->revisionRecord;
    }

    /**
     * Экземпляры в порядке состава.
     *
     * @return array<int, VersionRecord> Пункты.
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
