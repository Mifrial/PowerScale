<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\JsonField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Field\TextField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;

/**
 * Журнал технических записей ядра.
 */
final class LogTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'log';
    }

    /**
     * Перечисляет поля определения.
     *
     * @return array Список полей.
     */
    protected function defineFields(): array
    {
        return [
            IdField::big(),
            new DateTimeField(
                'created_at',
                FieldSettings::fromOptions([
                    'required' => true,
                    'default' => DateTimeNow::instance(),
                ]),
            ),
            new StringField('level', FieldSettings::fromOptions(['required' => true])),
            new TextField('message', FieldSettings::fromOptions(['required' => true])),
            new StringField('source', FieldSettings::fromOptions([])),
            new IntField('user_id', FieldSettings::fromOptions([])),
            new StringField('exception_class', FieldSettings::fromOptions([])),
            new StringField('error_code', FieldSettings::fromOptions([]), 64),
            new JsonField('context', FieldSettings::fromOptions([])),
        ];
    }
}
