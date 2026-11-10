<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\JsonField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;

/**
 * Карта `scene_enclosure`: вложение одного поля в другое.
 */
final class SceneEnclosureTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'scene_enclosure';
    }

    /**
     * Перечисляет поля определения.
     *
     * @return array Список полей.
     */
    protected function defineFields(): array
    {
        $now = FieldSettings::fromOptions(['required' => true, 'default' => DateTimeNow::instance()]);

        return [
            new IdField(),
            new ReferenceField(
                'parent_board_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                SceneBoardTable::class,
                'cascade',
            ),
            new ReferenceField(
                'child_board_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true, 'unique' => true]),
                SceneBoardTable::class,
                'cascade',
            ),
            new JsonField('footprint', FieldSettings::fromOptions(['required' => true])),
            new IntField('sort_order', FieldSettings::fromOptions(['required' => true, 'default' => 0]), 0, 1000000),
            new DateTimeField('created_at', $now),
        ];
    }
}
