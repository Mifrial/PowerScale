<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Setup;

use Mifrial\Core\Kernel\Interface\Service\IModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Messages\Chat\Schema\ChatSchema;

/**
 * Карты Chat для CLI setup. Те же class-string, что ChatSchema.
 */
final class ChatModuleSetup implements IModuleSetup
{
    /**
     * Возвращает три карты модуля Chat.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public function getTableClasses(): array
    {
        return ChatSchema::getTableClasses();
    }

    /**
     * Seed чатов — не этот заход.
     *
     * @return array<int, ISetupStep> Пустой список.
     */
    public function getDataSteps(): array
    {
        return [];
    }
}
