<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Field;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\User\Table\UserTable;

/**
 * Ссылка на учётку: reference с целью `UserTable`.
 */
final class UserReferenceField extends ReferenceField
{
    /**
     * Создаёт поле-ссылку на учётку.
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
        parent::__construct($fieldName, $fieldSettings, UserTable::class, $onDelete);
    }
}
