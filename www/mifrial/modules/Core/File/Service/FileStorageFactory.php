<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Service;

use Mifrial\Core\File\Dto\FileSettings;
use Mifrial\Core\File\Interface\Service\IFileStorage;

/**
 * Собирает локальное хранилище из настроек `files`.
 */
final class FileStorageFactory
{
    /**
     * Создаёт хранилище.
     *
     * @param FileSettings $fileSettings Настройки.
     *
     * @return IFileStorage Хранилище.
     */
    public function create(FileSettings $fileSettings): IFileStorage
    {
        return new LocalFileStorage($fileSettings->path());
    }
}
