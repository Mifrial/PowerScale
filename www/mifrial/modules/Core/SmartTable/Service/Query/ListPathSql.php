<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Query;

/**
 * Коррелированный скалярный подзапрос пути без JOIN в FROM.
 */
final class ListPathSql
{
    /**
     * SQL выражения листа (для SELECT/ORDER BY).
     *
     * @param ResolvedFieldPath $resolvedPath Путь.
     *
     * @return string Подзапрос в скобках.
     */
    public function scalarSql(ResolvedFieldPath $resolvedPath): string
    {
        return $this->nestedSelect($resolvedPath, $resolvedPath->leafField()->name());
    }

    /**
     * SQL id строки листа (для догрузки mfv).
     *
     * @param ResolvedFieldPath $resolvedPath Путь.
     *
     * @return string Подзапрос в скобках.
     */
    public function leafIdSql(ResolvedFieldPath $resolvedPath): string
    {
        $idName = $resolvedPath->hopFields()[count($resolvedPath->hopFields()) - 1]->targetIdField();

        return $this->nestedSelect($resolvedPath, $idName);
    }

    /**
     * Вкладывает SELECT колонки по hops.
     *
     * @param ResolvedFieldPath $resolvedPath Путь.
     * @param string $leafColumn Колонка на последней цели.
     *
     * @return string Подзапрос.
     */
    private function nestedSelect(ResolvedFieldPath $resolvedPath, string $leafColumn): string
    {
        $hopFields = $resolvedPath->hopFields();
        $expression = $this->qualify(
            $resolvedPath->rootTable()->getName(),
            $hopFields[0]->name(),
        );
        foreach ($hopFields as $hopIndex => $hopField) {
            $aliasName = 'st_p' . $hopIndex;
            $selectName = $this->selectName($resolvedPath, $hopIndex, $leafColumn);
            $targetName = $hopField->targetTableName();
            $idName = $hopField->targetIdField();
            $expression = '(select ' . $this->quote($selectName)
                . ' from ' . $this->quote($targetName)
                . ' as ' . $this->quote($aliasName)
                . ' where ' . $this->qualify($aliasName, $idName)
                . ' = ' . $expression . ')';
        }

        return $expression;
    }

    /**
     * Колонка SELECT на шаге hop.
     *
     * @param ResolvedFieldPath $resolvedPath Путь.
     * @param int $hopIndex Индекс hop.
     * @param string $leafColumn Колонка листа.
     *
     * @return string Имя колонки.
     */
    private function selectName(ResolvedFieldPath $resolvedPath, int $hopIndex, string $leafColumn): string
    {
        $hopFields = $resolvedPath->hopFields();
        if ($hopIndex === count($hopFields) - 1) {
            return $leafColumn;
        }

        return $hopFields[$hopIndex + 1]->name();
    }

    /**
     * Квалифицирует стол.поле.
     *
     * @param string $tableName Стол или алиас.
     * @param string $columnName Колонка.
     *
     * @return string SQL.
     */
    private function qualify(string $tableName, string $columnName): string
    {
        return $this->quote($tableName) . '.' . $this->quote($columnName);
    }

    /**
     * Берёт идентификатор в кавычки.
     *
     * @param string $identifier Имя.
     *
     * @return string `имя`.
     */
    private function quote(string $identifier): string
    {
        return '`' . $identifier . '`';
    }
}
