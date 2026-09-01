<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\StringField;

/**
 * Таблица записей словаря (PHP-класс): имя runtime-таблицы.
 */
final class MetaTableDefinition extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя meta-таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'st_meta_table';
    }

    /**
     * Перечисляет поля словаря таблиц.
     *
     * @return array<int, IdField|StringField> Поля.
     */
    protected function defineFields(): array
    {
        return [
            new IdField(),
            new StringField('name', FieldSettings::fromOptions([
                'required' => true,
                'unique' => true,
            ])),
            new StringField('label', FieldSettings::fromOptions()),
        ];
    }
}
