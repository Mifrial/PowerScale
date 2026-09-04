<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;

/**
 * Карта таблицы группы `user_group`.
 */
final class UserGroupTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'user_group';
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
            new StringField('name', FieldSettings::fromOptions(['required' => true, 'unique' => true])),
            new BoolField('active', FieldSettings::fromOptions(['required' => true, 'default' => true])),
            new BoolField('bypass', FieldSettings::fromOptions(['required' => true, 'default' => false])),
            new BoolField(
                'assign_on_register',
                FieldSettings::fromOptions(['required' => true, 'default' => false]),
            ),
            new DateTimeField(
                'created_at',
                FieldSettings::fromOptions(['required' => true, 'default' => DateTimeNow::instance()]),
            ),
            new StringField('permissions', FieldSettings::fromOptions(['multiple' => true])),
        ];
    }
}
