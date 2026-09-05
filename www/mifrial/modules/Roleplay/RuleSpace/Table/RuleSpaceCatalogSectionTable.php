<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Rule\Table\RuleSpaceTable;

/**
 * Узел снимка каталога `rulespace_catalog_section`.
 */
final class RuleSpaceCatalogSectionTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'rulespace_catalog_section';
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
                'section_version',
                FieldSettings::fromOptions(['required' => true]),
                1,
            ),
            new StringField('code', FieldSettings::fromOptions(['required' => true])),
            new StringField('name', FieldSettings::fromOptions(['required' => true])),
            new StringField('parent_code', FieldSettings::fromOptions([])),
            new IntField('sort_order', FieldSettings::fromOptions(['required' => true])),
            new StringField('catalog_root_for', FieldSettings::fromOptions([])),
        ];
    }

    /**
     * Код уникален в снимке мира.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['space_id', 'section_version', 'code'],
        ];
    }
}
