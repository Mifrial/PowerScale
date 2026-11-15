<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Field\TextField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;

/**
 * Карта `lore_section`: раздел дерева кодекса.
 */
final class LoreSectionTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'lore_section';
    }

    /**
     * Перечисляет поля определения.
     *
     * @return array Список полей.
     */
    protected function defineFields(): array
    {
        $required = FieldSettings::fromOptions(['required' => true]);
        $now = FieldSettings::fromOptions(['required' => true, 'default' => DateTimeNow::instance()]);

        return [
            new IdField(),
            new ReferenceField(
                'world_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                LoreWorldTable::class,
            ),
            new StringField('code', $required, 64),
            new StringField('name', $required, 255),
            new TextField('description', $required),
            new StringField('parent_code', FieldSettings::fromOptions([]), 64),
            new IntField('sort_order', FieldSettings::fromOptions(['required' => true, 'default' => 0]), 0, null),
            new StringField('icon', $required, 64),
            new DateTimeField('created_at', $now),
            new DateTimeField('updated_at', $now),
        ];
    }

    /**
     * Код раздела уникален внутри мира.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['world_id', 'code'],
        ];
    }
}
