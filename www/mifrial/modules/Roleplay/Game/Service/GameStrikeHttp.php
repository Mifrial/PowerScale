<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\DeclareGameStrikeInput;
use Mifrial\Roleplay\Game\Dto\Action\ResolveGameStrikeInput;
use Mifrial\Roleplay\Game\Interface\Service\IGameStrikes;

/**
 * HTTP удара. В handle action только этот сценарий.
 */
final class GameStrikeHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param IGameStrikes $strikes Фасад.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IGameStrikes $strikes,
    ) {
    }

    /**
     * Открывает удар.
     *
     * @param DeclareGameStrikeInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function declareStrike(DeclareGameStrikeInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->strikes->declareStrike(
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
     * Закрывает удар.
     *
     * @param ResolveGameStrikeInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function resolveStrike(ResolveGameStrikeInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->strikes->resolveStrike(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->battleId,
            $input->idempotencyKey,
            $input->expectedVersion,
            $input->expectedSheetVersion,
            $input->defense,
        );
    }
}
