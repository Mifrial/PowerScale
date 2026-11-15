<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Setup;

use Mifrial\Core\Kernel\Interface\Service\IModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Lore\Schema\LoreSchema;

/**
 * Таблицы кодекса для CLI setup.
 */
final class LoreModuleSetup implements IModuleSetup
{
    /**
     * Возвращает карты модуля.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public function getTableClasses(): array
    {
        return LoreSchema::getTableClasses();
    }

    /**
     * Данных для досева нет.
     *
     * @return array<int, ISetupStep> Пустой список.
     */
    public function getDataSteps(): array
    {
        return [];
    }
}
