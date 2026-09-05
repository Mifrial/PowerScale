<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests\Fixture;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\LinkSetField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Та же физика, что LinkSetOwnerTable, onDelete none.
 */
final class LinkSetNoneOwnerTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'st_linkset_owner';
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
            new LinkSetField('keywords', FieldSettings::fromOptions(), ParentRefTable::class, 'none'),
        ];
    }
}
