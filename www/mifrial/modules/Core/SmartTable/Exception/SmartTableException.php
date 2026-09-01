<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Exception;

use Mifrial\Core\Kernel\Exception\MifrialException;
use Throwable;

/**
 * База ошибок SmartTable: код плюс ветка наследников.
 */
abstract class SmartTableException extends MifrialException
{
    /**
     * Создаёт ошибку SmartTable.
     *
     * @param string $errorCode Код ошибки.
     * @param string $message Текст без секретов соединения.
     * @param Throwable|null $previous Предыдущее исключение.
     *
     * @return void
     */
    public function __construct(
        private readonly string $errorCode,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Возвращает код ошибки SmartTable.
     *
     * @return string Машиночитаемый код.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
