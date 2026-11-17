<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\GetGameActiveEffectsInput;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameActiveEffectRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * HTTP чтения длительных эффектов текущей сессии.
 */
final class GameActiveEffectQueryHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param IGames $games Строка игры.
     * @param GameCardAccess $cardAccess Видимость карточки.
     * @param GameSessionRepository $sessions Сессия.
     * @param GameActiveEffectRepository $effects Строки эффектов.
     * @param GameActiveEffectView $view JSON.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IGames $games,
        private readonly GameCardAccess $cardAccess,
        private readonly GameSessionRepository $sessions,
        private readonly GameActiveEffectRepository $effects,
        private readonly GameActiveEffectView $view,
    ) {
    }

    /**
     * Читает строки текущей сессии.
     *
     * @param GetGameActiveEffectsInput $input JSON.
     *
     * @return array<int, array<string, mixed>> Строки.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если игра скрыта.
     */
    public function get(GetGameActiveEffectsInput $input): array
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
        foreach ($this->effects->findForSession($sessionId) as $effect) {
            $rows[] = $this->view->build($effect);
        }

        return $rows;
    }
}
