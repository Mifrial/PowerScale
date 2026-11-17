<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameActiveEffectRepository;
use Mifrial\Roleplay\Game\Repository\GameActiveSpellRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCheckRepository;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameProcessRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

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
            (new GameActionRepository($this->smartTableGateway))->deleteBySession($sessionId);
            (new GameCommandRepository($this->smartTableGateway))->deleteBySession($sessionId);
            $battles = new GameBattleRepository($this->smartTableGateway);
            $activeSpells = new GameActiveSpellRepository($this->smartTableGateway);
            $activeEffects = new GameActiveEffectRepository($this->smartTableGateway);
            foreach ($battles->findIdsBySession($sessionId) as $battleId) {
                $activeSpells->deleteForBattle($battleId);
                $activeEffects->deleteForBattle($battleId);
                $battles->delete($battleId);
            }
            $this->sessionRepository->deleteSession($sessionId);
        });
    }
}
