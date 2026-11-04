<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Setup;

use Mifrial\Core\File\Schema\FileSchema;
use Mifrial\Core\Kernel\Interface\Service\IModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Карта File для CLI setup.
 */
final class FileModuleSetup implements IModuleSetup
{
    /**
     * Возвращает карты модуля.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public function getTableClasses(): array
    {
        return FileSchema::getTableClasses();
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
