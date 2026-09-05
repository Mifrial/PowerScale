<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Dto;

/**
 * Результат TX commit: ревизия и собранный срез.
 */
final class WrittenRevision
{
    /**
     * Создаёт результат записи.
     *
     * @param RevisionRecord $revisionRecord Ревизия.
     * @param RevisionSlice $revisionSlice Срез.
     *
     * @return void
     */
    public function __construct(
        private readonly RevisionRecord $revisionRecord,
        private readonly RevisionSlice $revisionSlice,
    ) {
    }

    /**
     * Ревизия.
     *
     * @return RevisionRecord Record.
     */
    public function getRevisionRecord(): RevisionRecord
    {
        return $this->revisionRecord;
    }

    /**
     * Срез для кэша.
     *
     * @return RevisionSlice Срез.
     */
    public function getRevisionSlice(): RevisionSlice
    {
        return $this->revisionSlice;
    }
}
