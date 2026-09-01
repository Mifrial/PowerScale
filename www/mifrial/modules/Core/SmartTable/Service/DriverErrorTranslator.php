<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Mifrial\Core\SmartTable\Exception\Row\ReferenceConstraintException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Exception\Schema\SchemaMismatchException;
use Mifrial\Core\SmartTable\Exception\Schema\TableMissingException;
use Mifrial\Core\SmartTable\Exception\SmartTableException;
use PDOException;
use Throwable;

/**
 * Перевод ошибок PDO/Query в листья SmartTable.
 */
final class DriverErrorTranslator
{
    /**
     * Выполняет работу с билдером и переводит SQLSTATE.
     *
     * @param callable(): mixed $databaseWork Работа с драйвером.
     *
     * @return mixed Результат работы.
     *
     * @throws TableMissingException Если SQLSTATE 42S02.
     * @throws SchemaMismatchException Если SQLSTATE 42S22.
     * @throws ReferenceConstraintException Если MySQL 1451 или 1452.
     * @throws UniqueConstraintException Если MySQL 1062.
     * @throws RowWriteFailedException Остальные ошибки драйвера.
     */
    public function run(callable $databaseWork): mixed
    {
        try {
            return $databaseWork();
        } catch (SmartTableException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            $this->throwFromDriver($throwable);
        }
    }

    /**
     * Переводит throwable драйвера в лист.
     *
     * @param Throwable $throwable Ошибка драйвера.
     *
     * @return never
     *
     * @throws TableMissingException Если SQLSTATE 42S02.
     * @throws SchemaMismatchException Если SQLSTATE 42S22.
     * @throws ReferenceConstraintException Если MySQL 1451 или 1452.
     * @throws UniqueConstraintException Если MySQL 1062.
     * @throws RowWriteFailedException Остальные ошибки драйвера.
     */
    private function throwFromDriver(Throwable $throwable): never
    {
        $sqlState = $this->sqlState($throwable);
        if ($sqlState === '42S02') {
            throw new TableMissingException();
        }

        if ($sqlState === '42S22') {
            throw new SchemaMismatchException();
        }

        $driverCode = $this->driverErrorCode($throwable);
        if ($driverCode === 1451 || $driverCode === 1452) {
            throw new ReferenceConstraintException($throwable);
        }

        if ($driverCode === 1062) {
            throw new UniqueConstraintException($throwable);
        }

        throw new RowWriteFailedException($throwable);
    }

    /**
     * Читает код драйвера MySQL из errorInfo.
     *
     * @param Throwable $throwable Ошибка драйвера.
     *
     * @return int Код или 0.
     */
    private function driverErrorCode(Throwable $throwable): int
    {
        $current = $throwable;
        while ($current instanceof Throwable) {
            $driverCode = $this->pdoDriverCode($current);
            if ($driverCode !== 0) {
                return $driverCode;
            }

            $current = $current->getPrevious();
        }

        return 0;
    }

    /**
     * Читает код драйвера из PDO errorInfo.
     *
     * @param Throwable $throwable Звено цепочки.
     *
     * @return int Код или 0.
     */
    private function pdoDriverCode(Throwable $throwable): int
    {
        if (!$throwable instanceof PDOException || !isset($throwable->errorInfo[1])) {
            return 0;
        }

        $driverCode = $throwable->errorInfo[1];
        if (is_int($driverCode)) {
            return $driverCode;
        }

        return is_string($driverCode) && ctype_digit($driverCode) ? (int) $driverCode : 0;
    }

    /**
     * Читает SQLSTATE по цепочке previous.
     *
     * @param Throwable $throwable Ошибка драйвера.
     *
     * @return string SQLSTATE или пусто.
     */
    private function sqlState(Throwable $throwable): string
    {
        $current = $throwable;
        while ($current instanceof Throwable) {
            $sqlState = $this->sqlStateFromErrorInfo($current);
            if ($sqlState !== '') {
                return $sqlState;
            }

            $current = $current->getPrevious();
        }

        return '';
    }

    /**
     * Достаёт SQLSTATE только из PDO errorInfo.
     *
     * @param Throwable $throwable Звено цепочки.
     *
     * @return string Пять символов или пусто.
     */
    private function sqlStateFromErrorInfo(Throwable $throwable): string
    {
        if (
            !$throwable instanceof PDOException
            || !isset($throwable->errorInfo[0])
            || !is_string($throwable->errorInfo[0])
        ) {
            return '';
        }

        $sqlState = $throwable->errorInfo[0];
        if (preg_match('/^[0-9A-Z]{5}$/', $sqlState) !== 1) {
            return '';
        }

        return $sqlState;
    }
}
