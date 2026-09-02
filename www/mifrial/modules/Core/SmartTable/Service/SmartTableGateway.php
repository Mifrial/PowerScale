<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Closure;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Transaction\TransactionFailedException;
use Mifrial\Core\SmartTable\Exception\Transaction\TransactionOpenException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedTable;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Cache\TableCache;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Service\Query\TableList;
use Mifrial\Core\SmartTable\Service\Query\TableRows;
use Mifrial\Core\SmartTable\Service\Schema\TableSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use ReflectionClass;
use ReflectionException;
use Throwable;

/**
 * Открытие таблиц по PHP-классу и транзакция соединения.
 */
final class SmartTableGateway implements ISmartTableGateway
{
    /**
     * Создаёт шлюз.
     *
     * @param IlluminateDatabaseConnection $databaseConnection Адаптер модуля.
     * @param TableSchema $tableSchema DDL.
     * @param TableRows $tableRows Строки.
     * @param TableList $tableList Список.
     * @param TableCache $tableCache Кэш get/getList.
     *
     * @return void
     */
    public function __construct(
        private readonly IlluminateDatabaseConnection $databaseConnection,
        private readonly TableSchema $tableSchema,
        private readonly TableRows $tableRows,
        private readonly TableList $tableList,
        private readonly TableCache $tableCache,
    ) {
    }

    /**
     * Открывает handle по классу определения.
     *
     * @param string $definitionClass Наследник SmartTableDefinition.
     *
     * @return IOpenedTable Handle таблицы.
     *
     * @throws MapInvalidException Если класс нельзя инстанцировать как definition.
     */
    public function open(string $definitionClass): IOpenedTable
    {
        $tableDefinition = $this->instantiateDefinition($definitionClass);

        return new OpenedTable(
            $tableDefinition,
            $this->tableSchema,
            $this->tableRows,
            $this->tableList,
            $this->tableCache,
        );
    }

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
    public function transaction(Closure $work): mixed
    {
        $mySqlConnection = $this->databaseConnection->illuminateConnection();
        if ($mySqlConnection->transactionLevel() > 0) {
            throw new TransactionOpenException();
        }

        try {
            $mySqlConnection->beginTransaction();
        } catch (Throwable $throwable) {
            throw new TransactionFailedException($throwable);
        }

        return $this->runTransaction($work);
    }

    /**
     * Создаёт definition после проверок Reflection.
     *
     * @param string $definitionClass Имя класса.
     *
     * @return SmartTableDefinition Определение.
     *
     * @throws MapInvalidException Если класс непригоден.
     */
    private function instantiateDefinition(string $definitionClass): SmartTableDefinition
    {
        if (!class_exists($definitionClass) || !is_subclass_of($definitionClass, SmartTableDefinition::class)) {
            throw new MapInvalidException('Definition class is invalid');
        }

        try {
            $reflectionClass = new ReflectionClass($definitionClass);
        } catch (ReflectionException) {
            throw new MapInvalidException('Definition class is invalid');
        }

        if ($reflectionClass->isAbstract() || !$this->hasOptionalConstructor($reflectionClass)) {
            throw new MapInvalidException('Definition class is invalid');
        }

        $tableDefinition = $reflectionClass->newInstance();
        if (!$tableDefinition instanceof SmartTableDefinition) {
            throw new MapInvalidException('Definition class is invalid');
        }

        return $tableDefinition;
    }

    /**
     * Проверяет, что конструктор без обязательных параметров.
     *
     * @param ReflectionClass $reflectionClass Рефлексия definition.
     *
     * @return bool true, если new без аргументов допустим.
     */
    private function hasOptionalConstructor(ReflectionClass $reflectionClass): bool
    {
        $constructor = $reflectionClass->getConstructor();
        if ($constructor === null) {
            return true;
        }

        foreach ($constructor->getParameters() as $parameter) {
            if (!$parameter->isOptional()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Commit или rollback после begin.
     *
     * @param Closure $work Работа.
     *
     * @return mixed Результат $work.
     *
     * @throws TransactionFailedException Если commit или rollback не удались.
     */
    private function runTransaction(Closure $work): mixed
    {
        try {
            $result = $work();
        } catch (Throwable $workException) {
            $this->rollBackOrFail();
            $this->tableCache->settleTransaction(false);

            throw $workException;
        }

        try {
            $this->databaseConnection->illuminateConnection()->commit();
        } catch (Throwable $commitException) {
            $this->rollBackOrFail();
            $this->tableCache->settleTransaction(false);

            throw new TransactionFailedException($commitException);
        }

        $this->tableCache->settleTransaction(true);

        return $result;
    }

    /**
     * Откатывает транзакцию или бросает TRANSACTION_FAILED.
     *
     * @return void
     *
     * @throws TransactionFailedException Если rollback не удался.
     */
    private function rollBackOrFail(): void
    {
        try {
            $this->databaseConnection->illuminateConnection()->rollBack();
        } catch (Throwable $rollbackException) {
            throw new TransactionFailedException($rollbackException);
        }
    }
}
