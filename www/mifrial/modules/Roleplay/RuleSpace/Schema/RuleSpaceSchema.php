<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceCatalogItemTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceCatalogSectionTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceMetaTable;
use Mifrial\Roleplay\RuleSpace\Table\RuleSpaceRevisionCatalogTable;

/**
 * Сверка карт RuleSpace с физикой.
 */
final class RuleSpaceSchema
{
    /**
     * Создаёт установщик схемы.
     *
     * @param IOpenedSchema $ruleSpaceSchema DDL `rulespace`.
     * @param IOpenedSchema $sectionSchema DDL секций.
     * @param IOpenedSchema $itemSchema DDL размещений.
     * @param IOpenedSchema $pointerSchema DDL указателей.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $ruleSpaceSchema,
        private readonly IOpenedSchema $sectionSchema,
        private readonly IOpenedSchema $itemSchema,
        private readonly IOpenedSchema $pointerSchema,
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
            RuleSpaceMetaTable::class,
            RuleSpaceCatalogSectionTable::class,
            RuleSpaceCatalogItemTable::class,
            RuleSpaceRevisionCatalogTable::class,
        ];
    }

    /**
     * Приводит таблицы к текущей карте.
     *
     * @return void
     */
    public function install(): void
    {
        $this->apply($this->ruleSpaceSchema);
        $this->apply($this->sectionSchema);
        $this->apply($this->itemSchema);
        $this->apply($this->pointerSchema);
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
