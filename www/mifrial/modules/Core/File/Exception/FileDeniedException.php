<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Exception;

use Throwable;

/**
 * Файл чужой или недоступен актору.
 */
final class FileDeniedException extends FileException
{
    /**
     * Создаёт ошибку доступа.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'File access is denied',
        ?Throwable $previous = null,
    ) {
        parent::__construct('FILE_DENIED', $message, $previous);
    }
}
