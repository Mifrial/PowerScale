<?php

declare(strict_types=1);

namespace Mifrial\Core\Agent\Setup;

use Mifrial\Core\Agent\Schema\AgentSchema;
use Mifrial\Core\Kernel\Interface\Service\IModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Карта Agent для CLI setup. Data-шаги пишет донор.
 */
final class AgentModuleSetup implements IModuleSetup
{
    /**
     * Возвращает карты модуля.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public function getTableClasses(): array
    {
        return AgentSchema::getTableClasses();
    }

    /**
     * Строки агентов сеет донор.
     *
     * @return array<int, ISetupStep> Пустой список.
     */
    public function getDataSteps(): array
    {
        return [];
    }
}
