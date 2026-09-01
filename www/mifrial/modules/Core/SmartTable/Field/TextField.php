<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Field;

use Mifrial\Core\SmartTable\Dto\ColumnMeta;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;

/**
 * Длинный текст TEXT без лимита SmartTable.
 */
final class TextField extends BaseField
{
    /**
     * Возвращает наш тип поля.
     *
     * @return string Код типа.
     */
    public function type(): string
    {
        return 'text';
    }

    /**
     * Возвращает метаданные колонки.
     *
     * @return ColumnMeta Описание SQL-типа.
     */
    public function column(): ColumnMeta
    {
        return new ColumnMeta('TEXT');
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
        return $this->assertString($inputValue);
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
        return $this->assertString($phpValue);
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
        return $this->assertString($databaseValue);
    }

    /**
     * Проверяет, что значение — строка.
     *
     * @param mixed $value Кандидат.
     *
     * @return string Строка.
     */
    private function assertString(mixed $value): string
    {
        if (!is_string($value)) {
            $this->invalidValue();
        }

        return $value;
    }
}
