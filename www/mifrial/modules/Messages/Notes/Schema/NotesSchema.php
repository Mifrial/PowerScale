<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Messages\Notes\Table\NoteTable;

/**
 * Сверка карты Notes с физикой. Не порт соседа.
 */
final class NotesSchema
{
    /**
     * Создаёт установщик схемы модуля.
     *
     * @param IOpenedSchema $noteSchema DDL `note`.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $noteSchema,
    ) {
    }

    /**
     * Возвращает class-string карт модуля.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты Notes.
     */
    public static function getTableClasses(): array
    {
        return [NoteTable::class];
    }

    /**
     * Приводит таблицы модуля к текущим картам.
     *
     * @return void
     */
    public function install(): void
    {
        if ($this->noteSchema->exists()) {
            $this->noteSchema->updateTable();

            return;
        }

        $this->noteSchema->createTable();
    }
}
