<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests\Fixture;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Левая вершина цикла FK.
 */
final class CycleLeftTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'st_setup_cycle_left';
    }

    /**
     * Ссылается на правую вершину.
     *
     * @return array Список полей.
     */
    protected function defineFields(): array
    {
        return [
            new IdField(),
            new ReferenceField(
                'right_id',
                FieldSettings::fromOptions(['required' => true]),
                CycleRightTable::class,
            ),
        ];
    }
}
