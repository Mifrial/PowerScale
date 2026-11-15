<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Table;

// phpcs:disable MifrialCodingStandard.Metrics.MethodQuality.MethodTooLong -- перечень полей карты.

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\HtmlField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\LinkSetField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Field\TextField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;
use Mifrial\Core\User\Field\UserLinkSetField;
use Mifrial\Core\User\Field\UserReferenceField;
use Mifrial\Roleplay\Keyword\Table\KeywordTable;

/**
 * Карта `lore_article`: статья кодекса.
 */
final class LoreArticleTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'lore_article';
    }

    /**
     * Перечисляет поля определения.
     *
     * @return array Список полей.
     */
    protected function defineFields(): array
    {
        $required = FieldSettings::fromOptions(['required' => true]);
        $now = FieldSettings::fromOptions(['required' => true, 'default' => DateTimeNow::instance()]);

        return [
            new IdField(),
            new ReferenceField(
                'world_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                LoreWorldTable::class,
            ),
            new StringField('code', $required, 64),
            new StringField('title', $required, 255),
            new TextField('summary', $required),
            new HtmlField('body_html', $required),
            new TextField('body_text', $required),
            new StringField('section_code', FieldSettings::fromOptions(['indexed' => true]), 64),
            new IntField('sort_order', FieldSettings::fromOptions(['required' => true, 'default' => 0]), 0, null),
            new LinkSetField('keywords', FieldSettings::fromOptions(['default' => []]), KeywordTable::class),
            new StringField('status', FieldSettings::fromOptions(['required' => true, 'indexed' => true]), 16),
            new StringField('visibility', FieldSettings::fromOptions(['required' => true, 'indexed' => true]), 16),
            new UserLinkSetField('readers', FieldSettings::fromOptions(['default' => []])),
            new IntField('version', FieldSettings::fromOptions(['required' => true, 'default' => 1]), 1, null),
            new BoolField('is_archived', FieldSettings::fromOptions([
                'required' => true,
                'default' => false,
                'indexed' => true,
            ])),
            new DateTimeField('created_at', $now),
            new DateTimeField('updated_at', FieldSettings::fromOptions([
                'required' => true,
                'default' => DateTimeNow::instance(),
                'indexed' => true,
            ])),
            new UserReferenceField('created_by', $required),
            new UserReferenceField('updated_by', FieldSettings::fromOptions([]), 'setNull'),
        ];
    }

    /**
     * Код статьи уникален внутри мира.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['world_id', 'code'],
        ];
    }
}
