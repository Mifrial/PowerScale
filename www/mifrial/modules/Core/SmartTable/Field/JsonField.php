<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Field;

use JsonException;
use Mifrial\Core\SmartTable\Dto\ColumnMeta;
use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Interface\Field\IFieldHydrator;

/**
 * JSON-документ: PHP array/скаляр, колонка JSON.
 */
final class JsonField extends BaseField
{
    /**
     * Создаёт JSON-поле.
     *
     * @param string $fieldName Имя поля.
     * @param FieldSettings $fieldSettings Настройки.
     * @param IFieldHydrator|null $fieldHydrator Опциональный hydrator объекта.
     *
     * @return void
     */
    public function __construct(
        string $fieldName,
        FieldSettings $fieldSettings,
        private readonly ?IFieldHydrator $fieldHydrator = null,
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
        return 'json';
    }

    /**
     * Возвращает метаданные колонки.
     *
     * @return ColumnMeta Описание SQL-типа.
     */
    public function column(): ColumnMeta
    {
        return new ColumnMeta('JSON');
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
        if ($this->fieldHydrator instanceof IFieldHydrator) {
            $decodedValue = $this->fieldHydrator->extract($inputValue);
            $this->assertJsonCompatible($decodedValue);

            return $inputValue;
        }

        $this->assertJsonCompatible($inputValue);

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
        if ($this->fieldHydrator instanceof IFieldHydrator) {
            $decodedValue = $this->fieldHydrator->extract($phpValue);
            $this->assertJsonCompatible($decodedValue);

            return $decodedValue;
        }

        $this->assertJsonCompatible($phpValue);

        return $phpValue;
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
        $decodedValue = $this->decodeIncoming($databaseValue);
        if ($this->fieldHydrator instanceof IFieldHydrator) {
            return $this->fieldHydrator->hydrate($decodedValue);
        }

        return $decodedValue;
    }

    /**
     * Декодирует строку JSON с границы драйвера.
     *
     * @param mixed $databaseValue Сырое значение.
     *
     * @return mixed Array или скаляр.
     */
    private function decodeIncoming(mixed $databaseValue): mixed
    {
        if (is_string($databaseValue)) {
            return $this->decodeJsonString($databaseValue);
        }

        if (is_object($databaseValue)) {
            return $this->objectToJsonArray($databaseValue);
        }

        $this->assertJsonCompatible($databaseValue);

        return $databaseValue;
    }

    /**
     * Декодирует строку JSON или оставляет скаляр.
     *
     * @param string $databaseValue Сырая строка колонки.
     *
     * @return mixed Array, скаляр JSON или исходная строка.
     */
    private function decodeJsonString(string $databaseValue): mixed
    {
        try {
            return json_decode($databaseValue, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->assertJsonCompatible($databaseValue);

            return $databaseValue;
        }
    }

    /**
     * Разворачивает объект драйвера в PHP-массив JSON.
     *
     * @param object $databaseValue Значение колонки.
     *
     * @return mixed Array или скаляр.
     *
     * @throws FieldInvalidException Если объект не сериализуется в JSON.
     */
    private function objectToJsonArray(object $databaseValue): mixed
    {
        try {
            $encoded = json_encode($databaseValue, JSON_THROW_ON_ERROR);

            return json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->invalidValue();
        }
    }

    /**
     * Проверяет JSON-совместимость без смены типа.
     *
     * @param mixed $value Кандидат.
     *
     * @return void
     */
    private function assertJsonCompatible(mixed $value): void
    {
        try {
            json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->invalidValue();
        }
    }
}
