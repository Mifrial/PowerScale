<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests\Fixture;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Строки с group_id для COUNT/MAX/SUM.
 */
final class AggregateItemTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'st_agg_item';
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
            new IntField('group_id', FieldSettings::fromOptions(['required' => true])),
            new IntField('amount', FieldSettings::fromOptions(['required' => true])),
            new StringField('title', FieldSettings::fromOptions(['required' => true])),
            new StringField('note', FieldSettings::fromOptions()),
            new DateTimeField('created', FieldSettings::fromOptions()),
        ];
    }
}
