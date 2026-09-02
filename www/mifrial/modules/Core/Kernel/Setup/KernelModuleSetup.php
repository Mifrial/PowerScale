<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Setup;

use Mifrial\Core\Kernel\Interface\Service\IModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\Kernel\Table\SetupStepTable;

/**
 * Карты Kernel для CLI setup: реестр data-шагов.
 */
final class KernelModuleSetup implements IModuleSetup
{
    /**
     * Возвращает карту реестра шагов.
     *
     * @return array<int, class-string<SetupStepTable>> Карты.
     */
    public function tableClasses(): array
    {
        return [SetupStepTable::class];
    }

    /**
     * У Kernel нет data-шагов.
     *
     * @return array<int, ISetupStep> Пустой список.
     */
    public function dataSteps(): array
    {
        return [];
    }
}
