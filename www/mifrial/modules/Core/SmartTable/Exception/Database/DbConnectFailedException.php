<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Exception\Database;

use Throwable;

/**
 * Сервер MySQL отклонил или не принял соединение.
 */
final class DbConnectFailedException extends DatabaseException
{
    /**
     * Создаёт ошибку соединения.
     *
     * @param Throwable|null $previous Исходный throwable драйвера.
     *
     * @return void
     */
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('DB_CONNECT_FAILED', 'Database connection failed', $previous);
    }
}
