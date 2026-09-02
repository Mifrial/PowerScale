<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests\Fixture;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Child с onDelete none: ребра в граф нет.
 */
final class NoneChildTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'st_setup_none_child';
    }

    /**
     * Ссылается на parent без физического FK.
     *
     * @return array Список полей.
     */
    protected function defineFields(): array
    {
        return [
            new IdField(),
            new ReferenceField(
                'parent_id',
                FieldSettings::fromOptions(),
                NoneParentTable::class,
                'none',
            ),
        ];
    }
}
