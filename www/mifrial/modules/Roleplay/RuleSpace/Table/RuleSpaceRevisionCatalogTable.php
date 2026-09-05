<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Rule\Table\RuleSpaceTable;

/**
 * Указатель ревизии на снимок `rulespace_revision_catalog`.
 */
final class RuleSpaceRevisionCatalogTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'rulespace_revision_catalog';
    }

    /**
     * Перечисляет поля определения.
     *
     * @return array Список полей.
     */
    protected function defineFields(): array
    {
        return [
            new IdField(),
            new ReferenceField(
                'space_id',
                FieldSettings::fromOptions(['required' => true]),
                RuleSpaceTable::class,
            ),
            new IntField(
                'revision',
                FieldSettings::fromOptions(['required' => true]),
                1,
            ),
            new IntField(
                'section_version',
                FieldSettings::fromOptions(['required' => true]),
                1,
            ),
        ];
    }

    /**
     * Одна строка на ревизию мира.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['space_id', 'revision'],
        ];
    }
}
