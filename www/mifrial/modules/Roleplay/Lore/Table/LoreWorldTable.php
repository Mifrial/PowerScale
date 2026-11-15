<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Field\TextField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;
use Mifrial\Core\User\Field\UserReferenceField;

/**
 * Карта `lore_world`: мир кодекса.
 */
final class LoreWorldTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'lore_world';
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
            new UserReferenceField(
                'owner_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
            ),
            new StringField('code', FieldSettings::fromOptions(['required' => true, 'unique' => true]), 64),
            new StringField('name', $required, 255),
            new TextField('description', $required),
            new StringField('visibility', FieldSettings::fromOptions(['required' => true]), 16),
            new StringField('space_code', $required, 64),
            new IntField('version', FieldSettings::fromOptions(['required' => true, 'default' => 1]), 1, null),
            new BoolField('is_archived', FieldSettings::fromOptions([
                'required' => true,
                'default' => false,
                'indexed' => true,
            ])),
            new UserReferenceField('updated_by', FieldSettings::fromOptions([]), 'setNull'),
            new DateTimeField('created_at', $now),
            new DateTimeField('updated_at', $now),
        ];
    }
}
