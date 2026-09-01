<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Dto;

/**
 * Связанный аргумент вызова handle.
 */
final class BoundArgument
{
    /**
     * Создаёт результат привязки одного параметра.
     *
     * @param bool $isValid Признак успешной привязки.
     * @param mixed $value Приведённое значение параметра.
     * @param string $errorMessage Описание ошибки привязки.
     *
     * @return void
     */
    private function __construct(
        public readonly bool $isValid,
        public readonly mixed $value,
        public readonly string $errorMessage,
    ) {
    }

    /**
     * Создаёт успешную привязку значения.
     *
     * @param mixed $value Приведённое значение.
     *
     * @return self Успешный результат.
     */
    public static function valid(mixed $value): self
    {
        return new self(true, $value, '');
    }

    /**
     * Создаёт ошибку привязки параметра.
     *
     * @param string $errorMessage Описание ошибки.
     *
     * @return self Результат с ошибкой.
     */
    public static function invalid(string $errorMessage): self
    {
        return new self(false, null, $errorMessage);
    }
}
