<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Курсор хода открытого боя.
 */
final class GameTurnCursor
{
    /**
     * Текущий участник по курсору.
     *
     * @param GameBattleRecord $battle Бой.
     *
     * @return array{type: string, id: int} Участник.
     *
     * @throws GameInvalidException Если порядок или запись битые.
     */
    public function current(GameBattleRecord $battle): array
    {
        $order = $battle->getTurnOrder() ?? [];
        $entry = $order[$battle->getActiveIndex()] ?? null;
        if (!is_array($entry) || !is_string($entry['type'] ?? null) || !is_int($entry['id'] ?? null)) {
            throw new GameInvalidException('Game turn order is missing');
        }

        return ['type' => $entry['type'], 'id' => $entry['id']];
    }

    /**
     * Следующий курсор и раунд.
     *
     * @param GameBattleRecord $battle Бой.
     *
     * @return array{index: int, round: int} Курсор.
     */
    public function advanced(GameBattleRecord $battle): array
    {
        $order = $battle->getTurnOrder() ?? [];
        $next = $battle->getActiveIndex() + 1;
        if (!array_key_exists($next, $order)) {
            return ['index' => 0, 'round' => $battle->getRound() + 1];
        }

        return ['index' => $next, 'round' => $battle->getRound()];
    }
}
