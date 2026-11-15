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
 * Поддерживаемое заклинание боя. Не лист и не состояние персонажа.
 */
final class GameActiveSpellTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'game_active_spell';
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
            new ReferenceField(
                'session_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                GameSessionTable::class,
            ),
            new ReferenceField(
                'battle_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                GameBattleTable::class,
            ),
            new StringField('caster_kind', FieldSettings::fromOptions(['required' => true]), 16),
            new IntField('caster_id', FieldSettings::fromOptions(['required' => true]), 1, null),
            new StringField('spell_code', FieldSettings::fromOptions(['required' => true]), 255),
            new StringField('path_code', FieldSettings::fromOptions(['required' => true]), 255),
            new StringField('source_key', FieldSettings::fromOptions(['required' => true, 'indexed' => true]), 255),
            new StringField('duration_type', FieldSettings::fromOptions(['required' => true]), 32),
            new JsonField('used_power', FieldSettings::fromOptions(['required' => true])),
            new JsonField('sustain_power', FieldSettings::fromOptions(['required' => true])),
            new JsonField('parameter_values', FieldSettings::fromOptions([])),
            new JsonField('applied_upgrade_codes', FieldSettings::fromOptions([])),
            new IntField('started_round', FieldSettings::fromOptions(['required' => true, 'default' => 0]), 0, null),
            new StringField('started_participant', FieldSettings::fromOptions(['required' => true]), 64),
            new IntField('caster_sheet_version', FieldSettings::fromOptions(['required' => true]), 1, null),
            new IntField('state_version', FieldSettings::fromOptions(['required' => true, 'default' => 1]), 1, null),
        ];
    }
}
