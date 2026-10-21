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
     * Тред атаки внутри текущего хода.
     *
     * @param GameBattleRecord $battle Бой.
     * @param string $threadId Идентификатор атаки.
     *
     * @return array{id: string, parentId: string, kind: string} Тред.
     */
    public static function buildAttack(GameBattleRecord $battle, string $threadId): array
    {
        $round = max(1, $battle->getRound());
        $index = max(0, $battle->getActiveIndex());

        return [
            'id' => $threadId,
            'parentId' => 'b' . $battle->getId() . '-r' . $round . '-t' . $index,
            'kind' => 'attack',
        ];
    }
}
