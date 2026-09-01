<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\JsonField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;

/**
 * Таблица полей словаря одной runtime-таблицы (PHP-класс).
 */
final class MetaFieldDefinition extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя meta-полей.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'st_meta_field';
    }

    /**
     * Перечисляет поля словаря полей.
     *
     * @return array<int, IdField|ReferenceField|StringField|JsonField> Поля.
     */
    protected function defineFields(): array
    {
        return [
            new IdField(),
            new ReferenceField(
                'table_id',
                FieldSettings::fromOptions(['required' => true]),
                MetaTableDefinition::class,
            ),
            new StringField('name', FieldSettings::fromOptions(['required' => true])),
            new StringField('type', FieldSettings::fromOptions(['required' => true])),
            new JsonField('settings', FieldSettings::fromOptions(['default' => []])),
        ];
    }
}
