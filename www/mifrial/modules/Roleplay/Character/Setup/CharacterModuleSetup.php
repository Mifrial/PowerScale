<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Setup;

use Mifrial\Core\Kernel\Interface\Service\IModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Character\Schema\CharacterSchema;

/**
 * Карты Character для CLI setup.
 */
final class CharacterModuleSetup implements IModuleSetup
{
    /**
     * Создаёт setup.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз таблиц.
     *
     * @return void
     */
    public function __construct(
        private readonly ISmartTableGateway $smartTableGateway,
    ) {
    }

    /**
     * Возвращает карты модуля.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public function getTableClasses(): array
    {
        return CharacterSchema::getTableClasses();
    }

    /**
     * Data-шаг снятия состояний без id.
     *
     * @return array<int, ISetupStep> Шаги.
     */
    public function getDataSteps(): array
    {
        return [new DropUnidentifiedCharacterStatesStep($this->smartTableGateway)];
    }
}
