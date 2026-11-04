<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Schema;

use Mifrial\Core\File\Table\FileTable;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Сверка карты File с физикой.
 */
final class FileSchema
{
    /**
     * Создаёт установщик схемы.
     *
     * @param IOpenedSchema $fileSchema DDL `file`.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $fileSchema,
    ) {
    }

    /**
     * Возвращает class-string карт модуля.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public static function getTableClasses(): array
    {
        return [
            FileTable::class,
        ];
    }

    /**
     * Приводит таблицу к текущей карте.
     *
     * @return void
     */
    public function install(): void
    {
        if ($this->fileSchema->exists()) {
            $this->fileSchema->updateTable();

            return;
        }

        $this->fileSchema->createTable();
    }
}
