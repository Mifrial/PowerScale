<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Service\Read\CharacterSectionMask;
use Mifrial\Roleplay\Game\Interface\Service\IGameMemberships;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameShopPositionRepository;

/**
 * Порты экономики: лист, NPC, магазин и проекция конфликтов.
 */
final class GameEconomyPorts
{
    public readonly GameMemberRepository $members;

    public readonly GameNpcRepository $npcs;

    public readonly GameShopPositionRepository $shopPositions;

    public readonly ConflictSheetProjection $conflictProjection;

    /**
     * Собирает порты.
     *
     * @param ISmartTableGateway $gateway Шлюз таблиц.
     * @param ICharacterActualMutations $mutations Порт мутации листа.
     * @param ICharacters $characters Строки персонажа.
     * @param IGameMemberships $memberships Персонажи игры.
     *
     * @return void
     */
    public function __construct(
        public readonly ISmartTableGateway $gateway,
        public readonly ICharacterActualMutations $mutations,
        public readonly ICharacters $characters,
        public readonly IGameMemberships $memberships,
    ) {
        $this->members = new GameMemberRepository($gateway);
        $this->npcs = new GameNpcRepository($gateway);
        $this->shopPositions = new GameShopPositionRepository($gateway);
        $this->conflictProjection = new ConflictSheetProjection(
            new CharacterSectionMask(),
            new GameCharacterProjectionMask(),
            new GameNpcVisibility(),
        );
    }
}
