<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\GameManualSheetInput;

/**
 * HTTP ручной правки листа.
 */
final class GameManualSheetHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param GameManualSheetActions $actions Команды.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly GameManualSheetActions $actions,
    ) {
    }

    /**
     * Выполняет команду.
     *
     * @param GameManualSheetInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function act(GameManualSheetInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->actions->act(
            $input->gameId,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->body(),
        );
    }
}
