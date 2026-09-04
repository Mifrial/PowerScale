<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Table;

use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Field\BaseField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\StringField;

/**
 * Составные unique и имена индексов карты без SQL.
 */
final class UniqueKeyMap
{
    /**
     * Проверяет, что поле можно поставить в unique или index.
     *
     * @param BaseField $field Поле.
     *
     * @return void
     *
     * @throws MapInvalidException Если тип или multiple нельзя.
     */
    public function assertFieldCanBeUnique(BaseField $field): void
    {
        if ($field instanceof IdField || $field->settings()->multiple()) {
            throw new MapInvalidException('Index flags are not allowed on this field');
        }

        $fieldType = $field->type();
        if (!in_array($fieldType, ['string', 'int', 'bigint', 'bool', 'datetime', 'reference'], true)) {
            throw new MapInvalidException('Index flags are not allowed for this field type');
        }

        if ($field instanceof StringField && $field->maxLength() > 255) {
            throw new MapInvalidException('Indexed string maxLength cannot exceed 255');
        }
    }

    /**
     * Нормализует defineUniqueKeys после карты полей.
     *
     * @param array<string, BaseField> $fieldMap Карта.
     * @param array<int, mixed> $rawKeys Кортежи из definition.
     *
     * @return array<int, array<int, string>> Кортежи.
     *
     * @throws MapInvalidException Если кортеж или дубль недопустимы.
     */
    public function collect(array $fieldMap, array $rawKeys): array
    {
        $uniqueKeys = [];
        $seenTuples = [];
        foreach ($rawKeys as $tuple) {
            $normalized = $this->normalizeTuple($fieldMap, $tuple);
            $tupleKey = implode("\0", $normalized);
            if (isset($seenTuples[$tupleKey])) {
                throw new MapInvalidException('Duplicate unique key');
            }

            $seenTuples[$tupleKey] = true;
            $uniqueKeys[] = $normalized;
        }

        return $uniqueKeys;
    }

    /**
     * Имена индексов полей и кортежей без столкновений.
     *
     * @param string $tableName Физическое имя.
     * @param array<string, BaseField> $fieldMap Карта.
     * @param array<int, array<int, string>> $uniqueKeys Кортежи.
     *
     * @return void
     *
     * @throws MapInvalidException Если имя недопустимо или занято.
     */
    public function assertPlannedNames(string $tableName, array $fieldMap, array $uniqueKeys): void
    {
        $seenNames = [];
        foreach ($this->fieldIndexNames($tableName, $fieldMap) as $indexName) {
            $this->rememberIndexName($seenNames, $indexName);
        }

        foreach ($uniqueKeys as $tuple) {
            $this->rememberIndexName($seenNames, $this->makeIndexName($tableName, implode('_', $tuple), 'unq'));
        }
    }

    /**
     * Проверяет один кортеж unique.
     *
     * @param array<string, BaseField> $fieldMap Карта.
     * @param mixed $tuple Кортеж из definition.
     *
     * @return array<int, string> Имена полей.
     *
     * @throws MapInvalidException Если форма или поле недопустимы.
     */
    private function normalizeTuple(array $fieldMap, mixed $tuple): array
    {
        if (!$this->isStringList($tuple) || count($tuple) < 2) {
            throw new MapInvalidException('Unique key tuple is invalid');
        }

        $seenNames = [];
        $normalized = [];
        foreach ($tuple as $fieldName) {
            if (isset($seenNames[$fieldName]) || !isset($fieldMap[$fieldName])) {
                throw new MapInvalidException('Unique key field is invalid');
            }

            $seenNames[$fieldName] = true;
            $this->assertFieldCanBeUnique($fieldMap[$fieldName]);
            $normalized[] = $fieldName;
        }

        return $normalized;
    }

    /**
     * Список строк с индексами 0..n.
     *
     * @param mixed $tuple Значение.
     *
     * @return bool true, если список строк.
     */
    private function isStringList(mixed $tuple): bool
    {
        if (!is_array($tuple) || !array_is_list($tuple) || $tuple === []) {
            return false;
        }

        foreach ($tuple as $item) {
            if (!is_string($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Имена одноколоночных индексов.
     *
     * @param string $tableName Таблица.
     * @param array<string, BaseField> $fieldMap Карта.
     *
     * @return array<int, string> Имена.
     *
     * @throws MapInvalidException Если имя недопустимо.
     */
    private function fieldIndexNames(string $tableName, array $fieldMap): array
    {
        $indexNames = [];
        foreach ($fieldMap as $field) {
            if ($field->settings()->unique()) {
                $indexNames[] = $this->makeIndexName($tableName, $field->name(), 'unq');
                continue;
            }

            if ($field->settings()->indexed()) {
                $indexNames[] = $this->makeIndexName($tableName, $field->name(), 'idx');
            }
        }

        return $indexNames;
    }

    /**
     * Запоминает имя индекса.
     *
     * @param array<string, true> $seenNames Уже занятые.
     * @param string $indexName Имя.
     *
     * @return void
     *
     * @throws MapInvalidException Если имя уже есть.
     */
    private function rememberIndexName(array &$seenNames, string $indexName): void
    {
        if (isset($seenNames[$indexName])) {
            throw new MapInvalidException('Index name is invalid');
        }

        $seenNames[$indexName] = true;
    }

    /**
     * Собирает `{table}_{middle}_{suffix}`.
     *
     * @param string $tableName Таблица.
     * @param string $middle Поле или поля через `_`.
     * @param string $suffix idx или unq.
     *
     * @return string Имя.
     *
     * @throws MapInvalidException Если имя недопустимо.
     */
    private function makeIndexName(string $tableName, string $middle, string $suffix): string
    {
        $physicalName = $tableName . '_' . $middle . '_' . $suffix;
        if (preg_match('/^[a-z][a-z0-9_]*$/', $physicalName) !== 1 || strlen($physicalName) > 64) {
            throw new MapInvalidException('Index name is invalid');
        }

        return $physicalName;
    }
}
