<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Field;

use Mifrial\Core\SmartTable\Dto\ColumnMeta;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;

/**
 * Булево значение TINYINT(1).
 */
final class BoolField extends BaseField
{
    /**
     * Возвращает наш тип поля.
     *
     * @return string Код типа.
     */
    public function type(): string
    {
        return 'bool';
    }

    /**
     * Возвращает метаданные колонки.
     *
     * @return ColumnMeta Описание SQL-типа.
     */
    public function column(): ColumnMeta
    {
        return new ColumnMeta('TINYINT', 1);
    }

    /**
     * Приводит присутствующее API-значение.
     *
     * @param mixed $inputValue Вход.
     *
     * @return mixed Нормализованное значение.
     *
     * @throws FieldInvalidException Если тип неверен.
     */
    protected function castPresent(mixed $inputValue): mixed
    {
        if (!is_bool($inputValue)) {
            $this->invalidValue();
        }

        return $inputValue;
    }

    /**
     * Готовит ненулевое значение к БД.
     *
     * @param mixed $phpValue PHP-значение.
     *
     * @return mixed Значение для драйвера.
     *
     * @throws FieldInvalidException Если тип неверен.
     */
    protected function extractPresent(mixed $phpValue): mixed
    {
        if (!is_bool($phpValue)) {
            $this->invalidValue();
        }

        return $phpValue ? 1 : 0;
    }

    /**
     * Собирает ненулевое значение из БД.
     *
     * @param mixed $databaseValue Сырое значение.
     *
     * @return mixed PHP-значение.
     *
     * @throws FieldInvalidException Если тип неверен.
     */
    protected function hydratePresent(mixed $databaseValue): mixed
    {
        if ($databaseValue === true || $databaseValue === 1 || $databaseValue === '1') {
            return true;
        }

        if ($databaseValue === false || $databaseValue === 0 || $databaseValue === '0') {
            return false;
        }

        $this->invalidValue();
    }
}
