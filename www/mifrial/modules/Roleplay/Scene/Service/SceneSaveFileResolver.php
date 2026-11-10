<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Service;

use Mifrial\Core\File\Dto\FileRecord;
use Mifrial\Core\File\Exception\FileException;
use Mifrial\Core\File\Interface\Service\IFiles;
use Mifrial\Roleplay\Scene\Exception\SceneInvalidException;

/**
 * Разрешает public_id подложки или декора в строку файла.
 */
final class SceneSaveFileResolver
{
    private const ALLOWED_MIME = ['image/png', 'image/jpeg', 'image/webp'];

    /**
     * Создаёт resolver.
     *
     * @param IFiles $files Файлы.
     *
     * @return void
     */
    public function __construct(
        private readonly IFiles $files,
    ) {
    }

    /**
     * Файл по public_id или null.
     *
     * @param string|null $publicId Внешний id.
     * @param string $path Путь поля для ошибки.
     *
     * @return FileRecord|null Файл или null.
     *
     * @throws SceneInvalidException Если файл недоступен или не картинка.
     */
    public function resolve(?string $publicId, string $path): ?FileRecord
    {
        if ($publicId === null || $publicId === '') {
            return null;
        }

        try {
            $fileRecord = $this->files->requireOwned($publicId);
        } catch (FileException $exception) {
            throw new SceneInvalidException($path . ' is not available', $exception);
        }

        if ($fileRecord->isArchived()) {
            throw new SceneInvalidException($path . ' is archived');
        }

        if (!in_array($fileRecord->getMime(), self::ALLOWED_MIME, true)) {
            throw new SceneInvalidException($path . ' must be png, jpeg or webp');
        }

        if ($fileRecord->getWidth() === null || $fileRecord->getHeight() === null) {
            throw new SceneInvalidException($path . ' has no dimensions');
        }

        return $fileRecord;
    }
}
