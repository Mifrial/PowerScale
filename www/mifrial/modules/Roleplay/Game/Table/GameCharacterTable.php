<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\JsonField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Field\TextField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Строка персонажа в игре. Не участник и не лист Character.
 */
final class GameCharacterTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'game_character';
    }

    /**
     * Перечисляет поля определения.
     *
     * @return array Список полей.
     */
    protected function defineFields(): array
    {
        return array_merge($this->linkFields(), $this->reviewFields(), $this->bonusFields());
    }

    /**
     * Игра, персонаж и живой слот.
     *
     * @return array Поля.
     */
    private function linkFields(): array
    {
        return [
            new IdField(),
            new ReferenceField(
                'game_id',
                FieldSettings::fromOptions(['required' => true]),
                GameTable::class,
            ),
            ReferenceField::forTable(
                'character_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                'character',
            ),
            ReferenceField::forTable(
                'character_owner_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                'user',
            ),
            new IntField('live_character_id', FieldSettings::fromOptions(['unique' => true])),
            new StringField('status', FieldSettings::fromOptions(['required' => true]), 32),
        ];
    }

    /**
     * Snapshot, revision и return.
     *
     * @return array Поля.
     */
    private function reviewFields(): array
    {
        return [
            new JsonField('approved_character_version', FieldSettings::fromOptions([])),
            new IntField(
                'membership_revision',
                FieldSettings::fromOptions(['required' => true, 'default' => 1]),
                1,
                null,
            ),
            new DateTimeField('returned_at', FieldSettings::fromOptions([])),
            new TextField('return_reason', FieldSettings::fromOptions([])),
            new IntField('discussion_chat_id', FieldSettings::fromOptions([]), 1, null),
            new IntField('return_message_id', FieldSettings::fromOptions([]), 1, null),
        ];
    }

    /**
     * Бонус и видимость секций.
     *
     * @return array Поля.
     */
    private function bonusFields(): array
    {
        return [
            new IntField('os_bonus', FieldSettings::fromOptions(['required' => true, 'default' => 0]), 0, null),
            new IntField('or_bonus', FieldSettings::fromOptions(['required' => true, 'default' => 0]), 0, null),
            new IntField('ol_bonus', FieldSettings::fromOptions(['required' => true, 'default' => 0]), 0, null),
            new JsonField(
                'section_visibility',
                FieldSettings::fromOptions(['required' => true, 'default' => []]),
            ),
        ];
    }
}
