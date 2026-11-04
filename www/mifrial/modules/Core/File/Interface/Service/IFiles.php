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

    /**
     * Копирует байты своей активной строки во внешний файл.
     *
     * Нужен потребителю, который готовит производную: он получает локальный
     * файл и не знает раскладку шардов.
     *
     * @param string $publicId Внешний id.
     * @param string $targetPath Абсолютный путь назначения.
     *
     * @return FileRecord Своя активная запись.
     */
    public function copyOwnedTo(string $publicId, string $targetPath): FileRecord;

    /**
     * Кладёт готовые байты новой строкой от имени текущего актора.
     *
     * Исходный файл переносится в шард: после вызова его не существует.
     * Владелец производной — текущий актор, исходная строка должна быть его.
     *
     * @param string $sourcePublicId Внешний id исходника.
     * @param string $sourcePath Абсолютный путь к готовым байтам.
     * @param string $originalName Имя строки; пусто — имя исходника.
     *
     * @return FileRecord Новая строка.
     */
    public function storeDerived(
        string $sourcePublicId,
        string $sourcePath,
        string $originalName,
    ): FileRecord;

    /**
     * Проекция id → public_id для живых строк.
     *
     * Прав не проверяет и актора не требует: id должны приходить из уже
     * авторизованных строк. Только для проекций, не для доступа к байтам.
     *
     * @param array<int, int> $fileIds Внутренние id.
     *
     * @return array<int, string> id → public_id.
     */
    public function activePublicIds(array $fileIds): array;
}
