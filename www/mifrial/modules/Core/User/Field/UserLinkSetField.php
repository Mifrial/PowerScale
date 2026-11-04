<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Field;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\LinkSetField;
use Mifrial\Core\User\Table\UserTable;

/**
 * Множество ссылок на учётки: linkset с целью `UserTable`.
 */
final class UserLinkSetField extends LinkSetField
{
    /**
     * Создаёт поле-набор ссылок на учётки.
     *
     * @param string $fieldName Имя поля.
     * @param FieldSettings $fieldSettings Настройки.
     * @param string $onDelete Режим restrict или none.
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
