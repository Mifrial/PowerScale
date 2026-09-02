<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Field;

use Mifrial\Core\Kernel\Value\DateTime as UnixDateTime;
use Mifrial\Core\SmartTable\Dto\ColumnMeta;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Value\DateTimeNow;

/**
 * Момент времени: в PHP объект ядра, в БД unix INT.
 */
final class DateTimeField extends BaseField
{
    /**
     * Возвращает наш тип поля.
     *
     * @return string Код типа.
     */
    public function type(): string
    {
        return 'datetime';
    }

    /**
     * Возвращает метаданные колонки.
     *
     * @return ColumnMeta Описание SQL-типа.
     */
    public function column(): ColumnMeta
    {
        return new ColumnMeta('INT');
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
        if ($inputValue instanceof DateTimeNow) {
            return UnixDateTime::now();
        }

        if (!$inputValue instanceof UnixDateTime) {
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
        if (!$phpValue instanceof UnixDateTime) {
            $this->invalidValue();
        }

        return $phpValue->toUnix();
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
        if ($databaseValue instanceof UnixDateTime) {
            return $databaseValue;
        }

        if (is_int($databaseValue)) {
            return UnixDateTime::fromUnix($databaseValue);
        }

        if (is_string($databaseValue) && preg_match('/^-?\d+$/', $databaseValue) === 1) {
            return UnixDateTime::fromUnix((int) $databaseValue);
        }

        $this->invalidValue();
    }
}
