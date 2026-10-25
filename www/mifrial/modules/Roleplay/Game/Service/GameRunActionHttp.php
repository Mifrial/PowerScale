<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\RunGameActionInput;

/**
 * HTTP единого входа действий.
 */
final class GameRunActionHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param GameRunAction $actions Диспетчер.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly GameRunAction $actions,
    ) {
    }

    /**
     * Выполняет действие.
     *
     * @param RunGameActionInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED, AUTH_DENIED или INVALID_PARAMS.
     */
    public function run(RunGameActionInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->actions->run(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->battleId,
            $input->idempotencyKey,
            $input->expectedVersion,
            $input->action,
        );
    }
}
