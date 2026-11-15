<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Карта `lore_link`: извлечённая из тела статьи ссылка.
 */
final class LoreLinkTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'lore_link';
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
                'world_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                LoreWorldTable::class,
            ),
            new ReferenceField(
                'source_article_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                LoreArticleTable::class,
            ),
            new StringField('target_type', FieldSettings::fromOptions(['required' => true, 'indexed' => true]), 32),
            new StringField('target_code', FieldSettings::fromOptions(['required' => true, 'indexed' => true]), 255),
        ];
    }

    /**
     * Одна и та же цель не дублируется в статье.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['source_article_id', 'target_type', 'target_code'],
        ];
    }
}
