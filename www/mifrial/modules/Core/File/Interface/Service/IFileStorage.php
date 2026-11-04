<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Interface\Service;

/**
 * Хранение байтов: диск за интерфейсом, чтобы сервисы тестировались без ФС.
 */
interface IFileStorage
{
    /**
     * Считает SHA-256 файла-источника.
     *
     * @param string $sourcePath Абсолютный путь (tmp PHP).
     *
     * @return string Hex.
     */
    public function hash(string $sourcePath): string;

    /**
     * Определяет серверный mime.
     *
     * @param string $sourcePath Абсолютный путь.
     *
     * @return string type/subtype.
     */
    public function mimeType(string $sourcePath): string;

    /**
     * Размеры картинки.
     *
     * @param string $sourcePath Абсолютный путь.
     *
     * @return array{0: int, 1: int}|null Ширина и высота или null.
     */
    public function imageSize(string $sourcePath): ?array;

    /**
     * Относительный путь шарда по public_id и sha256.
     *
     * @param string $publicId Внешний id.
     * @param string $sha256 Hex.
     *
     * @return string Путь относительно корня.
     */
    public function storagePath(string $publicId, string $sha256): string;

    /**
     * Переносит принятый файл в шард.
     *
     * @param string $sourcePath Абсолютный tmp.
     * @param string $storagePath Относительный путь.
     *
     * @return void
     */
    public function store(string $sourcePath, string $storagePath): void;

    /**
     * Удаляет файл шарда, если он есть.
     *
     * @param string $storagePath Относительный путь.
     *
     * @return void
     */
    public function delete(string $storagePath): void;

    /**
     * Отдаёт байты файла порциями.
     *
     * @param string $storagePath Относительный путь.
     * @param int $chunkSize Размер порции.
     *
     * @return iterable<string> Порции байт.
     */
    public function chunks(string $storagePath, int $chunkSize): iterable;
}
