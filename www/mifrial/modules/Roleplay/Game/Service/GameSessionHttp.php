<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\GameSessionInput;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Interface\Service\IGameSessionRoster;

/**
 * HTTP старта и stop. Карточку не прячет через видимость game.get.
 */
final class GameSessionHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param IGames $games Игра и роль.
     * @param GameSessions $sessions Старт и stop.
     * @param IGameSessionRoster $sessionRoster Признак сессии.
     * @param GameViewAssembler $viewAssembler JSON.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IGames $games,
        private readonly GameSessions $sessions,
        private readonly IGameSessionRoster $sessionRoster,
        private readonly GameViewAssembler $viewAssembler,
    ) {
    }

    /**
     * Запускает сессию.
     *
     * @param GameSessionInput $input Игра.
     *
     * @return array<string, mixed> Карточка.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если нет права или игры.
     */
    public function start(GameSessionInput $input): array
    {
        $this->assertEditor($input->gameId);
        $this->sessions->start($input->gameId);

        return $this->card($input->gameId);
    }

    /**
     * Останавливает сессию.
     *
     * @param GameSessionInput $input Игра.
     *
     * @return array<string, mixed> Карточка.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если нет права или игры.
     */
    public function stop(GameSessionInput $input): array
    {
        $this->assertEditor($input->gameId);
        $this->sessions->stop($input->gameId);

        return $this->card($input->gameId);
    }

    /**
     * Карточка без assertVisible.
     *
     * @param int $gameId Игра.
     *
     * @return array<string, mixed> JSON.
     *
     * @throws GameNotFoundException Если игры нет.
     */
    private function card(int $gameId): array
    {
        return $this->viewAssembler->detail(
            $this->games->get($gameId),
            $this->sessionRoster->hasSession($gameId),
        );
    }

    /**
     * game.edit этой игры или game.edit_all.
     *
     * @param int $gameId Игра.
     *
     * @return void
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если права нет.
     */
    private function assertEditor(int $gameId): void
    {
        $actor = $this->userAccess->requireActor();
        if ($actor->hasKey(GamePermissionKeys::EDIT_ALL)) {
            return;
        }

        $game = $this->games->get($gameId);
        $role = null;
        try {
            $role = $this->games->getMember($gameId, $actor->getUserId())->getRole();
        } catch (GameNotFoundException) {
            $role = null;
        }

        $keys = GamePermissionKeys::keysFor($game->getOwnerId() === $actor->getUserId(), $role);
        if (!in_array(GamePermissionKeys::EDIT, $keys, true)) {
            throw new GameNotFoundException();
        }
    }
}
