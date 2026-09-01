<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Field;

use Mifrial\Core\SmartTable\Dto\ColumnMeta;
use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;

/**
 * Общее поле SmartTable: имя, настройки, cast/extract/hydrate.
 */
abstract class BaseField
{
    use MultipleList;

    /**
     * Создаёт поле.
     *
     * @param string $fieldName Имя поля.
     * @param FieldSettings $fieldSettings Настройки.
     *
     * @return void
     *
     * @throws MapInvalidException Если имя недопустимо.
     */
    public function __construct(
        private readonly string $fieldName,
        private readonly FieldSettings $fieldSettings,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $fieldName) !== 1) {
            throw new MapInvalidException('Field name is invalid');
        }
    }

    /**
     * Возвращает имя поля.
     *
     * @return string Имя.
     */
    public function name(): string
    {
        return $this->fieldName;
    }

    /**
     * Возвращает ключ подписи или имя поля.
     *
     * @return string Ключ сообщения или FIELD_NAME.
     */
    public function label(): string
    {
        $labelKey = $this->fieldSettings->label();
        if ($labelKey === '') {
            return $this->fieldName;
        }

        return $labelKey;
    }

    /**
     * Возвращает наш тип поля.
     *
     * @return string Код типа.
     */
    abstract public function type(): string;

    /**
     * Возвращает настройки поля.
     *
     * @return FieldSettings Настройки.
     */
    public function settings(): FieldSettings
    {
        return $this->fieldSettings;
    }

    /**
     * Возвращает метаданные колонки.
     *
     * @return ColumnMeta Описание SQL-типа.
     */
    abstract public function column(): ColumnMeta;

    /**
     * Нормализует вход API.
     *
     * @param mixed $inputValue Вход или null.
     * @param bool $keyPresent Ключ присутствовал в наборе.
     *
     * @return mixed Нормализованное PHP-значение.
     *
     * @throws FieldInvalidException Если значение недопустимо.
     * @throws FieldRequiredException Если required нарушен.
     * @throws MapInvalidException Если в list есть дубль.
     */
    public function cast(mixed $inputValue, bool $keyPresent): mixed
    {
        $resolvedValue = $this->resolveIncoming($inputValue, $keyPresent);
        if ($this->fieldSettings->multiple()) {
            return $this->castMultiple($resolvedValue);
        }

        $this->assertScalarRequired($resolvedValue);
        if ($resolvedValue === null) {
            return null;
        }

        return $this->castPresent($resolvedValue);
    }

    /**
     * Готовит значение к границе БД.
     *
     * @param mixed $phpValue Нормализованное PHP-значение.
     *
     * @return mixed Значение для драйвера.
     *
     * @throws FieldInvalidException Если значение недопустимо.
     * @throws FieldRequiredException Если required нарушен.
     * @throws MapInvalidException Если в list есть дубль.
     */
    public function extract(mixed $phpValue): mixed
    {
        if ($this->fieldSettings->multiple()) {
            return $this->extractMultiple($phpValue);
        }

        $this->assertScalarRequired($phpValue);
        if ($phpValue === null) {
            return null;
        }

        return $this->extractPresent($phpValue);
    }

    /**
     * Собирает PHP-значение из колонки.
     *
     * @param mixed $databaseValue Сырое значение драйвера.
     *
     * @return mixed PHP-значение.
     *
     * @throws FieldInvalidException Если значение недопустимо.
     */
    public function hydrate(mixed $databaseValue): mixed
    {
        if ($databaseValue === null) {
            return null;
        }

        return $this->hydratePresent($databaseValue);
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
    abstract protected function castPresent(mixed $inputValue): mixed;

    /**
     * Готовит ненулевое значение к БД.
     *
     * @param mixed $phpValue PHP-значение.
     *
     * @return mixed Значение для драйвера.
     *
     * @throws FieldInvalidException Если тип неверен.
     */
    abstract protected function extractPresent(mixed $phpValue): mixed;

    /**
     * Собирает ненулевое значение из БД.
     *
     * @param mixed $databaseValue Сырое значение.
     *
     * @return mixed PHP-значение.
     *
     * @throws FieldInvalidException Если тип неверен.
     */
    abstract protected function hydratePresent(mixed $databaseValue): mixed;

    /**
     * Разрешает null на записи (автоинкремент id).
     *
     * @return bool true, если null на add допустим.
     */
    protected function allowsNullWrite(): bool
    {
        return false;
    }

    /**
     * Бросает ошибку типа значения.
     *
     * @return never
     *
     * @throws FieldInvalidException Всегда.
     */
    protected function invalidValue(): never
    {
        throw new FieldInvalidException();
    }

    /**
     * Подставляет default при отсутствии ключа.
     *
     * @param mixed $inputValue Вход.
     * @param bool $keyPresent Ключ был в наборе.
     *
     * @return mixed Значение после default.
     */
    private function resolveIncoming(mixed $inputValue, bool $keyPresent): mixed
    {
        if (!$keyPresent && $this->fieldSettings->hasDefault()) {
            return $this->fieldSettings->defaultValue();
        }

        return $inputValue;
    }

    /**
     * Проверяет required на скалярной записи.
     *
     * @param mixed $phpValue Кандидат.
     *
     * @return void
     *
     * @throws FieldRequiredException Если поле нельзя записать.
     */
    private function assertScalarRequired(mixed $phpValue): void
    {
        if ($phpValue === null && $this->fieldSettings->required() && !$this->allowsNullWrite()) {
            throw new FieldRequiredException();
        }
    }
}
