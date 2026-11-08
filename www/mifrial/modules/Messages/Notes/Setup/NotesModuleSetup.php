<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Setup;

use Mifrial\Core\Kernel\Interface\Service\IModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Messages\Notes\Schema\NotesSchema;

/**
 * Карты Notes для CLI setup.
 */
final class NotesModuleSetup implements IModuleSetup
{
    /**
     * Возвращает карту модуля.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public function getTableClasses(): array
    {
        return NotesSchema::getTableClasses();
    }

    /**
     * Seed заметок — не этот заход.
     *
     * @return array<int, ISetupStep> Пустой список.
     */
    public function getDataSteps(): array
    {
        return [];
    }
}
