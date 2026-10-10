<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Interface\Service;

use Mifrial\Core\SmartTable\Dto\ConditionalCas;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\ReferenceConstraintException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Exception\Schema\SchemaMismatchException;
use Mifrial\Core\SmartTable\Exception\Schema\TableMissingException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;

/**
 * Условные операции одной открытой карты.
 */
interface IConditionalOpenedRecords
{
    /**
     * Пишет строку, если CAS-поле равно ожидаемому, и увеличивает его на один.
     *
     * @param int $rowId Идентификатор.
     * @param ConditionalCas $cas Поле и ожидаемое значение.
     * @param array<string, mixed> $values Поля к записи без CAS.
     *
     * @return bool true, если обновлена ровно одна строка.
     *
     * @throws MapInvalidException Если CAS-поле недопустимо или лежит в values.
     * @throws FieldInvalidException Если значение не прошло cast.
     * @throws FieldRequiredException Если required нарушен.
     * @throws TableMissingException Если таблицы нет.
     * @throws SchemaMismatchException Если колонки карты нет.
     * @throws RowWriteFailedException Если update затронул больше одной строки.
     * @throws ReferenceConstraintException Если нет родителя.
     * @throws UniqueConstraintException Если unique нарушен.
     */
    public function updateConditional(int $rowId, ConditionalCas $cas, array $values): bool;

    /**
     * Читает текущую строку с блокировкой, без кэша.
     *
     * @param int $rowId Идентификатор.
     *
     * @return array<string, mixed>|null Гидратированные поля или null.
     *
     * @throws TableMissingException Если таблицы нет.
     * @throws SchemaMismatchException Если колонки карты нет.
     */
    public function getCurrentById(int $rowId): ?array;
}
