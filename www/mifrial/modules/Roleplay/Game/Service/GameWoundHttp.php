<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\GameWoundInput;

/**
 * HTTP зажима и перевязки.
 */
final class GameWoundHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param GameWoundActions $wounds Команды.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly GameWoundActions $wounds,
    ) {
    }

    /**
     * Выполняет команду.
     *
     * @param GameWoundInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function act(GameWoundInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->wounds->act(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->body(),
        );
    }
}
