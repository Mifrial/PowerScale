<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Closure;
use Mifrial\Core\Kernel\Interface\Service\ITransactionRunner;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;

/**
 * Единица работы на соединении шлюза SmartTable.
 */
final class SmartTableTransactionRunner implements ITransactionRunner
{
    /**
     * Создаёт runner.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз соединения.
     *
     * @return void
     */
    public function __construct(
        private readonly ISmartTableGateway $smartTableGateway,
    ) {
    }

    /**
     * Выполняет работу в transaction() шлюза.
     *
     * @param Closure $work Работа.
     *
     * @return mixed Результат $work.
     */
    public function run(Closure $work): mixed
    {
        return $this->smartTableGateway->transaction($work);
    }
}
