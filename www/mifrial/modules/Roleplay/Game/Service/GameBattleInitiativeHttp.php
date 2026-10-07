<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\RollGameInitiativeInput;

/**
 * HTTP инициативы. В handle action только этот сценарий.
 */
final class GameBattleInitiativeHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param GameBattleInitiatives $initiatives Фасад.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly GameBattleInitiatives $initiatives,
    ) {
    }

    /**
     * Считает порядок боя.
     *
     * @param RollGameInitiativeInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function roll(RollGameInitiativeInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->initiatives->roll(
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
