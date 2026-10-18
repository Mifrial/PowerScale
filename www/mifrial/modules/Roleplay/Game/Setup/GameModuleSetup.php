<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Setup;

use Mifrial\Core\Kernel\Interface\Service\IModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Game\Schema\GameAdmissionSchema;
use Mifrial\Roleplay\Game\Schema\GameBattleSchema;
use Mifrial\Roleplay\Game\Schema\GameCheckSchema;
use Mifrial\Roleplay\Game\Schema\GameChronicleSchema;
use Mifrial\Roleplay\Game\Schema\GameCommandSchema;
use Mifrial\Roleplay\Game\Schema\GameDeliverySchema;
use Mifrial\Roleplay\Game\Schema\GameEconomySchema;
use Mifrial\Roleplay\Game\Schema\GameProcessSchema;
use Mifrial\Roleplay\Game\Schema\GameSchema;
use Mifrial\Roleplay\Game\Schema\GameStrikeSchema;
use Mifrial\Roleplay\Game\Schema\GameWideStrikeSchema;

/**
 * Карта Game для CLI setup.
 */
final class GameModuleSetup implements IModuleSetup
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
        return array_merge(
            GameSchema::getTableClasses(),
            GameAdmissionSchema::getTableClasses(),
            GameChronicleSchema::getTableClasses(),
            GameEconomySchema::getTableClasses(),
            GameBattleSchema::getTableClasses(),
            GameProcessSchema::getTableClasses(),
            GameStrikeSchema::getTableClasses(),
            GameWideStrikeSchema::getTableClasses(),
            GameCheckSchema::getTableClasses(),
            GameCommandSchema::getTableClasses(),
            GameDeliverySchema::getTableClasses(),
        );
    }

    /**
     * Снимает NPC-состояния без id.
     *
     * @return array<int, ISetupStep> Шаги.
     */
    public function getDataSteps(): array
    {
        return [
            new DropUnidentifiedNpcStatesStep($this->smartTableGateway),
        ];
    }
}
