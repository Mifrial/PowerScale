<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Interface\Service;

use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Карты и data-шаги модуля для CLI setup. Не порт контейнера соседа.
 */
interface IModuleSetup
{
    /**
     * Возвращает class-string карт модуля. Без обращения к БД.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public function tableClasses(): array;

    /**
     * Возвращает data-шаги модуля в порядке внутри модуля.
     *
     * @return array<int, ISetupStep> Шаги или пустой список.
     */
    public function dataSteps(): array;
}
