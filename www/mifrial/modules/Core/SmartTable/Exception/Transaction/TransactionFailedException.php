<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Exception\Transaction;

use Mifrial\Core\SmartTable\Exception\SmartTableException;
use Throwable;

/**
 * Commit или rollback не удались.
 */
final class TransactionFailedException extends SmartTableException
{
    /**
     * Создаёт ошибку транзакции.
     *
     * @param Throwable|null $previous Исходный throwable драйвера.
     *
     * @return void
     */
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('TRANSACTION_FAILED', 'Transaction commit or rollback failed', $previous);
    }
}
