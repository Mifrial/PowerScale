<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Repository;

use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Строки широкого удара 1 → N на общей модели действия.
 */
final class GameWideStrikeRepository
{
    private readonly GameActionRepository $actions;

    private readonly GameActionTargetRepository $targets;

    /**
     * Создаёт репозиторий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз ST.
     *
     * @return void
     */
    public function __construct(
        private readonly ISmartTableGateway $smartTableGateway,
    ) {
        $this->actions = new GameActionRepository($smartTableGateway);
        $this->targets = new GameActionTargetRepository($smartTableGateway);
    }

    /**
     * Открытый широкий удар боя или null.
     *
     * @param int $battleId Бой.
     *
     * @return array<string, mixed>|null Колонки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findOpen(int $battleId): ?array
    {
        return $this->actions->findOpenByBattle($battleId, 'wide_strike');
    }

    /**
     * Вставляет открытый удар.
     *
     * @param array<string, mixed> $fields Колонки.
     *
     * @return int Id.
     *
     * @throws GameNotFoundException Если сессии нет.
     * @throws GameInvalidException Если поле.
     */
    public function add(array $fields): int
    {
        $fields['kind'] = 'wide_strike';

        return $this->actions->add($fields);
    }

    /**
     * Закрывает удар.
     *
     * @param int $strikeId Удар.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    public function close(int $strikeId): void
    {
        $this->actions->close($strikeId);
    }

    /**
     * Id ударов сессии.
     *
     * @param int $sessionId Сессия.
     *
     * @return list<int> Id.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findIdsBySession(int $sessionId): array
    {
        return $this->actions->findIdsBySession($sessionId, 'wide_strike');
    }

    /**
     * Снимает удары сессии.
     *
     * @param int $sessionId Сессия.
     *
     * @return list<int> Id снятых ударов.
     *
     * @throws GameNotFoundException Если строку уже сняли.
     * @throws GameInvalidException Если поле.
     */
    public function deleteBySession(int $sessionId): array
    {
        return $this->smartTableGateway->transaction(function () use ($sessionId): array {
            $ids = $this->actions->findIdsBySession($sessionId, 'wide_strike');
            $this->targets->deleteByActions($ids);
            foreach ($ids as $strikeId) {
                $this->actions->delete($strikeId);
            }

            return $ids;
        });
    }
}
