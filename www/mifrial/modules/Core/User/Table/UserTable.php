<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Field\TextField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;

/**
 * Карта таблицы учётки `user`.
 */
final class UserTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'user';
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
            new StringField('login', FieldSettings::fromOptions(['required' => true, 'unique' => true])),
            new StringField('email', FieldSettings::fromOptions(['unique' => true])),
            new StringField('name', FieldSettings::fromOptions(['required' => true])),
            new StringField('surname', FieldSettings::fromOptions([])),
            new StringField('nickname', FieldSettings::fromOptions([])),
            new BoolField('active', FieldSettings::fromOptions(['required' => true, 'default' => true])),
            new DateTimeField(
                'registered_at',
                FieldSettings::fromOptions(['required' => true, 'default' => DateTimeNow::instance()]),
            ),
            new DateTimeField('deactivated_until', FieldSettings::fromOptions([])),
            new TextField('deactivate_reason', FieldSettings::fromOptions([])),
        ];
    }
}
