<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Table;

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
 * Очередь писем.
 */
final class MailJobTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'mail_job';
    }

    /**
     * Перечисляет поля определения.
     *
     * @return array Список полей.
     */
    protected function defineFields(): array
    {
        return [
            IdField::big(),
            new ReferenceField(
                'event_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                MailEventTable::class,
                'restrict',
            ),
            new JsonField('payload', FieldSettings::fromOptions(['required' => true])),
            new StringField('status', FieldSettings::fromOptions(['required' => true, 'indexed' => true])),
            new DateTimeField(
                'created_at',
                FieldSettings::fromOptions([
                    'required' => true,
                    'indexed' => true,
                    'default' => DateTimeNow::instance(),
                ]),
            ),
            new DateTimeField('sent_at', FieldSettings::fromOptions([])),
            new IntField('attempts', FieldSettings::fromOptions(['required' => true, 'default' => 0]), 0),
            new TextField('last_error', FieldSettings::fromOptions([])),
        ];
    }
}
