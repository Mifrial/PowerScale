<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Service;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\ListResult;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException as SmartMapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\ReferenceConstraintException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Library\Asset\Exception\AssetInvalidException;
use Mifrial\Library\Asset\Exception\AssetNotFoundException;

/**
 * Единая карта ошибок SmartTable в ошибки библиотеки ассетов.
 */
final class AssetRowErrors
{
    /**
     * Вставляет строку по карте полей.
     *
     * @param IOpenedRecords $records Строки.
     * @param array<string, mixed> $fields Поля.
     * @param string $message Текст ошибки значений.
     *
     * @return int Id.
     *
     * @throws AssetInvalidException Если строка недопустима.
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
            throw new AssetInvalidException($message, $exception);
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
     * @throws AssetNotFoundException Если строки нет.
     * @throws AssetInvalidException Если поле недопустимо.
     */
    public function updateById(IOpenedRecords $records, int $rowId, array $fields, string $message): void
    {
        try {
            $records->update($rowId, $fields);
        } catch (RowNotFoundException $exception) {
            throw new AssetNotFoundException('Asset row was not found', $exception);
        } catch (FieldInvalidException | SmartMapInvalidException | UniqueConstraintException $exception) {
            throw new AssetInvalidException($message, $exception);
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
     * @throws AssetInvalidException Если выборка недопустима.
     */
    public function list(IOpenedRecords $records, ListQuery $query, string $message): ListResult
    {
        try {
            return $records->getList($query);
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new AssetInvalidException($message, $exception);
        }
    }
}
