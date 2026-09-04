<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Field\TextField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Шаблон письма события.
 */
final class MailTemplateTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'mail_template';
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
                'event_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                MailEventTable::class,
                'restrict',
            ),
            new StringField('name', FieldSettings::fromOptions(['required' => true])),
            new StringField('email_from', FieldSettings::fromOptions(['required' => true])),
            new StringField('email_to', FieldSettings::fromOptions(['required' => true])),
            new StringField('subject', FieldSettings::fromOptions(['required' => true]), 1024),
            new TextField('body', FieldSettings::fromOptions(['required' => true])),
            new BoolField('active', FieldSettings::fromOptions(['required' => true, 'default' => true])),
        ];
    }
}
