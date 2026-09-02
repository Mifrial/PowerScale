<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Interface\Service;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\ListResult;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldMultipleUnsupportedException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\ReferenceConstraintException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Exception\Schema\DdlFailedException;
use Mifrial\Core\SmartTable\Exception\Schema\SchemaMismatchException;
use Mifrial\Core\SmartTable\Exception\Schema\TableExistsException;
use Mifrial\Core\SmartTable\Exception\Schema\TableMissingException;

/**
 * Фасад одной таблицы: DDL и строка.
 */
interface IOpenedTable
{
    /**
     * Создаёт физическую таблицу по карте.
     *
     * @return void
     *
     * @throws TableExistsException Если таблица уже есть.
     * @throws TableMissingException Если таблицы цели FK нет.
     * @throws MapInvalidException Если ColumnMeta нельзя отобразить.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function createTable(): void;

    /**
     * Добавляет отсутствующие колонки, индексы, mfv и FK карты.
     *
     * @return void
     *
     * @throws TableMissingException Если своей таблицы или таблицы цели FK нет.
     * @throws SchemaMismatchException Если нет колонки id.
     * @throws MapInvalidException Если ColumnMeta нельзя отобразить.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function updateTable(): void;

    /**
     * Добавляет недостающее по карте и снимает leftover.
     *
     * @return void
     *
     * @throws TableMissingException Если своей таблицы или таблицы цели FK нет.
     * @throws SchemaMismatchException Если нет колонки id.
     * @throws MapInvalidException Если ColumnMeta нельзя отобразить.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function forceUpdateTable(): void;

    /**
     * Удаляет физическую таблицу и mfv полей карты.
     *
     * @return void
     *
     * @throws TableMissingException Если своей таблицы нет.
     * @throws MapInvalidException Если имя sidecar недопустимо.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function deleteTable(): void;

    /**
     * Вставляет строку и возвращает id.
     *
     * @param array<string, mixed> $values Значения полей.
     *
     * @return int Новый id.
     *
     * @throws MapInvalidException Если ключ неизвестен или id задан.
     * @throws FieldInvalidException Если значение не прошло cast.
     * @throws FieldRequiredException Если required нарушен.
     * @throws TableMissingException Если таблицы нет.
     * @throws SchemaMismatchException Если колонки нет.
     * @throws RowWriteFailedException Если insert не дал id.
     * @throws ReferenceConstraintException Если нет родителя.
     * @throws UniqueConstraintException Если unique нарушен.
     */
    public function add(array $values): int;

    /**
     * Обновляет переданные поля строки.
     *
     * @param int $rowId Идентификатор.
     * @param array<string, mixed> $values Поля к записи.
     *
     * @return void
     *
     * @throws MapInvalidException Если набор пуст, есть id или неизвестный ключ.
     * @throws FieldInvalidException Если значение не прошло cast.
     * @throws FieldRequiredException Если required нарушен.
     * @throws RowNotFoundException Если строки нет.
     * @throws TableMissingException Если таблицы нет.
     * @throws SchemaMismatchException Если колонки нет.
     * @throws RowWriteFailedException Если update не удался.
     * @throws ReferenceConstraintException Если нет родителя.
     * @throws UniqueConstraintException Если unique нарушен.
     */
    public function update(int $rowId, array $values): void;

    /**
     * Удаляет строку.
     *
     * @param int $rowId Идентификатор.
     *
     * @return void
     *
     * @throws RowNotFoundException Если строки нет.
     * @throws TableMissingException Если таблицы нет.
     * @throws RowWriteFailedException Если delete не удался.
     * @throws ReferenceConstraintException Если на строку есть ссылки.
     */
    public function delete(int $rowId): void;

    /**
     * Читает строку по id или null.
     *
     * @param int $rowId Идентификатор.
     * @param int|null $cacheTtl Секунды кэша; null — всегда БД.
     *
     * @return array<string, mixed>|null Гидратированные поля или null.
     *
     * @throws TableMissingException Если таблицы нет.
     * @throws SchemaMismatchException Если колонки карты нет.
     * @throws MapInvalidException Если TTL ≤ 0.
     */
    public function getById(int $rowId, ?int $cacheTtl = null): ?array;

    /**
     * Возвращает страницу строк по запросу.
     *
     * @param ListQuery $listQuery Запрос списка.
     * @param int|null $cacheTtl Секунды кэша; null — всегда БД.
     *
     * @return ListResult Строки и optional total.
     *
     * @throws MapInvalidException Если запрос не сходится с картой или TTL ≤ 0.
     * @throws FieldInvalidException Если операнд фильтра не прошёл cast.
     * @throws FieldMultipleUnsupportedException Если фильтр/sort по multiple или @.
     * @throws TableMissingException Если таблицы нет.
     * @throws SchemaMismatchException Если колонки нет.
     * @throws RowWriteFailedException Если драйвер отклонил SELECT.
     */
    public function getList(ListQuery $listQuery, ?int $cacheTtl = null): ListResult;
}
