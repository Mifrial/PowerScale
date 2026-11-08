<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Field\TextField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;
use Mifrial\Core\User\Field\UserReferenceField;

/**
 * Карта личной заметки `note`.
 */
final class NoteTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'note';
    }

    /**
     * Перечисляет поля определения.
     *
     * @return array Список полей.
     */
    protected function defineFields(): array
    {
        $now = FieldSettings::fromOptions([
            'required' => true,
            'default' => DateTimeNow::instance(),
        ]);

        return [
            IdField::big(),
            new StringField('host_type', FieldSettings::fromOptions(['required' => true, 'indexed' => true]), 32),
            new IntField('host_id', FieldSettings::fromOptions(['required' => true, 'indexed' => true])),
            new UserReferenceField(
                'author_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                'cascade',
            ),
            new StringField('title', FieldSettings::fromOptions(['required' => true]), 255),
            new TextField('body_html', FieldSettings::fromOptions(['required' => true, 'default' => ''])),
            new TextField('body_text', FieldSettings::fromOptions(['required' => true, 'default' => ''])),
            new BoolField(
                'pinned',
                FieldSettings::fromOptions(['required' => true, 'default' => false, 'indexed' => true]),
            ),
            new IntField('sort_order', FieldSettings::fromOptions(['required' => true, 'default' => 0]), 0),
            new BoolField('active', FieldSettings::fromOptions(['required' => true, 'default' => true])),
            new DateTimeField('created_at', $now),
            new DateTimeField('updated_at', $now),
        ];
    }
}
