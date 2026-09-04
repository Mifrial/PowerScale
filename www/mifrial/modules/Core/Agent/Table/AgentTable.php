<?php

declare(strict_types=1);

namespace Mifrial\Core\Agent\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Расписание агентов CLI.
 */
final class AgentTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'agent';
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
            new StringField('code', FieldSettings::fromOptions(['required' => true, 'unique' => true])),
            new IntField('interval_sec', FieldSettings::fromOptions(['required' => true]), 1),
            new DateTimeField('last_run_at', FieldSettings::fromOptions([])),
            new BoolField('active', FieldSettings::fromOptions(['required' => true, 'default' => true])),
        ];
    }
}
