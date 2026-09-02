<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Query;

use Mifrial\Core\SmartTable\Exception\Schema\SchemaMismatchException;
use Mifrial\Core\SmartTable\Field\BaseField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Догрузка и гидрация полей пути.
 */
final class PathListHydrator
{
    /**
     * Создаёт гидратор путей.
     *
     * @param FieldPathWalker $fieldPathWalker Пути.
     * @param MfvRows $mfvRows Multiple целей.
     *
     * @return void
     */
    public function __construct(
        private readonly FieldPathWalker $fieldPathWalker,
        private readonly MfvRows $mfvRows,
    ) {
    }

    /**
     * Догружает multiple-листы целей в сырые строки.
     *
     * @param array<int, array<string, mixed>> $rowMaps Строки.
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param array<int, string> $hydrateNames Поля ответа.
     *
     * @return void
     */
    public function attachMultiple(array &$rowMaps, SmartTableDefinition $tableDefinition, array $hydrateNames): void
    {
        $mfvIndex = 0;
        foreach ($hydrateNames as $fieldName) {
            if (!str_contains($fieldName, '.')) {
                continue;
            }

            $resolvedPath = $this->fieldPathWalker->resolve($tableDefinition, $fieldName);
            if (!$resolvedPath->leafField()->settings()->multiple()) {
                continue;
            }

            $this->fillMultiple($rowMaps, $resolvedPath, $fieldName, $mfvIndex);
            $mfvIndex++;
        }
    }

    /**
     * Гидратирует ключи пути в PHP-строку.
     *
     * @param array<string, mixed> $rowMap Сырая строка.
     * @param array<string, mixed> $hydratedRow Уже свои поля.
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param array<int, string> $hydrateNames Поля ответа.
     *
     * @return array<string, mixed> Строка с путями.
     */
    public function hydratePaths(
        array $rowMap,
        array $hydratedRow,
        SmartTableDefinition $tableDefinition,
        array $hydrateNames,
    ): array {
        foreach ($hydrateNames as $fieldName) {
            if (!str_contains($fieldName, '.')) {
                continue;
            }

            $leafField = $this->fieldPathWalker->resolve($tableDefinition, $fieldName)->leafField();
            $rawValue = $rowMap[$fieldName] ?? null;
            $hydratedRow[$fieldName] = $leafField->settings()->multiple()
                ? $this->hydrateMultiple($leafField, is_array($rawValue) ? $rawValue : [])
                : $leafField->hydrate($rawValue);
        }

        return $hydratedRow;
    }

    /**
     * Пишет list mfv одной колонки пути.
     *
     * @param array<int, array<string, mixed>> $rowMaps Строки.
     * @param ResolvedFieldPath $resolvedPath Путь.
     * @param string $fieldName Ключ.
     * @param int $mfvIndex Индекс алиаса.
     *
     * @return void
     */
    private function fillMultiple(
        array &$rowMaps,
        ResolvedFieldPath $resolvedPath,
        string $fieldName,
        int $mfvIndex,
    ): void {
        $aliasName = '__st_m' . $mfvIndex;
        $ownerIds = [];
        foreach ($rowMaps as $rowMap) {
            if (isset($rowMap[$aliasName]) && $rowMap[$aliasName] !== null) {
                $ownerIds[] = (int) $rowMap[$aliasName];
            }
        }

        $groupedValues = $this->mfvRows->loadByOwners(
            $resolvedPath->leafTable(),
            $resolvedPath->leafField(),
            $ownerIds,
        );
        foreach ($rowMaps as $rowIndex => $rowMap) {
            $ownerId = isset($rowMap[$aliasName]) && $rowMap[$aliasName] !== null
                ? (int) $rowMap[$aliasName]
                : 0;
            $rowMaps[$rowIndex][$fieldName] = $groupedValues[$ownerId] ?? [];
        }
    }

    /**
     * Гидратирует list mfv.
     *
     * @param BaseField $leafField Лист.
     * @param mixed $rawList Сырой list.
     *
     * @return array<int, mixed> PHP-значения.
     *
     * @throws SchemaMismatchException Если это не list.
     */
    private function hydrateMultiple(BaseField $leafField, mixed $rawList): array
    {
        if (!is_array($rawList) || !array_is_list($rawList)) {
            throw new SchemaMismatchException('Multiple column must be a list of values');
        }

        $hydratedItems = [];
        foreach ($rawList as $rawValue) {
            $hydratedItems[] = $leafField->hydrate($rawValue);
        }

        return $hydratedItems;
    }
}
