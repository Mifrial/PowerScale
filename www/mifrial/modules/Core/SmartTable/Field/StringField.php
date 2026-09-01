<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Field;

use Mifrial\Core\SmartTable\Dto\ColumnMeta;
use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;

/**
 * Строка VARCHAR с ограничением длины в символах UTF-8.
 */
final class StringField extends BaseField
{
    /**
     * Создаёт строковое поле.
     *
     * @param string $fieldName Имя поля.
     * @param FieldSettings $fieldSettings Настройки.
     * @param int $maxLength Максимум символов, 1..1024.
     *
     * @return void
     *
     * @throws MapInvalidException Если maxLength вне диапазона.
     */
    public function __construct(
        string $fieldName,
        FieldSettings $fieldSettings,
        private readonly int $maxLength = 255,
    ) {
        parent::__construct($fieldName, $fieldSettings);
        if ($maxLength < 1 || $maxLength > 1024) {
            throw new MapInvalidException('String maxLength must be 1..1024');
        }
    }

    /**
     * Возвращает наш тип поля.
     *
     * @return string Код типа.
     */
    public function type(): string
    {
        return 'string';
    }

    /**
     * Возвращает максимум символов.
     *
     * @return int 1..1024.
     */
    public function maxLength(): int
    {
        return $this->maxLength;
    }

    /**
     * Возвращает метаданные колонки.
     *
     * @return ColumnMeta Описание SQL-типа.
     */
    public function column(): ColumnMeta
    {
        return new ColumnMeta('VARCHAR', $this->maxLength);
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
        return $this->assertUtf8String($inputValue);
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
        return $this->assertUtf8String($phpValue);
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
        return $this->assertUtf8String($databaseValue);
    }

    /**
     * Проверяет UTF-8 строку и длину.
     *
     * @param mixed $value Кандидат.
     *
     * @return string Строка.
     *
     * @throws FieldInvalidException Если не строка или слишком длинная.
     */
    private function assertUtf8String(mixed $value): string
    {
        if (!is_string($value)) {
            $this->invalidValue();
        }

        if (mb_strlen($value, 'UTF-8') > $this->maxLength) {
            $this->invalidValue();
        }

        return $value;
    }
}
