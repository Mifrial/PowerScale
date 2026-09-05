<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Field\TextField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Карта справочника механик `mechanic`.
 */
final class MechanicTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'mechanic';
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
            new StringField('code', FieldSettings::fromOptions(['required' => true])),
            new StringField('name', FieldSettings::fromOptions(['required' => true])),
            new TextField('description', FieldSettings::fromOptions(['required' => true])),
            new StringField(
                'handler_version',
                FieldSettings::fromOptions(['required' => true]),
                64,
            ),
        ];
    }

    /**
     * Пара семейство+поставка уникальна.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['code', 'handler_version'],
        ];
    }
}
