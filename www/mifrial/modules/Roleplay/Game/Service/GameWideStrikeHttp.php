<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\DeclareGameWideStrikeInput;
use Mifrial\Roleplay\Game\Dto\Action\ResolveGameWideStrikeInput;
use Mifrial\Roleplay\Game\Interface\Service\IGameWideStrikes;

/**
 * HTTP широкого удара. В handle action только этот сценарий.
 */
final class GameWideStrikeHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param IGameWideStrikes $strikes Фасад.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IGameWideStrikes $strikes,
    ) {
    }

    /**
     * Открывает широкий удар.
     *
     * @param DeclareGameWideStrikeInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function declareWideStrike(DeclareGameWideStrikeInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->strikes->declareWideStrike(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->battleId,
            $input->idempotencyKey,
            $input->expectedVersion,
            $input->attack,
        );
    }

    /**
     * Закрывает широкий удар.
     *
     * @param ResolveGameWideStrikeInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function resolveWideStrike(ResolveGameWideStrikeInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->strikes->resolveWideStrike(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->battleId,
            $input->idempotencyKey,
            $input->expectedVersion,
            $input->defense,
        );
    }
}
