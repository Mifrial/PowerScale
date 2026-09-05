<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Rule\Table\RuleRevisionItemTable;
use Mifrial\Roleplay\Rule\Table\RuleRevisionTable;
use Mifrial\Roleplay\Rule\Table\RuleSpaceTable;
use Mifrial\Roleplay\Rule\Table\RuleTable;
use Mifrial\Roleplay\Rule\Table\RuleVersionTable;

/**
 * Сверка карт Rule с физикой.
 */
final class RuleSchema
{
    /**
     * Создаёт установщик схемы.
     *
     * @param IOpenedSchema $ruleSchema DDL `rule`.
     * @param IOpenedSchema $spaceSchema DDL `rule_space`.
     * @param IOpenedSchema $versionSchema DDL `rule_version`.
     * @param IOpenedSchema $revisionSchema DDL `rule_revision`.
     * @param IOpenedSchema $itemSchema DDL `rule_revision_item`.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $ruleSchema,
        private readonly IOpenedSchema $spaceSchema,
        private readonly IOpenedSchema $versionSchema,
        private readonly IOpenedSchema $revisionSchema,
        private readonly IOpenedSchema $itemSchema,
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
            RuleTable::class,
            RuleSpaceTable::class,
            RuleVersionTable::class,
            RuleRevisionTable::class,
            RuleRevisionItemTable::class,
        ];
    }

    /**
     * Приводит таблицы к текущей карте.
     *
     * @return void
     */
    public function install(): void
    {
        $this->apply($this->ruleSchema);
        $this->apply($this->spaceSchema);
        $this->apply($this->versionSchema);
        $this->apply($this->revisionSchema);
        $this->apply($this->itemSchema);
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
