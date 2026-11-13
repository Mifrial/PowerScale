<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Service;

use Mifrial\Core\File\Dto\FileRecord;
use Mifrial\Core\File\Exception\FileException;
use Mifrial\Core\File\Interface\Service\IFiles;
use Mifrial\Library\Asset\Exception\AssetFileInvalidException;

/**
 * Чтение файлов ассетов через публичный порт Core/File.
 */
final class AssetFileResolver
{
    /**
     * Разрешённые типы картинок.
     *
     * @var array<int, string>
     */
    private const IMAGE_MIMES = ['image/png', 'image/jpeg', 'image/webp'];

    /**
     * Создаёт резолвер.
     *
     * @param IFiles $files Порт метаданных файла.
     *
     * @return void
     */
    public function __construct(
        private readonly IFiles $files,
    ) {
    }

    /**
     * Проверяет файл нового ассета.
     *
     * @param string $publicId Внешний id файла.
     *
     * @return FileRecord Активная картинка допустимого типа.
     *
     * @throws AssetFileInvalidException Если файла нет, он архивный или не картинка.
     */
    public function requireImage(string $publicId): FileRecord
    {
        try {
            $fileRecord = $this->files->requireReadable($publicId);
        } catch (FileException $exception) {
            throw new AssetFileInvalidException('Asset file is not readable', $exception);
        }

        if ($fileRecord->isArchived() || !in_array($fileRecord->getMime(), self::IMAGE_MIMES, true)) {
            throw new AssetFileInvalidException('Asset file must be an active png, jpeg or webp image');
        }

        return $fileRecord;
    }

    /**
     * Собирает живые файлы страницы по внутренним id.
     *
     * Архивные и пропавшие файлы в карту не попадают.
     *
     * @param array<int, int> $fileIds Внутренние id файлов.
     *
     * @return array<int, FileRecord> id → запись.
     */
    public function findPageFiles(array $fileIds): array
    {
        $publicIds = $this->files->activePublicIds($fileIds);
        $records = [];
        foreach ($publicIds as $fileId => $publicId) {
            try {
                $records[$fileId] = $this->files->requireReadable($publicId);
            } catch (FileException) {
                continue;
            }
        }

        return $records;
    }
}
