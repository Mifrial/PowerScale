<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;

/**
 * Карта членства `chat_member`.
 */
final class ChatMemberTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'chat_member';
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
                'chat_id',
                FieldSettings::fromOptions(['required' => true]),
                ChatTable::class,
            ),
            ReferenceField::forTable(
                'user_id',
                FieldSettings::fromOptions(['required' => true]),
                'user',
            ),
            new IntField('last_read_message_id', FieldSettings::fromOptions([]), null, null, true),
            new DateTimeField(
                'joined_at',
                FieldSettings::fromOptions(['required' => true, 'default' => DateTimeNow::instance()]),
            ),
        ];
    }

    /**
     * Пара чат+учётка уникальна.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['chat_id', 'user_id'],
        ];
    }
}
