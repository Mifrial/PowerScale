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
 * Наложенный длительный эффект боя. Не лист и не поддержание.
 */
final class GameActiveEffectTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'game_active_effect';
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
            new StringField('source_type', FieldSettings::fromOptions(['required' => true]), 32),
            new StringField('source_rule_code', FieldSettings::fromOptions(['required' => true]), 255),
            new StringField('applier_kind', FieldSettings::fromOptions([]), 16),
            new IntField('applier_id', FieldSettings::fromOptions([]), 1, null),
            new StringField('target_kind', FieldSettings::fromOptions(['required' => true, 'indexed' => true]), 16),
            new IntField('target_id', FieldSettings::fromOptions(['required' => true, 'indexed' => true]), 1, null),
            new StringField('duration_type', FieldSettings::fromOptions(['required' => true]), 32),
            new StringField('duration_unit', FieldSettings::fromOptions([]), 16),
            new IntField('turns_total', FieldSettings::fromOptions([]), 0, null),
            new IntField('turns_left', FieldSettings::fromOptions([]), 0, null),
            new JsonField('strength', FieldSettings::fromOptions([])),
            new JsonField('context', FieldSettings::fromOptions([])),
            new IntField('started_round', FieldSettings::fromOptions(['required' => true, 'default' => 0]), 0, null),
            new StringField('started_participant', FieldSettings::fromOptions(['required' => true]), 64),
            new IntField('state_version', FieldSettings::fromOptions(['required' => true, 'default' => 1]), 1, null),
        ];
    }
}
