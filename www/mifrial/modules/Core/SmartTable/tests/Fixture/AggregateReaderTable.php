<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests\Fixture;

use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Field\IntField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Чтение треда: unique пара thread+user.
 */
final class AggregateReaderTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'st_agg_reader';
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
            new IntField('thread_id', FieldSettings::fromOptions(['required' => true])),
            new IntField('user_id', FieldSettings::fromOptions(['required' => true])),
            new IntField('last_read_id', FieldSettings::fromOptions(), null, null, true),
        ];
    }

    /**
     * Пара тред+учётка уникальна.
     *
     * @return array<int, array<int, string>> Кортежи.
     */
    protected function defineUniqueKeys(): array
    {
        return [
            ['thread_id', 'user_id'],
        ];
    }
}
