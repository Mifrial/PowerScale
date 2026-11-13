<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Table;

use Mifrial\Core\File\Field\FileReferenceField;
use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;
use Mifrial\Core\User\Field\UserReferenceField;

/**
 * Карта `asset_item`: общая или личная запись библиотеки поверх файла.
 */
final class AssetItemTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'asset_item';
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
                'collection_id',
                FieldSettings::fromOptions(['required' => true]),
                AssetCollectionTable::class,
            ),
            new FileReferenceField('file_id', FieldSettings::fromOptions(['required' => true])),
            new BoolField('is_shared', FieldSettings::fromOptions([
                'required' => true,
                'default' => false,
                'indexed' => true,
            ])),
            new UserReferenceField('owner_id', FieldSettings::fromOptions(['indexed' => true]), 'setNull'),
            new StringField('name', FieldSettings::fromOptions(['required' => true]), 120),
            new BoolField('is_archived', FieldSettings::fromOptions([
                'required' => true,
                'default' => false,
                'indexed' => true,
            ])),
            new DateTimeField('created_at', FieldSettings::fromOptions([
                'required' => true,
                'default' => DateTimeNow::instance(),
            ])),
        ];
    }
}
