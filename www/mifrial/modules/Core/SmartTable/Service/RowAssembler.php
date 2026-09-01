<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use JsonException;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Schema\SchemaMismatchException;
use Mifrial\Core\SmartTable\Field\BaseField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Сборка payload и гидратация строки без SQL.
 */
final class RowAssembler
{
    /**
     * Готовит колонки для insert без id.
     *
     * @param array<string, mixed> $values Вход API.
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return array<string, mixed> Значения для драйвера.
     *
     * @throws MapInvalidException Если ключ неизвестен или id задан.
     */
    public function assembleInsert(array $values, SmartTableDefinition $tableDefinition): array
    {
        $fieldMap = $tableDefinition->getMap();
        $this->assertKnownKeys($values, $fieldMap);
        if (array_key_exists('id', $values) && $values['id'] !== null) {
            throw new MapInvalidException('id is assigned by the database');
        }

        $payload = [];
        foreach ($fieldMap as $fieldName => $field) {
            if ($fieldName === 'id' || $field->settings()->multiple()) {
                continue;
            }

            $keyPresent = array_key_exists($fieldName, $values);
            $payload[$fieldName] = $field->extract($field->cast(
                $keyPresent ? $values[$fieldName] : null,
                $keyPresent,
            ));
        }

        return $payload;
    }

    /**
     * Готовит множества multiple для mfv.
     *
     * @param array<string, mixed> $values Вход API.
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param bool $includeMissing Все multiple (add) или только ключи (update).
     *
     * @return array<string, array<int, mixed>> Имя поля => extract list.
     */
    public function assembleMultiple(
        array $values,
        SmartTableDefinition $tableDefinition,
        bool $includeMissing,
    ): array {
        $fieldMap = $tableDefinition->getMap();
        $this->assertKnownKeys($values, $fieldMap);
        $multiplePayload = [];
        foreach ($fieldMap as $fieldName => $field) {
            if (!$field->settings()->multiple()) {
                continue;
            }

            $keyPresent = array_key_exists($fieldName, $values);
            if (!$includeMissing && !$keyPresent) {
                continue;
            }

            $multiplePayload[$fieldName] = $field->extract($field->cast(
                $keyPresent ? $values[$fieldName] : null,
                $keyPresent,
            ));
        }

        return $multiplePayload;
    }

    /**
     * Готовит колонки частичного update.
     *
     * @param array<string, mixed> $values Вход API.
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return array<string, mixed> Значения для драйвера.
     *
     * @throws MapInvalidException Если набор пуст, есть id или неизвестный ключ.
     */
    public function assembleUpdate(array $values, SmartTableDefinition $tableDefinition): array
    {
        if ($values === []) {
            throw new MapInvalidException('Update requires at least one field');
        }

        if (array_key_exists('id', $values)) {
            throw new MapInvalidException('id cannot be updated');
        }

        $fieldMap = $tableDefinition->getMap();
        $this->assertKnownKeys($values, $fieldMap);
        $payload = [];
        foreach ($values as $fieldName => $inputValue) {
            if ($fieldMap[$fieldName]->settings()->multiple()) {
                continue;
            }

            $payload[$fieldName] = $fieldMap[$fieldName]->extract(
                $fieldMap[$fieldName]->cast($inputValue, true),
            );
        }

        return $payload;
    }

    /**
     * Собирает PHP-строку из колонок БД.
     *
     * @param array<string, mixed> $databaseRow Колонки выборки.
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return array<string, mixed> Гидратированные поля.
     *
     * @throws SchemaMismatchException Если колонки карты нет в строке.
     */
    public function hydrateRow(array $databaseRow, SmartTableDefinition $tableDefinition): array
    {
        return $this->hydrateSelected(
            $databaseRow,
            $tableDefinition,
            array_keys($tableDefinition->getMap()),
        );
    }

    /**
     * Кодирует JSON-поля для драйвера.
     *
     * @param array<string, mixed> $payload Значения extract.
     * @param SmartTableDefinition $tableDefinition Определение.
     *
     * @return array<string, mixed> Payload с JSON-строками.
     *
     * @throws FieldInvalidException Если json_encode не удался.
     */
    public function encodeJsonColumns(array $payload, SmartTableDefinition $tableDefinition): array
    {
        foreach ($tableDefinition->getMap() as $fieldName => $field) {
            if ($field->type() !== 'json') {
                continue;
            }

            if (!array_key_exists($fieldName, $payload) || $payload[$fieldName] === null) {
                continue;
            }

            try {
                $payload[$fieldName] = json_encode($payload[$fieldName], JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new FieldInvalidException('JSON value cannot be encoded');
            }
        }

        return $payload;
    }

    /**
     * Собирает PHP-строку из выбранных колонок.
     *
     * @param array<string, mixed> $databaseRow Колонки выборки.
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param array<int, string> $fieldNames Поля к гидратации.
     *
     * @return array<string, mixed> Гидратированные поля.
     *
     * @throws MapInvalidException Если выбранного поля нет в карте.
     * @throws SchemaMismatchException Если выбранной колонки нет в строке.
     */
    public function hydrateSelected(
        array $databaseRow,
        SmartTableDefinition $tableDefinition,
        array $fieldNames,
    ): array {
        $fieldMap = $tableDefinition->getMap();
        $hydratedRow = [];
        foreach ($fieldNames as $fieldName) {
            if (!isset($fieldMap[$fieldName])) {
                throw new MapInvalidException('Unknown field name');
            }

            if (!array_key_exists($fieldName, $databaseRow)) {
                throw new SchemaMismatchException('Result is missing mapped column');
            }

            $field = $fieldMap[$fieldName];
            $hydratedRow[$fieldName] = $field->settings()->multiple()
                ? $this->hydrateMultipleList($field, $databaseRow[$fieldName])
                : $field->hydrate($databaseRow[$fieldName]);
        }

        return $hydratedRow;
    }

    /**
     * Гидратирует list значений mfv.
     *
     * @param BaseField $field Multiple-поле.
     * @param mixed $rawList Сырой list.
     *
     * @return array<int, mixed> PHP-значения.
     *
     * @throws SchemaMismatchException Если это не list.
     */
    private function hydrateMultipleList(BaseField $field, mixed $rawList): array
    {
        if (!is_array($rawList) || !array_is_list($rawList)) {
            throw new SchemaMismatchException('Multiple column must be a list of values');
        }

        $hydratedItems = [];
        foreach ($rawList as $rawValue) {
            $hydratedItems[] = $field->hydrate($rawValue);
        }

        return $hydratedItems;
    }

    /**
     * Проверяет, что ключи есть в карте.
     *
     * @param array<string, mixed> $values Вход.
     * @param array<string, BaseField> $fieldMap Карта.
     *
     * @return void
     *
     * @throws MapInvalidException Если ключ неизвестен.
     */
    private function assertKnownKeys(array $values, array $fieldMap): void
    {
        foreach (array_keys($values) as $fieldName) {
            if (!isset($fieldMap[$fieldName])) {
                throw new MapInvalidException('Unknown field name');
            }
        }
    }
}
