<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;
use Mifrial\Core\User\Field\UserReferenceField;

/**
 * Карта аватара учётки `user_avatar`.
 */
final class UserAvatarTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'user_avatar';
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
            new UserReferenceField(
                'user_id',
                FieldSettings::fromOptions(['required' => true, 'unique' => true]),
                'cascade',
            ),
            // Без FK на `file`: User не должен зависеть от схемы File, а строки
            // файлов не удаляются, только архивируются.
            new IntField(
                'file_id',
                FieldSettings::fromOptions(['required' => true, 'indexed' => true]),
                1,
            ),
            new DateTimeField('created_at', FieldSettings::fromOptions([
                'required' => true,
                'default' => DateTimeNow::instance(),
            ])),
        ];
    }
}
