<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\GetGameBattleSheetsInput;
use Mifrial\Roleplay\Game\Dto\Action\GetGameRosterInput;
use Mifrial\Roleplay\Game\Dto\Action\GetGameSheetsInput;
use Mifrial\Roleplay\Game\Interface\Service\IGameProjections;

/**
 * HTTP проекций. В handle action только этот сценарий.
 */
final class GameProjectionHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param IGameProjections $projections Фасад.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IGameProjections $projections,
    ) {
    }

    /**
     * Краткий roster.
     *
     * @param GetGameRosterInput $input JSON.
     *
     * @return array<string, mixed> Записи.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function roster(GetGameRosterInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->projections->roster(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
        );
    }

    /**
     * Лист по ключам.
     *
     * @param GetGameSheetsInput $input JSON.
     *
     * @return array<string, mixed> Листы.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function sheets(GetGameSheetsInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->projections->sheets(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->keys,
        );
    }

    /**
     * Листы состава боя.
     *
     * @param GetGameBattleSheetsInput $input JSON.
     *
     * @return array<string, mixed> Состав.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function battleSheets(GetGameBattleSheetsInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->projections->battleSheets(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->battleId,
        );
    }
}
