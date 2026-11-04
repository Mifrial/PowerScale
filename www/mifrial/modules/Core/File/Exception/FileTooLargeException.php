<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Exception;

use Throwable;

/**
 * Файл больше лимита хранилища.
 */
final class FileTooLargeException extends FileException
{
    /**
     * Создаёт ошибку размера.
     *
     * @param int $maxSize Лимит байт.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        private readonly int $maxSize,
        ?Throwable $previous = null,
    ) {
        parent::__construct('FILE_TOO_LARGE', 'File is larger than the configured limit', $previous);
    }

    /**
     * Лимит байт.
     *
     * @return array<string, mixed> Детали конверта.
     */
    public function getErrorDetails(): array
    {
        return ['maxSize' => $this->maxSize];
    }
}
