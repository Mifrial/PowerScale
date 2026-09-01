<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Interface\Service;

use Closure;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Transaction\TransactionFailedException;
use Mifrial\Core\SmartTable\Exception\Transaction\TransactionOpenException;

/**
 * Порт открытия таблиц по PHP-классу и транзакции соединения.
 */
interface ISmartTableGateway
{
    /**
     * Открывает handle по классу определения.
     *
     * @param string $definitionClass Наследник SmartTableDefinition.
     *
     * @return IOpenedTable Handle таблицы.
     *
     * @throws MapInvalidException Если класс нельзя инстанцировать как definition.
     */
    public function open(string $definitionClass): IOpenedTable;

    /**
     * Выполняет работу в одной транзакции соединения.
     *
     * @param Closure $work Работа; возвращаемое значение отдаётся наружу.
     *
     * @return mixed Результат $work.
     *
     * @throws TransactionOpenException Если транзакция уже открыта.
     * @throws TransactionFailedException Если commit или rollback не удались.
     */
    public function transaction(Closure $work): mixed;
}
