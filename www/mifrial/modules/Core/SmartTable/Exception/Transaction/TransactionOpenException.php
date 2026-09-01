<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Exception\Transaction;

use Mifrial\Core\SmartTable\Exception\SmartTableException;

/**
 * Вложенная транзакция запрещена.
 */
final class TransactionOpenException extends SmartTableException
{
    /**
     * Создаёт ошибку вложенной транзакции.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct('TRANSACTION_OPEN', 'A transaction is already open');
    }
}
