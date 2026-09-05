<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Состав ревизии `rule_revision_item`.
 */
final class RuleRevisionItemTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'rule_revision_item';
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
                'revision_id',
                FieldSettings::fromOptions(['required' => true]),
                RuleRevisionTable::class,
            ),
            new ReferenceField(
                'version_id',
                FieldSettings::fromOptions(['required' => true]),
                RuleVersionTable::class,
            ),
        ];
    }

    /**
     * Версия один раз в ревизии.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['revision_id', 'version_id'],
        ];
    }
}
