<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Tests\Fixture;

use Mifrial\Core\SmartTable\Field\IdField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Identity фикстуры vt_note.
 */
final class NoteIdentityTable extends SmartTableDefinition
{
    /**
     * Задаёт физическое имя таблицы.
     *
     * @return string Имя.
     */
    protected function tableName(): string
    {
        return 'vt_note';
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
        ];
    }
}
