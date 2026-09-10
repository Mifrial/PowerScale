<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Character\Table\CharacterTable;
use Mifrial\Roleplay\Character\Table\CharacterViewerTable;

/**
 * Сверка карт Character с физикой.
 */
final class CharacterSchema
{
    /**
     * Создаёт установщик схемы.
     *
     * @param IOpenedSchema $characterSchema DDL `character`.
     * @param IOpenedSchema $viewerSchema DDL `character_viewer`.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $characterSchema,
        private readonly IOpenedSchema $viewerSchema,
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
            CharacterTable::class,
            CharacterViewerTable::class,
        ];
    }

    /**
     * Приводит таблицы к текущей карте.
     *
     * @return void
     */
    public function install(): void
    {
        $this->apply($this->characterSchema);
        $this->apply($this->viewerSchema);
    }

    /**
     * Создаёт или обновляет одну карту.
     *
     * @param IOpenedSchema $openedSchema DDL.
     *
     * @return void
     */
    private function apply(IOpenedSchema $openedSchema): void
    {
        if ($openedSchema->exists()) {
            $openedSchema->updateTable();

            return;
        }

        $openedSchema->createTable();
    }
}
