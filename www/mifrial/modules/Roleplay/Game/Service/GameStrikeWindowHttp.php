<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\ResolveGameStrikeWindowInput;
use Mifrial\Roleplay\Game\Interface\Service\IGameStrikes;

/**
 * HTTP решения окна удара.
 */
final class GameStrikeWindowHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param IGameStrikes $strikes Удары.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IGameStrikes $strikes,
    ) {
    }

    /**
     * Выполняет решение.
     *
     * @param ResolveGameStrikeWindowInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED, AUTH_DENIED или INVALID_PARAMS.
     */
    public function run(ResolveGameStrikeWindowInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->strikes->resolveWindow(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->battleId,
            $input->idempotencyKey,
            $input->expectedVersion,
            $input->window,
        );
    }
}
