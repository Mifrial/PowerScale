<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Dto;

/**
 * Состав commit до записи ревизии: id версий и их ряды.
 */
final class PreparedComposition
{
    /**
     * Создаёт подготовленный состав.
     *
     * @param array<int, int> $versionIds Порядок состава.
     * @param array<int, array<string, mixed>> $versionRows Ряды по id версии.
     *
     * @return void
     */
    public function __construct(
        private readonly array $versionIds,
        private readonly array $versionRows,
    ) {
    }

    /**
     * Id версий в порядке commit.
     *
     * @return array<int, int> Список.
     */
    public function getVersionIds(): array
    {
        return $this->versionIds;
    }

    /**
     * Ряд экземпляра.
     *
     * @param int $versionId Id версии.
     *
     * @return array<string, mixed>|null Колонки или null.
     */
    public function findVersionRow(int $versionId): ?array
    {
        return $this->versionRows[$versionId] ?? null;
    }
}
