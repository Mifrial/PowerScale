<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Заголовок действия боя: карточка, актор и открытость. Удары — в game_action_strike.
 */
final class GameActionTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'game_action';
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
            new StringField('kind', FieldSettings::fromOptions(['required' => true]), 32),
            new IntField('battle_id', FieldSettings::fromOptions(['required' => true]), 1, null),
            new ReferenceField(
                'session_id',
                FieldSettings::fromOptions(['required' => true]),
                GameSessionTable::class,
            ),
            new StringField('attacker_kind', FieldSettings::fromOptions(['required' => true]), 32),
            new IntField('attacker_id', FieldSettings::fromOptions(['required' => true]), 1, null),
            new StringField('action_rule_code', FieldSettings::fromOptions(['required' => true]), 255),
            new BoolField('open', FieldSettings::fromOptions(['required' => true])),
        ];
    }
}
