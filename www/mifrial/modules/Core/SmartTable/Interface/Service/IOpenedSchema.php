<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Interface\Service;

use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Schema\DdlFailedException;
use Mifrial\Core\SmartTable\Exception\Schema\SchemaMismatchException;
use Mifrial\Core\SmartTable\Exception\Schema\TableExistsException;
use Mifrial\Core\SmartTable\Exception\Schema\TableMissingException;

/**
 * DDL одной открытой карты.
 */
interface IOpenedSchema
{
    /**
     * Проверяет, есть ли физическая таблица этой карты.
     *
     * @return bool true, если таблица есть.
     */
    public function exists(): bool;

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
}
