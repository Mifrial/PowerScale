<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Setup;

use Mifrial\Core\Kernel\Interface\Service\IModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Core\User\Schema\UserSchema;

/**
 * Карты User для CLI setup. Те же class-string, что UserSchema.
 */
final class UserModuleSetup implements IModuleSetup
{
    /**
     * Возвращает три карты модуля User.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public function tableClasses(): array
    {
        return UserSchema::tableClasses();
    }

    /**
     * Seed групп — не этот заход.
     *
     * @return array<int, ISetupStep> Пустой список.
     */
    public function dataSteps(): array
    {
        return [];
    }
}
