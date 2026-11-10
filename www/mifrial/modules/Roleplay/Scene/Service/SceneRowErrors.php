<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Service;

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
use Mifrial\Roleplay\Scene\Exception\SceneInvalidException;
use Mifrial\Roleplay\Scene\Exception\SceneNotFoundException;

/**
 * Единая карта ошибок SmartTable в ошибки локаций.
 */
final class SceneRowErrors
{
    /**
     * Вставляет строку.
     *
     * @param Closure $write Операция вставки.
     * @param string $message Текст ошибки значений.
     *
     * @return int Id.
     *
     * @throws SceneInvalidException Если строка недопустима.
     */
    public function add(Closure $write, string $message): int
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
            throw new SceneInvalidException($message, $exception);
        }
    }

    /**
     * Вставляет строку по карте полей.
     *
     * @param IOpenedRecords $records Строки.
     * @param array<string, mixed> $fields Поля.
     * @param string $message Текст ошибки значений.
     *
     * @return int Id.
     *
     * @throws SceneInvalidException Если строка недопустима.
     */
    public function insert(IOpenedRecords $records, array $fields, string $message): int
    {
        return $this->add(fn (): int => $records->add($fields), $message);
    }

    /**
     * Пишет строку.
     *
     * @param Closure $write Операция записи.
     * @param string $message Текст ошибки значений.
     *
     * @return void
     *
     * @throws SceneNotFoundException Если строки нет.
     * @throws SceneInvalidException Если поле недопустимо.
     */
    public function update(Closure $write, string $message): void
    {
        try {
            $write();
        } catch (RowNotFoundException $exception) {
            throw new SceneNotFoundException('Scene row was not found', $exception);
        } catch (FieldInvalidException | SmartMapInvalidException | UniqueConstraintException $exception) {
            throw new SceneInvalidException($message, $exception);
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
     * @throws SceneNotFoundException Если строки нет.
     * @throws SceneInvalidException Если поле недопустимо.
     */
    public function updateById(IOpenedRecords $records, int $rowId, array $fields, string $message): void
    {
        $this->update(
            function () use ($records, $rowId, $fields): void {
                $records->update($rowId, $fields);
            },
            $message,
        );
    }

    /**
     * Удаляет строку.
     *
     * @param Closure $write Операция удаления.
     * @param string $message Текст ошибки ссылок.
     *
     * @return void
     *
     * @throws SceneNotFoundException Если строки нет.
     * @throws SceneInvalidException Если удаление запрещено.
     */
    public function delete(Closure $write, string $message): void
    {
        try {
            $write();
        } catch (RowNotFoundException $exception) {
            throw new SceneNotFoundException('Scene row was not found', $exception);
        } catch (ReferenceConstraintException $exception) {
            throw new SceneInvalidException($message, $exception);
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
     * @throws SceneNotFoundException Если строки нет.
     * @throws SceneInvalidException Если удаление запрещено.
     */
    public function deleteById(IOpenedRecords $records, int $rowId, string $message): void
    {
        $this->delete(
            function () use ($records, $rowId): void {
                $records->delete($rowId);
            },
            $message,
        );
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
     * @throws SceneInvalidException Если выборка недопустима.
     */
    public function list(IOpenedRecords $records, ListQuery $query, string $message): ListResult
    {
        try {
            return $records->getList($query);
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new SceneInvalidException($message, $exception);
        }
    }
}
