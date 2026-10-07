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
 * Тело проверки. process_id без ссылки: строка process переживает сессию.
 */
final class GameCheckTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'game_check';
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
            new IntField('process_id', FieldSettings::fromOptions(['required' => true]), 1, null),
            new ReferenceField(
                'session_id',
                FieldSettings::fromOptions(['required' => true]),
                GameSessionTable::class,
            ),
            new StringField('mode', FieldSettings::fromOptions(['required' => true]), 16),
            new StringField('rule_code', FieldSettings::fromOptions(['required' => true]), 255),
            new StringField('target_type', FieldSettings::fromOptions([]), 16),
            new IntField('target_id', FieldSettings::fromOptions([]), 1, null),
            new StringField('offer', FieldSettings::fromOptions(['required' => true]), 16),
            new IntField('asked_base', FieldSettings::fromOptions([]), null, null),
            new IntField('asked_size', FieldSettings::fromOptions([]), null, null),
            new IntField('difficulty_base', FieldSettings::fromOptions([]), null, null),
            new IntField('difficulty_size', FieldSettings::fromOptions([]), null, null),
            new IntField('success_base', FieldSettings::fromOptions([]), null, null),
            new IntField('success_size', FieldSettings::fromOptions([]), null, null),
            new BoolField('passed', FieldSettings::fromOptions([])),
            new IntField('rating', FieldSettings::fromOptions([]), null, null),
        ];
    }
}
