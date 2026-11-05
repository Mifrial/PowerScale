<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Dto;

/**
 * Строка `user_avatar`: производная, выбранный источник и круг.
 */
final class UserAvatarRecord
{
    /**
     * Собирает запись.
     *
     * @param int $fileId Производная.
     * @param int|null $sourceFileId Исходник.
     * @param int|null $cropX Левый край.
     * @param int|null $cropY Верхний край.
     * @param int|null $cropSize Сторона квадрата.
     *
     * @return void
     */
    public function __construct(
        private readonly int $fileId,
        private readonly ?int $sourceFileId,
        private readonly ?int $cropX,
        private readonly ?int $cropY,
        private readonly ?int $cropSize,
    ) {
    }

    /**
     * Гидратирует запись из строки SmartTable.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return self|null Запись или null, если строка неполная.
     */
    public static function fromRow(array $row): ?self
    {
        $fileId = self::intValue($row['file_id'] ?? null);
        if ($fileId === null || $fileId < 1) {
            return null;
        }

        return new self(
            $fileId,
            self::optionalInt($row['source_file_id'] ?? null, 1),
            self::optionalInt($row['crop_x'] ?? null, 0),
            self::optionalInt($row['crop_y'] ?? null, 0),
            self::optionalInt($row['crop_size'] ?? null, 1),
        );
    }

    /**
     * Производная.
     *
     * @return int file id.
     */
    public function getFileId(): int
    {
        return $this->fileId;
    }

    /**
     * Исходник.
     *
     * @return int|null file id или null.
     */
    public function getSourceFileId(): ?int
    {
        return $this->sourceFileId;
    }

    /**
     * Левый край кропа.
     *
     * @return int|null Пиксели исходника.
     */
    public function getCropX(): ?int
    {
        return $this->cropX;
    }

    /**
     * Верхний край кропа.
     *
     * @return int|null Пиксели исходника.
     */
    public function getCropY(): ?int
    {
        return $this->cropY;
    }

    /**
     * Сторона квадрата кропа.
     *
     * @return int|null Пиксели исходника.
     */
    public function getCropSize(): ?int
    {
        return $this->cropSize;
    }

    /**
     * Необязательное целое из строки.
     *
     * @param mixed $value Значение.
     * @param int $minimum Минимально допустимое.
     *
     * @return int|null Целое или null.
     */
    private static function optionalInt(mixed $value, int $minimum): ?int
    {
        $intValue = self::intValue($value);

        return $intValue !== null && $intValue >= $minimum ? $intValue : null;
    }

    /**
     * Целое из int или числовой строки драйвера.
     *
     * @param mixed $value Значение.
     *
     * @return int|null Целое или null.
     */
    private static function intValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }
}
