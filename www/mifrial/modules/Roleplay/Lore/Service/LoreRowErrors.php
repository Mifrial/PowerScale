<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Service;

use Closure;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\ListResult;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException as SmartMapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\ReferenceConstraintException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Roleplay\Lore\Exception\LoreInvalidException;
use Mifrial\Roleplay\Lore\Exception\LoreNotFoundException;

/**
 * Единая карта ошибок SmartTable в ошибки кодекса.
 */
final class LoreRowErrors
{
    /**
     * Вставляет строку.
     *
     * @param IOpenedRecords $records Строки.
     * @param array<string, mixed> $fields Поля.
     * @param string $message Текст ошибки значений.
     *
     * @return int Id.
     *
     * @throws LoreInvalidException Если строка недопустима.
     */
    public function insert(IOpenedRecords $records, array $fields, string $message): int
    {
        try {
            return $records->add($fields);
        } catch (
            FieldInvalidException
            | SmartMapInvalidException
            | RowWriteFailedException
            | ReferenceConstraintException
            | UniqueConstraintException $exception
        ) {
            throw new LoreInvalidException($message, $exception);
        }
    }

    /**
     * Вставляет пачку строк.
     *
     * @param IOpenedRecords $records Строки.
     * @param array<int, array<string, mixed>> $rows Пачка.
     * @param string $message Текст ошибки значений.
     *
     * @return void
     *
     * @throws LoreInvalidException Если строки недопустимы.
     */
    public function insertMany(IOpenedRecords $records, array $rows, string $message): void
    {
        if ($rows === []) {
            return;
        }

        try {
            $records->addMany($rows);
        } catch (
            FieldInvalidException
            | SmartMapInvalidException
            | RowWriteFailedException
            | ReferenceConstraintException
            | UniqueConstraintException $exception
        ) {
            throw new LoreInvalidException($message, $exception);
        }
    }

    /**
     * Пишет строку по id.
     *
     * @param IOpenedRecords $records Строки.
     * @param int $rowId Id.
     * @param array<string, mixed> $fields Поля.
     * @param string $message Текст ошибки значений.
     *
     * @return void
     *
     * @throws LoreNotFoundException Если строки нет.
     * @throws LoreInvalidException Если поле недопустимо.
     */
    public function updateById(IOpenedRecords $records, int $rowId, array $fields, string $message): void
    {
        try {
            $records->update($rowId, $fields);
        } catch (RowNotFoundException $exception) {
            throw new LoreNotFoundException('Lore row was not found', $exception);
        } catch (FieldInvalidException | SmartMapInvalidException | UniqueConstraintException $exception) {
            throw new LoreInvalidException($message, $exception);
        }
    }

    /**
     * Удаляет строку по id.
     *
     * @param IOpenedRecords $records Строки.
     * @param int $rowId Id.
     * @param string $message Текст ошибки ссылок.
     *
     * @return void
     *
     * @throws LoreNotFoundException Если строки нет.
     * @throws LoreInvalidException Если удаление запрещено.
     */
    public function deleteById(IOpenedRecords $records, int $rowId, string $message): void
    {
        try {
            $records->delete($rowId);
        } catch (RowNotFoundException $exception) {
            throw new LoreNotFoundException('Lore row was not found', $exception);
        } catch (ReferenceConstraintException $exception) {
            throw new LoreInvalidException($message, $exception);
        }
    }

    /**
     * Читает страницу строк.
     *
     * @param IOpenedRecords $records Строки.
     * @param ListQuery $query Запрос.
     * @param string $message Текст ошибки выборки.
     *
     * @return ListResult Страница.
     *
     * @throws LoreInvalidException Если выборка недопустима.
     */
    public function list(IOpenedRecords $records, ListQuery $query, string $message): ListResult
    {
        try {
            return $records->getList($query);
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new LoreInvalidException($message, $exception);
        }
    }

    /**
     * Одна строка или null.
     *
     * @param IOpenedRecords $records Строки.
     * @param int $rowId Id.
     * @param string $message Текст ошибки чтения.
     *
     * @return array<string, mixed>|null Строка или null.
     */
    public function getById(IOpenedRecords $records, int $rowId, string $message): ?array
    {
        unset($message);

        return $records->getById($rowId);
    }

    /**
     * Выполняет запись с общей картой ошибок.
     *
     * @param Closure $write Запись.
     * @param string $message Текст ошибки значений.
     *
     * @return mixed Результат.
     *
     * @throws LoreInvalidException Если строка недопустима.
     */
    public function write(Closure $write, string $message): mixed
    {
        try {
            return $write();
        } catch (
            FieldInvalidException
            | SmartMapInvalidException
            | RowWriteFailedException
            | ReferenceConstraintException
            | UniqueConstraintException $exception
        ) {
            throw new LoreInvalidException($message, $exception);
        }
    }
}
