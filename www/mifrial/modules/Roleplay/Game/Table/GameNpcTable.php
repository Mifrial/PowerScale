<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\JsonField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * NPC игры. Один лист, без строки character.
 */
final class GameNpcTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'game_npc';
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
                FieldSettings::fromOptions(['required' => true]),
                GameTable::class,
            ),
            new StringField('name', FieldSettings::fromOptions(['required' => true]), 255),
            new JsonField('version', FieldSettings::fromOptions(['required' => true])),
            new IntField(
                'actual_version',
                FieldSettings::fromOptions(['required' => true, 'default' => 1]),
                1,
                null,
            ),
            new JsonField('visibility', FieldSettings::fromOptions(['required' => true])),
        ];
    }
}
