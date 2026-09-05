<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\JsonField;
use Mifrial\Core\SmartTable\Field\LinkSetField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Field\TextField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;
use Mifrial\Roleplay\Keyword\Table\KeywordTable;
use Mifrial\Roleplay\Mechanic\Table\MechanicTable;

/**
 * Экземпляр правила `rule_version`.
 */
final class RuleVersionTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'rule_version';
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
                'entity_id',
                FieldSettings::fromOptions(['required' => true]),
                RuleTable::class,
            ),
            new DateTimeField(
                'created_at',
                FieldSettings::fromOptions(['required' => true, 'default' => DateTimeNow::instance()]),
            ),
            new BoolField(
                'active',
                FieldSettings::fromOptions(['required' => true, 'default' => true]),
            ),
            ...$this->bodyFields(),
        ];
    }

    /**
     * Поля тела снимка без конверта часов.
     *
     * @return array Список полей.
     */
    private function bodyFields(): array
    {
        return [
            new StringField('type', FieldSettings::fromOptions(['required' => true]), 64),
            new StringField('name', FieldSettings::fromOptions(['required' => true])),
            new TextField('description', FieldSettings::fromOptions(['required' => true])),
            new JsonField('spec', FieldSettings::fromOptions(['required' => true])),
            new ReferenceField(
                'mechanic_id',
                FieldSettings::fromOptions([]),
                MechanicTable::class,
            ),
            new LinkSetField(
                'keywords',
                FieldSettings::fromOptions([]),
                KeywordTable::class,
            ),
            new JsonField('mechanic_payload', FieldSettings::fromOptions(['required' => true])),
            new StringField(
                'content_status',
                FieldSettings::fromOptions(['required' => true, 'default' => 'needs_work']),
                32,
            ),
        ];
    }
}
