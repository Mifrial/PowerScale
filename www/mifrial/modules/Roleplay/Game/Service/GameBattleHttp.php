<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\EndGameBattleInput;
use Mifrial\Roleplay\Game\Dto\Action\SetGameBattleRosterInput;
use Mifrial\Roleplay\Game\Dto\Action\StartGameBattleInput;
use Mifrial\Roleplay\Game\Interface\Service\IGameBattles;

/**
 * HTTP боя. В handle action только этот сценарий.
 */
final class GameBattleHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param IGameBattles $battles Фасад.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IGameBattles $battles,
    ) {
    }

    /**
     * Открывает бой.
     *
     * @param StartGameBattleInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function start(StartGameBattleInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->battles->start(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->idempotencyKey,
            $input->participants,
        );
    }

    /**
     * Меняет состав.
     *
     * @param SetGameBattleRosterInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function setRoster(SetGameBattleRosterInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->battles->setRoster(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->battleId,
            $input->idempotencyKey,
            $input->participants,
            $input->expectedVersion,
        );
    }

    /**
     * Закрывает бой.
     *
     * @param EndGameBattleInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function end(EndGameBattleInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->battles->end(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->battleId,
            $input->idempotencyKey,
            $input->expectedVersion,
        );
    }
}
