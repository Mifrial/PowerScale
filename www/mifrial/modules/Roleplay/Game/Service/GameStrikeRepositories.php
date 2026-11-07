<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * Репозитории ударного домена.
 */
final class GameStrikeRepositories
{
    public readonly GameBattleRepository $battles;

    public readonly GameActionRepository $actions;

    public readonly GameCommandRepository $commands;

    public readonly GameNpcRepository $npcs;

    public readonly GameCharacterRepository $characters;

    public readonly GameMemberRepository $members;

    public readonly GameSessionRepository $sessions;

    /**
     * Создаёт набор репозиториев.
     *
     * @param ISmartTableGateway $gateway Шлюз таблиц.
     *
     * @return void
     */
    public function __construct(ISmartTableGateway $gateway)
    {
        $this->battles = new GameBattleRepository($gateway);
        $this->actions = new GameActionRepository($gateway);
        $this->commands = new GameCommandRepository($gateway);
        $this->npcs = new GameNpcRepository($gateway);
        $this->characters = new GameCharacterRepository($gateway);
        $this->members = new GameMemberRepository($gateway);
        $this->sessions = new GameSessionRepository($gateway);
    }
}
