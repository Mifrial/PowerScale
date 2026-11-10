<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Table;

use Mifrial\Core\File\Field\FileReferenceField;
use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\JsonField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;

/**
 * Карта `scene_element`: опора, препятствие, проём, террейн, декор или маркер.
 */
final class SceneElementTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'scene_element';
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
                'board_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                SceneBoardTable::class,
                'cascade',
            ),
            new StringField('kind', FieldSettings::fromOptions(['required' => true, 'indexed' => true]), 32),
            new JsonField('shape', FieldSettings::fromOptions(['required' => true])),
            new JsonField('props', FieldSettings::fromOptions(['required' => true, 'default' => []])),
            new IntField('sort_order', FieldSettings::fromOptions(['required' => true, 'default' => 0]), 0, 1000000),
            new IntField('target_board_id', FieldSettings::fromOptions(['indexed' => true]), 1, null),
            new FileReferenceField('asset_file_id', FieldSettings::fromOptions([]), 'setNull'),
            new DateTimeField('created_at', $now),
        ];
    }
}
