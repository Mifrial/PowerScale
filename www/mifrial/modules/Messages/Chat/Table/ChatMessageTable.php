<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\JsonField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Field\TextField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;

/**
 * Карта сообщения `chat_message`.
 */
final class ChatMessageTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'chat_message';
    }

    /**
     * Перечисляет поля определения.
     *
     * @return array Список полей.
     */
    protected function defineFields(): array
    {
        $indexedNow = FieldSettings::fromOptions([
            'required' => true,
            'indexed' => true,
            'default' => DateTimeNow::instance(),
        ]);

        return array_merge($this->bodyFields(), [
            new DateTimeField('created_at', $indexedNow),
            new DateTimeField('updated_at', $indexedNow),
        ]);
    }

    /**
     * Поля тела сообщения без дат.
     *
     * @return array Список полей.
     */
    private function bodyFields(): array
    {
        return [
            IdField::big(),
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
            new TextField('content', FieldSettings::fromOptions(['required' => true])),
            new JsonField('attachments', FieldSettings::fromOptions(['required' => true, 'default' => []])),
            new StringField(
                'audience',
                FieldSettings::fromOptions(['required' => true, 'default' => 'all']),
                16,
            ),
            new IntField(
                'audience_user_ids',
                FieldSettings::fromOptions(['multiple' => true, 'default' => []]),
            ),
        ];
    }
}
