<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameBattleCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCheckCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameCheckRepository;
use Mifrial\Roleplay\Game\Repository\GameProcessRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;
use Mifrial\Roleplay\Game\Repository\GameStrikeCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameStrikeRepository;
use Mifrial\Roleplay\Game\Repository\GameWideStrikeCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameWideStrikeRepository;
use Mifrial\Roleplay\Game\Repository\GameWideStrikeTargetRepository;

/**
 * Снимает бои сессии и затем саму сессию одной транзакцией.
 */
final class GameBattleCleanup
{
    /**
     * Создаёт снятие.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     * @param GameSessionRepository $sessionRepository Строка сессии.
     *
     * @return void
     */
    public function __construct(
        private readonly ISmartTableGateway $smartTableGateway,
        private readonly GameSessionRepository $sessionRepository,
    ) {
    }

    /**
     * Удаляет бои, команды и сессию.
     *
     * @param int $sessionId Сессия.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строку уже сняли.
     * @throws GameInvalidException Если поле.
     */
    public function deleteWithSession(int $sessionId): void
    {
        $this->smartTableGateway->transaction(function () use ($sessionId): void {
            (new GameProcessRepository($this->smartTableGateway))->cancelOpenForSession($sessionId);
            (new GameCheckRepository($this->smartTableGateway))->deleteBySession($sessionId);
            (new GameCheckCommandRepository($this->smartTableGateway))->deleteBySession($sessionId);
            $wideStrikes = new GameWideStrikeRepository($this->smartTableGateway);
            $wideStrikeIds = $wideStrikes->findIdsBySession($sessionId);
            (new GameWideStrikeTargetRepository($this->smartTableGateway))->deleteByStrikes($wideStrikeIds);
            $wideStrikes->deleteBySession($sessionId);
            (new GameWideStrikeCommandRepository($this->smartTableGateway))->deleteBySession($sessionId);
            (new GameStrikeRepository($this->smartTableGateway))->deleteBySession($sessionId);
            (new GameStrikeCommandRepository($this->smartTableGateway))->deleteBySession($sessionId);
            (new GameBattleRepository($this->smartTableGateway))->deleteBySession($sessionId);
            (new GameBattleCommandRepository($this->smartTableGateway))->deleteBySession($sessionId);
            $this->sessionRepository->deleteSession($sessionId);
        });
    }
}
