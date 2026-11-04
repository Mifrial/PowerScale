<?php

declare(strict_types=1);

namespace Mifrial\Core\Image\Dto;

/**
 * Готовая производная во временном файле; удаляет вызывающий.
 */
final class ImageResult
{
    /**
     * Собирает результат.
     *
     * @param string $path Временный файл.
     * @param string $mime Тип результата.
     * @param int $width Ширина.
     * @param int $height Высота.
     * @param int $size Байт.
     *
     * @return void
     */
    public function __construct(
        private readonly string $path,
        private readonly string $mime,
        private readonly int $width,
        private readonly int $height,
        private readonly int $size,
    ) {
    }

    /**
     * Временный файл результата.
     *
     * @return string Абсолютный путь.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Тип результата.
     *
     * @return string type/subtype.
     */
    public function getMime(): string
    {
        return $this->mime;
    }

    /**
     * Ширина результата.
     *
     * @return int Пиксели.
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Высота результата.
     *
     * @return int Пиксели.
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Размер результата.
     *
     * @return int Байты.
     */
    public function getSize(): int
    {
        return $this->size;
    }
}
