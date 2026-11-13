<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Service;

use Mifrial\Core\File\Dto\FileRecord;
use Mifrial\Library\Asset\Dto\AssetCollectionRecord;
use Mifrial\Library\Asset\Dto\AssetRecord;

/**
 * JSON-вид ассета. Внутренние id и путь файла наружу не отдаются.
 */
final class AssetViewAssembler
{
    /**
     * Собирает JSON-вид записи.
     *
     * @param AssetRecord $assetRecord Ассет.
     * @param AssetCollectionRecord $collectionRecord Назначение.
     * @param FileRecord|null $fileRecord Файл; null, если строка файла пропала.
     *
     * @return array<string, mixed> JSON-вид.
     */
    public function assemble(
        AssetRecord $assetRecord,
        AssetCollectionRecord $collectionRecord,
        ?FileRecord $fileRecord,
    ): array {
        return [
            'id' => $assetRecord->getId(),
            'collectionModule' => $collectionRecord->getModule(),
            'collectionCode' => $collectionRecord->getCode(),
            'collectionTitle' => $collectionRecord->getTitle(),
            'filePublicId' => $fileRecord?->getPublicId(),
            'name' => $assetRecord->getName(),
            'width' => $fileRecord?->getWidth(),
            'height' => $fileRecord?->getHeight(),
            'mime' => $fileRecord?->getMime(),
            'size' => $fileRecord?->getSize(),
            'isShared' => $assetRecord->isShared(),
            'ownerId' => $assetRecord->getOwnerId(),
            'isArchived' => $assetRecord->isArchived(),
            'createdAt' => $assetRecord->getCreatedAt()->toUnix(),
        ];
    }
}
