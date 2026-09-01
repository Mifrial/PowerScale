<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Field;

use Mifrial\Core\SmartTable\Dto\ColumnMeta;
use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;

/**
 * Целое INT с опциональными min/max.
 */
class IntField extends BaseField
{
    /**
     * Создаёт целочисленное поле.
     *
     * @param string $fieldName Имя поля.
     * @param FieldSettings $fieldSettings Настройки.
     * @param int|null $minimum Нижняя граница или null.
     * @param int|null $maximum Верхняя граница или null.
     *
     * @return void
     */
    public function __construct(
        string $fieldName,
        FieldSettings $fieldSettings,
        private readonly ?int $minimum = null,
        private readonly ?int $maximum = null,
    ) {
        parent::__construct($fieldName, $fieldSettings);
    }

    /**
     * Возвращает наш тип поля.
     *
     * @return string Код типа.
     */
    public function type(): string
    {
        return 'int';
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
        if (!is_int($inputValue)) {
            $this->invalidValue();
        }

        return $this->assertRange($inputValue);
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
        if (!is_int($phpValue)) {
            $this->invalidValue();
        }

        return $this->assertRange($phpValue);
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
        if (is_int($databaseValue)) {
            return $this->assertRange($databaseValue);
        }

        if (is_string($databaseValue) && preg_match('/^-?\d+$/', $databaseValue) === 1) {
            return $this->assertRange((int) $databaseValue);
        }

        $this->invalidValue();
    }

    /**
     * Проверяет min/max.
     *
     * @param int $intValue Целое.
     *
     * @return int То же целое.
     */
    private function assertRange(int $intValue): int
    {
        if ($this->minimum !== null && $intValue < $this->minimum) {
            $this->invalidValue();
        }

        if ($this->maximum !== null && $intValue > $this->maximum) {
            $this->invalidValue();
        }

        return $intValue;
    }
}
