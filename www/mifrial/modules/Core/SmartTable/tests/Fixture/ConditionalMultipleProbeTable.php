<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests\Fixture;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Таблица с integer CAS и multiple sidecar для конкурентных тестов.
 */
final class ConditionalMultipleProbeTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'st_conditional_mfv_probe';
    }

    /**
     * Перечисляет поля определения.
     *
     * @return array Список.
     */
    protected function defineFields(): array
    {
        return [
            new IdField(),
            new StringField('title', FieldSettings::fromOptions(['required' => true])),
            new IntField('version', FieldSettings::fromOptions(), 0, null),
            new StringField('tags', FieldSettings::fromOptions(['multiple' => true])),
        ];
    }
}
