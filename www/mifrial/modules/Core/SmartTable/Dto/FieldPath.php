<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Dto;

use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;

/**
 * Путь filter/sort/select через точки.
 */
final class FieldPath
{
    /**
     * Шаблон имени поля или пути.
     */
    public const NAME_PATTERN = '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$/';

    /**
     * Создаёт путь из сегментов.
     *
     * @param array<int, string> $segments Имена полей.
     *
     * @return void
     */
    private function __construct(
        private readonly array $segments,
    ) {
    }

    /**
     * Разбирает ключ с точками.
     *
     * @param string $pathKey Ключ запроса.
     *
     * @return self Путь.
     *
     * @throws MapInvalidException Если форма неверна.
     */
    public static function parse(string $pathKey): self
    {
        if (
            $pathKey === ''
            || str_starts_with($pathKey, '.')
            || str_ends_with($pathKey, '.')
            || str_contains($pathKey, '..')
        ) {
            throw new MapInvalidException('Field path is invalid');
        }

        $segments = explode('.', $pathKey);
        if (count($segments) < 2) {
            throw new MapInvalidException('Field path is invalid');
        }

        foreach ($segments as $segmentName) {
            if (preg_match('/^[a-z][a-z0-9_]*$/', $segmentName) !== 1) {
                throw new MapInvalidException('Field path is invalid');
            }
        }

        return new self($segments);
    }

    /**
     * Разбирает ключ, если в нём есть точка.
     *
     * @param string $fieldName Ключ запроса.
     *
     * @return self|null Путь или обычное поле.
     *
     * @throws MapInvalidException Если точка есть, но форма неверна.
     */
    public static function tryParse(string $fieldName): ?self
    {
        if (!str_contains($fieldName, '.')) {
            return null;
        }

        return self::parse($fieldName);
    }

    /**
     * Возвращает сегменты.
     *
     * @return array<int, string> Имена.
     */
    public function segments(): array
    {
        return $this->segments;
    }

    /**
     * Собирает ключ с точками.
     *
     * @return string Ключ.
     */
    public function key(): string
    {
        return implode('.', $this->segments);
    }
}
