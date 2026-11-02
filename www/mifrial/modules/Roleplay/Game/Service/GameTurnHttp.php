<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\GameTurnInput;

/**
 * HTTP конца хода.
 */
final class GameTurnHttp
{
    /**
     * @param IUserAccess $userAccess Актор.
     * @param GameTurns $turns Сценарий.
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly GameTurns $turns,
    ) {
    }

    /**
     * @param GameTurnInput $input Вход.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function end(GameTurnInput $input): array
    {
        return $this->call($input, 'end');
    }

    /**
     * @param GameTurnInput $input Вход.
     *
     * @return array<string, mixed> Итог.
     */
    public function continueAction(GameTurnInput $input): array
    {
        return $this->call($input, 'continue');
    }

    /**
     * @param GameTurnInput $input Вход.
     *
     * @return array<string, mixed> Итог.
     */
    public function abort(GameTurnInput $input): array
    {
        return $this->call($input, 'abort');
    }

    /**
     * @param GameTurnInput $input Вход.
     *
     * @return array<string, mixed> Итог.
     */
    public function abortProcess(GameTurnInput $input): array
    {
        return $this->call($input, 'abort_process');
    }

    /**
     * @param GameTurnInput $input Вход.
     * @param string $command Команда.
     *
     * @return array<string, mixed> Итог.
     */
    private function call(GameTurnInput $input, string $command): array
    {
        $actor = $this->userAccess->requireActor();
        $editAll = $actor->hasKey(GamePermissionKeys::EDIT_ALL);
        $viewAll = $actor->hasKey(GamePermissionKeys::VIEW_ALL);
        if ($command === 'continue') {
            return $this->turns->continueAction($input->gameId, $actor->getUserId(), $editAll, $viewAll, $input->body());
        }

        if ($command === 'abort') {
            return $this->turns->abort($input->gameId, $actor->getUserId(), $editAll, $viewAll, $input->body());
        }

        if ($command === 'abort_process') {
            return $this->turns->abortProcess($input->gameId, $actor->getUserId(), $editAll, $viewAll, $input->body());
        }

        return $this->turns->end($input->gameId, $actor->getUserId(), $editAll, $viewAll, $input->body());
    }
}
