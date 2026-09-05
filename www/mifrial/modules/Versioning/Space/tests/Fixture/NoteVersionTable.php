<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Tests\Fixture;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Field\TextField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;

/**
 * Экземпляр фикстуры vt_note.
 */
final class NoteVersionTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'vt_note_version';
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
                NoteIdentityTable::class,
            ),
            new DateTimeField(
                'created_at',
                FieldSettings::fromOptions(['required' => true, 'default' => DateTimeNow::instance()]),
            ),
            new BoolField(
                'active',
                FieldSettings::fromOptions(['required' => true, 'default' => true]),
            ),
            new StringField('title', FieldSettings::fromOptions(['required' => true])),
            new TextField('body', FieldSettings::fromOptions(['required' => true])),
        ];
    }
}
