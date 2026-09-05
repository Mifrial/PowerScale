<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Field\TextField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;
use Mifrial\Core\User\Table\UserTable;
use Mifrial\Roleplay\Rule\Table\RuleSpaceTable;

/**
 * Продуктовая мета мира `rulespace`.
 */
final class RuleSpaceMetaTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'rulespace';
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
                'space_id',
                FieldSettings::fromOptions(['required' => true, 'unique' => true]),
                RuleSpaceTable::class,
            ),
            new StringField('code', FieldSettings::fromOptions(['required' => true, 'unique' => true])),
            new StringField('name', FieldSettings::fromOptions(['required' => true])),
            new ReferenceField(
                'owner_id',
                FieldSettings::fromOptions(['required' => true]),
                UserTable::class,
            ),
            new TextField('description', FieldSettings::fromOptions(['required' => true])),
            new BoolField('active', FieldSettings::fromOptions(['required' => true, 'default' => true])),
            new DateTimeField(
                'created_at',
                FieldSettings::fromOptions(['required' => true, 'default' => DateTimeNow::instance()]),
            ),
        ];
    }
}
