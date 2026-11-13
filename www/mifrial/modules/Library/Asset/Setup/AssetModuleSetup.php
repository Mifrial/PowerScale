<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Setup;

use Mifrial\Core\Kernel\Interface\Service\IModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Library\Asset\Schema\AssetSchema;

/**
 * Таблицы библиотеки ассетов для CLI setup.
 */
final class AssetModuleSetup implements IModuleSetup
{
    /**
     * Возвращает карты модуля.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public function getTableClasses(): array
    {
        return AssetSchema::getTableClasses();
    }

    /**
     * Коллекции создают потребители, а не библиотека.
     *
     * @return array<int, ISetupStep> Пустой список.
     */
    public function getDataSteps(): array
    {
        return [];
    }
}
