<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Table;

use Mifrial\Core\SmartTable\Field\BaseField;
use Mifrial\Core\SmartTable\Field\IdField;

/**
 * Карта runtime-таблицы из словаря: имя и поля без SQL.
 */
final class RuntimeDefinition extends SmartTableDefinition
{
    /**
     * Собирает определение из уже собранных полей спеки.
     *
     * @param string $tableName Физическое имя.
     * @param array<int, BaseField> $fields Поля без IdField.
     *
     * @return void
     */
    public function __construct(
        private readonly string $tableName,
        private readonly array $fields,
    ) {
    }

    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return $this->tableName;
    }

    /**
     * Перечисляет системный id и поля спеки.
     *
     * @return array<int, BaseField> Список полей.
     */
    protected function defineFields(): array
    {
        return array_merge([new IdField()], $this->fields);
    }
}
