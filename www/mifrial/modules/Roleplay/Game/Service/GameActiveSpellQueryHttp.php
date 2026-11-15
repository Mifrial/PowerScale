<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\GetGameActiveSpellsInput;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameActiveSpellRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * HTTP чтения активных заклинаний игры.
 */
final class GameActiveSpellQueryHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param IGames $games Строка игры.
     * @param GameCardAccess $cardAccess Видимость карточки.
     * @param GameSessionRepository $sessions Сессия.
     * @param GameActiveSpellRepository $spells Строки поддержания.
     * @param GameActiveSpellView $view JSON.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IGames $games,
        private readonly GameCardAccess $cardAccess,
        private readonly GameSessionRepository $sessions,
        private readonly GameActiveSpellRepository $spells,
        private readonly GameActiveSpellView $view,
    ) {
    }

    /**
     * Читает строки текущей сессии.
     *
     * @param GetGameActiveSpellsInput $input JSON.
     *
     * @return array<int, array<string, mixed>> Строки.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если игра скрыта.
     */
    public function get(GetGameActiveSpellsInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $game = $this->games->get($input->gameId);
        if (!$this->cardAccess->isVisible(
            $game,
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
        )) {
            throw new GameNotFoundException('Game was not found');
        }

        $sessionId = $this->sessions->findSessionId($input->gameId);
        if ($sessionId === null) {
            return [];
        }

        $rows = [];
        foreach ($this->spells->findForSession($sessionId) as $spell) {
            $rows[] = $this->view->build($spell);
        }

        return $rows;
    }
}
