<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Query;

use Mifrial\Core\SmartTable\Dto\FieldPath;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Field\BaseField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Разворачивает путь по картам reference→id.
 */
final class FieldPathWalker
{
    /**
     * Создаёт walker.
     *
     * @param CatalogDefinitionLookup $catalogLookup Карты словаря.
     *
     * @return void
     */
    public function __construct(
        private readonly CatalogDefinitionLookup $catalogLookup = new CatalogDefinitionLookup(),
    ) {
    }

    /**
     * Разворачивает ключ относительно корневой карты.
     *
     * @param SmartTableDefinition $rootTable Стартовая карта.
     * @param string $pathKey Ключ с точками.
     *
     * @return ResolvedFieldPath Цепочка.
     *
     * @throws MapInvalidException Если hop или лист недопустимы.
     */
    public function resolve(SmartTableDefinition $rootTable, string $pathKey): ResolvedFieldPath
    {
        $fieldPath = FieldPath::parse($pathKey);
        $segments = $fieldPath->segments();
        $hopFields = [];
        $hopTables = [];
        $currentTable = $rootTable;
        $lastIndex = count($segments) - 1;
        for ($segmentIndex = 0; $segmentIndex < $lastIndex; $segmentIndex++) {
            $hopField = $this->requireField($currentTable, $segments[$segmentIndex]);
            if (!$hopField instanceof ReferenceField) {
                throw new MapInvalidException('Field path hop must be a reference');
            }

            $currentTable = $this->targetTable($hopField);
            $hopFields[] = $hopField;
            $hopTables[] = $currentTable;
        }

        $leafField = $this->requireField($currentTable, $segments[$lastIndex]);

        return new ResolvedFieldPath(
            $fieldPath->key(),
            $rootTable,
            $hopFields,
            $hopTables,
            $leafField,
            $currentTable,
        );
    }

    /**
     * Берёт поле карты.
     *
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param string $fieldName Имя.
     *
     * @return BaseField Поле.
     *
     * @throws MapInvalidException Если поля нет.
     */
    private function requireField(SmartTableDefinition $tableDefinition, string $fieldName): BaseField
    {
        $fieldMap = $tableDefinition->getMap();
        if (!isset($fieldMap[$fieldName])) {
            throw new MapInvalidException('Unknown field name');
        }

        return $fieldMap[$fieldName];
    }

    /**
     * Карта цели reference.
     *
     * @param ReferenceField $referenceField Hop.
     *
     * @return SmartTableDefinition Цель.
     *
     * @throws MapInvalidException Если карту нельзя открыть.
     */
    private function targetTable(ReferenceField $referenceField): SmartTableDefinition
    {
        $definitionClass = $referenceField->targetDefinitionClass();
        if ($definitionClass !== null) {
            return new $definitionClass();
        }

        return $this->catalogLookup->definitionByName($referenceField->targetTableName());
    }
}
