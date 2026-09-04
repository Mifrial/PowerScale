<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\SmartTable\Value\DateTimeNow;

/**
 * Каталог политик безопасности (пароль). Не singleton.
 */
final class AuthSecurityPolicyTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'auth_security_policy';
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
            new StringField('name', FieldSettings::fromOptions(['required' => true])),
            new IntField('min_length', FieldSettings::fromOptions(['required' => true, 'default' => 4]), 1),
            new BoolField('require_mixed_case', FieldSettings::fromOptions(['required' => true, 'default' => false])),
            new BoolField('require_digit', FieldSettings::fromOptions(['required' => true, 'default' => false])),
            new BoolField('require_special_char', FieldSettings::fromOptions(['required' => true, 'default' => false])),
            new BoolField('is_default', FieldSettings::fromOptions(['required' => true, 'default' => false])),
            new DateTimeField(
                'updated_at',
                FieldSettings::fromOptions(['required' => true, 'default' => DateTimeNow::instance()]),
            ),
        ];
    }
}
