<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests\Fixture;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Unique на string длиннее 255 — отказ карты.
 */
final class LongUniqueStringTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'st_idx_bad_long';
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
            new StringField('title', FieldSettings::fromOptions(['unique' => true]), 256),
        ];
    }
}
