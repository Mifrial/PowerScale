<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;

/**
 * Идентификаторы тредов чата боя.
 */
final class GameCombatThread
{
    /**
     * Тред раунда.
     *
     * @param int $battleId Бой.
     * @param int $round Раунд.
     *
     * @return array{id: string, parentId: string, kind: string} Тред.
     */
    public static function buildRound(int $battleId, int $round): array
    {
        return [
            'id' => 'b' . $battleId . '-r' . max(1, $round),
            'parentId' => 'b' . $battleId,
            'kind' => 'round',
        ];
    }

    /**
     * Тред хода.
     *
     * @param int $battleId Бой.
     * @param int $round Раунд.
     * @param int $index Индекс в порядке хода.
     *
     * @return array{id: string, parentId: string, kind: string} Тред.
     */
    public static function buildTurn(int $battleId, int $round, int $index): array
    {
        $roundThread = self::buildRound($battleId, $round);

        return [
            'id' => $roundThread['id'] . '-t' . max(0, $index),
            'parentId' => $roundThread['id'],
            'kind' => 'turn',
        ];
    }

    /**
     * Тред атаки внутри текущего хода.
     *
     * @param GameBattleRecord $battle Бой.
     * @param string $threadId Идентификатор атаки.
     *
     * @return array{id: string, parentId: string, kind: string} Тред.
     */
    public static function buildAttack(GameBattleRecord $battle, string $threadId): array
    {
        $turn = self::buildTurn($battle->getId(), $battle->getRound(), $battle->getActiveIndex());

        return [
            'id' => $threadId,
            'parentId' => $turn['id'],
            'kind' => 'attack',
        ];
    }
}
