<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Process сессии или одного боя. Не лист и не история CharacterVersion.
 */
final class GameProcessTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'game_process';
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
            new IntField('session_id', FieldSettings::fromOptions(['required' => true]), 1, null),
            new IntField('battle_id', FieldSettings::fromOptions(['required' => false]), 1, null),
            new StringField('participant_type', FieldSettings::fromOptions(['required' => true]), 16),
            new IntField('participant_id', FieldSettings::fromOptions(['required' => true]), 1, null),
            new StringField('status', FieldSettings::fromOptions(['required' => true]), 16),
        ];
    }
}
