<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Setup;

use Mifrial\Core\Kernel\Interface\Service\IModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Table\MetaFieldDefinition;
use Mifrial\Core\SmartTable\Table\MetaTableDefinition;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Карты словаря SmartTable для CLI setup.
 */
final class SmartTableModuleSetup implements IModuleSetup
{
    /**
     * Возвращает PHP-классы meta-таблиц словаря.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public function tableClasses(): array
    {
        return [MetaTableDefinition::class, MetaFieldDefinition::class];
    }

    /**
     * У SmartTable нет data-шагов в этом заходе.
     *
     * @return array<int, ISetupStep> Пустой список.
     */
    public function dataSteps(): array
    {
        return [];
    }
}
