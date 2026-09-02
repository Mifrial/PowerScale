<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests\Fixture;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Цель child.parent_id: скаляр, multiple, hop дальше.
 */
final class PathParentTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'st_path_parent';
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
            new StringField('title', FieldSettings::fromOptions(['required' => true])),
            new BoolField('active', FieldSettings::fromOptions(['required' => true, 'default' => true])),
            new StringField('note', FieldSettings::fromOptions()),
            new ReferenceField('owner_id', FieldSettings::fromOptions(), PathOwnerTable::class),
            new StringField('tags', FieldSettings::fromOptions(['multiple' => true])),
        ];
    }
}
