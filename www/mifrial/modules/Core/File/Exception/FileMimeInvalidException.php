<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Exception;

use Throwable;

/**
 * Серверный тип файла вне политики хранилища.
 */
final class FileMimeInvalidException extends FileException
{
    /**
     * Создаёт ошибку типа.
     *
     * @param array<int, string> $allowed Разрешённые типы.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        private readonly array $allowed,
        ?Throwable $previous = null,
    ) {
        parent::__construct('FILE_MIME_INVALID', 'File type is not allowed', $previous);
    }

    /**
     * Разрешённые типы.
     *
     * @return array<string, mixed> Детали конверта.
     */
    public function getErrorDetails(): array
    {
        return ['allowedMime' => $this->allowed];
    }
}
