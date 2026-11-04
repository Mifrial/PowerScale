<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Exception;

use Mifrial\Core\Kernel\Exception\MifrialException;
use Throwable;

/**
 * Сбой диска: не HTTP-отказ, журналируется как INTERNAL.
 */
final class FileStorageException extends MifrialException
{
    /**
     * Создаёт ошибку диска.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'File storage operation failed',
        ?Throwable $previous = null,
    ) {
        parent::__construct('FILE_STORAGE', $message, $previous);
    }
}
