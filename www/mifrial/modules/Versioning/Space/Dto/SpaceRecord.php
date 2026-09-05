<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Dto;

use Mifrial\Versioning\Space\Exception\SpaceInvalidException;

/**
 * Прочитанное пространство репозитория.
 */
final class SpaceRecord
{
    /**
     * Создаёт запись.
     *
     * @param int $id Идентификатор.
     * @param string $title Подпись.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly string $title,
    ) {
    }

    /**
     * Собирает Record из строки ST.
     *
     * @param array<string, mixed> $fields Колонки.
     *
     * @return self Пространство.
     *
     * @throws SpaceInvalidException Если строка неполная.
     */
    public static function fromNormalized(array $fields): self
    {
        if (!isset($fields['id'], $fields['title']) || !is_int($fields['id']) || !is_string($fields['title'])) {
            throw new SpaceInvalidException('Space record is incomplete');
        }

        return new self($fields['id'], $fields['title']);
    }

    /**
     * Идентификатор.
     *
     * @return int Id.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Подпись.
     *
     * @return string Title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }
}
