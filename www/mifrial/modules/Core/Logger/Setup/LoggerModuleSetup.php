<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Setup;

use Mifrial\Core\Kernel\Interface\Service\IModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\Logger\Schema\LoggerSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Карта Logger для CLI setup.
 */
final class LoggerModuleSetup implements IModuleSetup
{
    /**
     * Возвращает карты модуля.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public function getTableClasses(): array
    {
        return LoggerSchema::getTableClasses();
    }

    /**
     * Data-шагов нет.
     *
     * @return array<int, ISetupStep> Пустой список.
     */
    public function getDataSteps(): array
    {
        return [];
    }
}
