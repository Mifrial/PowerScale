<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Field;

use Mifrial\Core\File\Table\FileTable;
use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\ReferenceField;

/**
 * Ссылка на файл: reference с целью `FileTable`.
 */
final class FileReferenceField extends ReferenceField
{
    /**
     * Создаёт поле-ссылку на файл.
     *
     * @param string $fieldName Имя поля.
     * @param FieldSettings $fieldSettings Настройки.
     * @param string $onDelete Режим restrict, setNull, none или cascade.
     *
     * @return void
     */
    public function __construct(
        string $fieldName,
        FieldSettings $fieldSettings,
        string $onDelete = 'restrict',
    ) {
        parent::__construct($fieldName, $fieldSettings, FileTable::class, $onDelete);
    }
}
