<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;

/**
 * Хранилища строки персонажа в игре: шлюз, запись листа и сами строки.
 */
final class GameMembershipPorts
{
    public readonly ISmartTableGateway $gateway;

    public readonly ICharacterActualMutations $mutations;

    public readonly GameCharacterRepository $characters;

    /**
     * Собирает хранилища.
     *
     * @param ISmartTableGateway $gateway Шлюз таблиц.
     * @param ICharacterActualMutations $mutations Запись листа персонажа.
     *
     * @return void
     */
    public function __construct(ISmartTableGateway $gateway, ICharacterActualMutations $mutations)
    {
        $this->gateway = $gateway;
        $this->mutations = $mutations;
        $this->characters = new GameCharacterRepository($gateway);
    }
}
