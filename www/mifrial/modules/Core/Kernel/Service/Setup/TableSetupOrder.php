<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service\Setup;

use Mifrial\Core\Kernel\Exception\Setup\SetupException;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use ReflectionClass;

/**
 * Топологический порядок карт по FK reference → физ. имя.
 */
final class TableSetupOrder
{
    /**
     * Инстанциирует карты и сортирует по графу FK.
     *
     * @param array<int, class-string<SmartTableDefinition>> $tableClasses Карты прогона.
     *
     * @return array<int, SmartTableDefinition> Определения в порядке DDL.
     *
     * @throws SetupException Если дубль имени, не no-arg или цикл.
     */
    public function order(array $tableClasses): array
    {
        $definitionsByName = $this->indexByPhysicalName($tableClasses);
        $adjacency = $this->adjacency($definitionsByName);
        $orderedNames = $this->kahnSort(array_keys($definitionsByName), $adjacency);
        $orderedDefinitions = [];
        foreach ($orderedNames as $tableName) {
            $orderedDefinitions[] = $definitionsByName[$tableName];
        }

        return $orderedDefinitions;
    }

    /**
     * Собирает определения по физическому имени.
     *
     * @param array<int, class-string<SmartTableDefinition>> $tableClasses Карты.
     *
     * @return array<string, SmartTableDefinition> Имя → определение.
     *
     * @throws SetupException Если класс недопустим или имя занято.
     */
    private function indexByPhysicalName(array $tableClasses): array
    {
        $definitionsByName = [];
        foreach ($tableClasses as $tableClass) {
            $definition = $this->instantiate($tableClass);
            $tableName = $definition->getName();
            if (isset($definitionsByName[$tableName])) {
                throw new SetupException(
                    'SETUP_DUPLICATE_TABLE',
                    'Duplicate physical table name: ' . $tableName,
                );
            }

            $definitionsByName[$tableName] = $definition;
        }

        return $definitionsByName;
    }

    /**
     * Создаёт definition без аргументов.
     *
     * @param string $tableClass Класс карты.
     *
     * @return SmartTableDefinition Определение.
     *
     * @throws SetupException Если класс нельзя создать без аргументов.
     */
    private function instantiate(string $tableClass): SmartTableDefinition
    {
        if (!is_a($tableClass, SmartTableDefinition::class, true)) {
            throw new SetupException(
                'SETUP_INVALID',
                'Setup table class must be a SmartTable definition: ' . $tableClass,
            );
        }

        $classReflection = new ReflectionClass($tableClass);
        $constructor = $classReflection->getConstructor();
        $requiredParameters = $constructor === null ? 0 : $constructor->getNumberOfRequiredParameters();
        if (!$classReflection->isInstantiable() || $requiredParameters > 0) {
            throw new SetupException(
                'SETUP_INVALID',
                'Setup table definition must be a no-arg class: ' . $tableClass,
            );
        }

        $definition = $classReflection->newInstance();
        if (!$definition instanceof SmartTableDefinition) {
            throw new SetupException(
                'SETUP_INVALID',
                'Setup table class must be a SmartTable definition: ' . $tableClass,
            );
        }

        return $definition;
    }

    /**
     * Строит список детей для каждой вершины (родитель раньше ребёнка).
     *
     * @param array<string, SmartTableDefinition> $definitionsByName Карты.
     *
     * @return array<string, array<int, string>> Родитель → дети.
     */
    private function adjacency(array $definitionsByName): array
    {
        $adjacency = [];
        foreach (array_keys($definitionsByName) as $tableName) {
            $adjacency[$tableName] = [];
        }

        foreach ($definitionsByName as $childName => $definition) {
            foreach ($this->parentNames($definition) as $parentName) {
                if ($parentName === $childName || !isset($definitionsByName[$parentName])) {
                    continue;
                }

                $adjacency[$parentName][] = $childName;
            }
        }

        return $adjacency;
    }

    /**
     * Собирает физ. имена целей FK (onDelete не none, не self).
     *
     * @param SmartTableDefinition $definition Карта.
     *
     * @return array<int, string> Имена родителей.
     */
    private function parentNames(SmartTableDefinition $definition): array
    {
        $parentNames = [];
        foreach ($definition->getMap() as $field) {
            if (!$field instanceof ReferenceField || $field->onDelete() === 'none') {
                continue;
            }

            $parentNames[] = $field->targetTableName();
        }

        return $parentNames;
    }

    /**
     * Сортирует имена алгоритмом Кана.
     *
     * @param array<int, string> $tableNames Вершины.
     * @param array<string, array<int, string>> $adjacency Родитель → дети.
     *
     * @return array<int, string> Порядок DDL.
     *
     * @throws SetupException Если в графе цикл.
     */
    private function kahnSort(array $tableNames, array $adjacency): array
    {
        $indegree = [];
        foreach ($tableNames as $tableName) {
            $indegree[$tableName] = 0;
        }

        foreach ($adjacency as $children) {
            foreach ($children as $childName) {
                $indegree[$childName]++;
            }
        }

        $readyNames = [];
        foreach ($indegree as $tableName => $pendingParents) {
            if ($pendingParents === 0) {
                $readyNames[] = $tableName;
            }
        }

        sort($readyNames);

        return $this->drainReady($readyNames, $adjacency, $indegree, $tableNames);
    }

    /**
     * Снимает вершины с нулевой входящей степенью.
     *
     * @param array<int, string> $readyNames Очередь.
     * @param array<string, array<int, string>> $adjacency Родитель → дети.
     * @param array<string, int> $indegree Входящие рёбра.
     * @param array<int, string> $tableNames Все вершины.
     *
     * @return array<int, string> Порядок.
     *
     * @throws SetupException Если остались вершины — цикл.
     */
    private function drainReady(
        array $readyNames,
        array $adjacency,
        array $indegree,
        array $tableNames,
    ): array {
        $orderedNames = [];
        while ($readyNames !== []) {
            $currentName = array_shift($readyNames);
            $orderedNames[] = $currentName;
            foreach ($adjacency[$currentName] as $childName) {
                $indegree[$childName]--;
                if ($indegree[$childName] === 0) {
                    $readyNames[] = $childName;
                    sort($readyNames);
                }
            }
        }

        if (count($orderedNames) !== count($tableNames)) {
            throw new SetupException(
                'SETUP_CYCLE',
                'Cyclic foreign keys in setup table graph',
            );
        }

        return $orderedNames;
    }
}
