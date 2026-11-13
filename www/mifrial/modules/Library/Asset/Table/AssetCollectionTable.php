<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Table;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\BoolField;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Карта `asset_collection`: назначение ассетов, объявленное модулем.
 */
final class AssetCollectionTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'asset_collection';
    }

    /**
     * Перечисляет поля определения.
     *
     * @return array Список полей.
     */
    protected function defineFields(): array
    {
        $required = FieldSettings::fromOptions(['required' => true]);

        return [
            new IdField(),
            new StringField('module', $required, 64),
            new StringField('code', $required, 64),
            new StringField('title', $required, 120),
            new BoolField('is_active', FieldSettings::fromOptions([
                'required' => true,
                'default' => true,
                'indexed' => true,
            ])),
        ];
    }

    /**
     * Пара модуль+код уникальна.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['module', 'code'],
        ];
    }
}
