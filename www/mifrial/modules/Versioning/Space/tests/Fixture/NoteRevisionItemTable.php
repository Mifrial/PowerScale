<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Tests\Fixture;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Состав ревизии фикстуры vt_note.
 */
final class NoteRevisionItemTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'vt_note_revision_item';
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
                NoteRevisionTable::class,
            ),
            new ReferenceField(
                'version_id',
                FieldSettings::fromOptions(['required' => true]),
                NoteVersionTable::class,
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
