<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Interface\Service;

use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Exception\Schema\DdlFailedException;
use Mifrial\Core\SmartTable\Exception\Schema\TableExistsException;
use Mifrial\Core\SmartTable\Exception\Schema\TableMissingException;

/**
 * Порт словаря: meta DDL и runtime-таблицы по имени.
 */
interface ITableCatalog
{
    /**
     * Создаёт или дополняет физические meta-таблицы.
     *
     * @return void
     *
     * @throws TableExistsException Если create на уже существующей таблице.
     * @throws TableMissingException Если цели FK нет.
     * @throws MapInvalidException Если карта meta некорректна.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function installMeta(): void;

    /**
     * Открывает handle runtime-таблицы по строке словаря.
     *
     * @param string $tableName Физическое имя.
     *
     * @return IOpenedTable Handle.
     *
     * @throws TableMissingException Если строки словаря нет.
     * @throws MapInvalidException Если карта из словаря некорректна.
     */
    public function openByName(string $tableName): IOpenedTable;

    /**
     * Регистрирует таблицу в словаре и создаёт физику, если её ещё нет.
     *
     * @param string $tableName Физическое имя.
     * @param array<int, array<string, mixed>> $fieldSpecs Спеки полей без id.
     * @param array<int, array<int, string>> $uniqueKeys Составные unique.
     *
     * @return IOpenedTable Handle новой или поднятой таблицы.
     *
     * @throws TableExistsException Если физика уже есть.
     * @throws TableMissingException Если цели FK нет.
     * @throws MapInvalidException Если имя или спеки недопустимы.
     * @throws UniqueConstraintException Если имя таблицы в словаре занято.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function createTable(
        string $tableName,
        array $fieldSpecs,
        array $uniqueKeys = [],
    ): IOpenedTable;

    /**
     * Добавляет поле в словарь и колонку/sidecar.
     *
     * @param string $tableName Физическое имя.
     * @param array<string, mixed> $fieldSpec Спека поля.
     *
     * @return void
     *
     * @throws TableMissingException Если словаря или физики нет.
     * @throws MapInvalidException Если спека или имя недопустимы.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function addField(string $tableName, array $fieldSpec): void;

    /**
     * Удаляет поле из словаря и leftover хранения.
     *
     * @param string $tableName Физическое имя.
     * @param string $fieldName Имя поля.
     *
     * @return void
     *
     * @throws TableMissingException Если строки словаря нет.
     * @throws MapInvalidException Если поле id или неизвестно.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function dropField(string $tableName, string $fieldName): void;

    /**
     * Удаляет физику (если есть) и строки словаря.
     *
     * @param string $tableName Физическое имя.
     *
     * @return void
     *
     * @throws TableMissingException Если строки словаря нет.
     * @throws DdlFailedException Если входящий FK не дал DROP.
     * @throws MapInvalidException Если карта некорректна.
     */
    public function dropTable(string $tableName): void;

    /**
     * Заменяет составные unique runtime-таблицы.
     *
     * @param string $tableName Физическое имя.
     * @param array<int, array<int, string>> $uniqueKeys Кортежи.
     *
     * @return void
     *
     * @throws TableMissingException Если строки словаря нет.
     * @throws MapInvalidException Если ключи некорректны.
     * @throws DdlFailedException Если драйвер отклонил DDL.
     */
    public function setUniqueKeys(string $tableName, array $uniqueKeys): void;
}
