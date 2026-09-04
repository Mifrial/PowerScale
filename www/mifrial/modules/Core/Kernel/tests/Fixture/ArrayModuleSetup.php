<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests\Fixture;

use Mifrial\Core\Kernel\Interface\Service\IModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Setup для тестов прогона.
 */
final class ArrayModuleSetup implements IModuleSetup
{
    /**
     * Создаёт setup из списков.
     *
     * @param array<int, class-string<SmartTableDefinition>> $tableClasses Карты.
     * @param array<int, ISetupStep> $dataSteps Шаги.
     *
     * @return void
     */
    public function __construct(
        private readonly array $tableClasses,
        private readonly array $dataSteps = [],
    ) {
    }

    /**
     * Возвращает карты.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public function getTableClasses(): array
    {
        return $this->tableClasses;
    }

    /**
     * Возвращает шаги.
     *
     * @return array<int, ISetupStep> Шаги.
     */
    public function getDataSteps(): array
    {
        return $this->dataSteps;
    }
}
