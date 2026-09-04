<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Field\TextField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Каталог типов почтовых событий.
 */
final class MailEventTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'mail_event';
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
            new StringField('code', FieldSettings::fromOptions(['required' => true, 'unique' => true])),
            new StringField('name', FieldSettings::fromOptions(['required' => true])),
            new TextField('description', FieldSettings::fromOptions([])),
        ];
    }
}
