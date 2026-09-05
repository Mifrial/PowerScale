<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Размещение правила в секции `rulespace_catalog_item`.
 */
final class RuleSpaceCatalogItemTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'rulespace_catalog_item';
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
                'section_id',
                FieldSettings::fromOptions(['required' => true]),
                RuleSpaceCatalogSectionTable::class,
            ),
            new StringField('rule_code', FieldSettings::fromOptions(['required' => true])),
            new IntField('sort_order', FieldSettings::fromOptions(['required' => true])),
        ];
    }

    /**
     * Правило не дублируется в одной папке.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['section_id', 'rule_code'],
        ];
    }
}
