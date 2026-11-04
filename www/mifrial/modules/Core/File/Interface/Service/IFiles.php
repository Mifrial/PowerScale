<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Interface\Service;

use Mifrial\Core\File\Dto\FileRecord;

/**
 * Публичный порт метаданных файла для соседей.
 */
interface IFiles
{
    /**
     * Строка по public_id для текущего актора.
     *
     * @param string $publicId Внешний id.
     *
     * @return FileRecord Запись; архивная тоже читается.
     */
    public function requireReadable(string $publicId): FileRecord;

    /**
     * Строка, если владелец — текущий актор.
     *
     * @param string $publicId Внешний id.
     *
     * @return FileRecord Своя запись.
     */
    public function requireOwned(string $publicId): FileRecord;

    /**
     * Архив владельцем.
     *
     * @param string $publicId Внешний id.
     *
     * @return void
     */
    public function archive(string $publicId): void;
}
