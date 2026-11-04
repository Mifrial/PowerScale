<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;
use Mifrial\Core\User\Field\UserReferenceField;

/**
 * Карта таблицы файла `file`.
 */
final class FileTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'file';
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
            new StringField('public_id', FieldSettings::fromOptions(['required' => true, 'unique' => true]), 32),
            new UserReferenceField('owner_id', FieldSettings::fromOptions(['required' => true, 'indexed' => true])),
            new StringField('original_name', FieldSettings::fromOptions(['required' => true])),
            new StringField('mime', FieldSettings::fromOptions(['required' => true]), 127),
            new IntField('size', FieldSettings::fromOptions(['required' => true]), 0, 33554432),
            new StringField('sha256', FieldSettings::fromOptions(['required' => true]), 64),
            new StringField('storage_path', FieldSettings::fromOptions(['required' => true]), 255),
            new IntField('width', FieldSettings::fromOptions([]), 1, 20000),
            new IntField('height', FieldSettings::fromOptions([]), 1, 20000),
            new BoolField('is_archived', FieldSettings::fromOptions([
                'required' => true,
                'default' => false,
                'indexed' => true,
            ])),
            new DateTimeField('archived_at', FieldSettings::fromOptions([])),
            new DateTimeField('created_at', FieldSettings::fromOptions([
                'required' => true,
                'default' => DateTimeNow::instance(),
            ])),
        ];
    }
}
