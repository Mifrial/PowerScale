<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Field\TextField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Запись летописи одной игры. Шапка хроники отдельно не хранится.
 */
final class GameChronicleEntryTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'game_chronicle_entry';
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
                'game_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                GameTable::class,
            ),
            new StringField('title', FieldSettings::fromOptions(['required' => true]), 255),
            new TextField('content', FieldSettings::fromOptions(['required' => true])),
            new IntField(
                'offset_minutes',
                FieldSettings::fromOptions(['required' => true]),
                0,
                2147483647,
            ),
            ReferenceField::forTable(
                'created_by',
                FieldSettings::fromOptions(['required' => true]),
                'user',
            ),
            new DateTimeField('created_at', FieldSettings::fromOptions(['required' => true])),
            new DateTimeField('updated_at', FieldSettings::fromOptions(['required' => true])),
        ];
    }
}
