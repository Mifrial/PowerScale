<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Tests\Fixture;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;

/**
 * Ревизия фикстуры vt_note.
 */
final class NoteRevisionTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'vt_note_revision';
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
                'space_id',
                FieldSettings::fromOptions(['required' => true]),
                NoteSpaceTable::class,
            ),
            new IntField(
                'revision',
                FieldSettings::fromOptions(['required' => true]),
                1,
                null,
            ),
            new DateTimeField(
                'published_at',
                FieldSettings::fromOptions(['required' => true, 'default' => DateTimeNow::instance()]),
            ),
        ];
    }

    /**
     * Номер уникален внутри пространства.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['space_id', 'revision'],
        ];
    }
}
