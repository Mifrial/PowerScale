<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Service;

use Mifrial\Core\File\Dto\FileRecord;

/**
 * JSON-вид записи файла. `storagePath` наружу не отдаётся.
 */
final class FileViewAssembler
{
    /**
     * Собирает JSON-вид.
     *
     * @param FileRecord $fileRecord Запись.
     *
     * @return array<string, mixed> FileEntry.
     */
    public function assemble(FileRecord $fileRecord): array
    {
        return [
            'id' => $fileRecord->getId(),
            'publicId' => $fileRecord->getPublicId(),
            'ownerId' => $fileRecord->getOwnerId(),
            'originalName' => $fileRecord->getOriginalName(),
            'mime' => $fileRecord->getMime(),
            'size' => $fileRecord->getSize(),
            'width' => $fileRecord->getWidth(),
            'height' => $fileRecord->getHeight(),
            'sha256' => $fileRecord->getSha256(),
            'isArchived' => $fileRecord->isArchived(),
            'archivedAt' => $fileRecord->getArchivedAt()?->toUnix(),
            'createdAt' => $fileRecord->getCreatedAt()->toUnix(),
        ];
    }
}
