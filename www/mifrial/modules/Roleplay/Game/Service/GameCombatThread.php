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
     * Тред проверки внутри текущего хода или сессии.
     *
     * @param int $processId Process проверки.
     * @param GameBattleRecord|null $battle Бой или null.
     * @param int $sessionId Сессия.
     *
     * @return array{id: string, parentId: string, kind: string} Тред.
     */
    public static function buildCheck(int $processId, ?GameBattleRecord $battle, int $sessionId): array
    {
        $parentId = $battle === null
            ? 's' . $sessionId
            : self::buildTurn($battle->getId(), $battle->getRound(), $battle->getActiveIndex())['id'];

        return [
            'id' => 'check-' . $processId,
            'parentId' => $parentId,
            'kind' => 'check',
        ];
    }

    /**
     * Тред заклинания касания внутри текущего хода.
     *
     * @param GameBattleRecord $battle Бой.
     * @param int $strikeId Удар.
     *
     * @return array{id: string, parentId: string, kind: string} Тред.
     */
    public static function buildSpell(GameBattleRecord $battle, int $strikeId): array
    {
        $turn = self::buildTurn($battle->getId(), $battle->getRound(), $battle->getActiveIndex());

        return [
            'id' => self::spellThreadId($strikeId),
            'parentId' => $turn['id'],
            'kind' => 'spell',
        ];
    }

    /**
     * Тред заклинания без касания внутри текущего хода.
     *
     * @param GameBattleRecord $battle Бой.
     * @param string $key Ключ команды.
     *
     * @return array{id: string, parentId: string, kind: string} Тред.
     */
    public static function buildSpellCast(GameBattleRecord $battle, string $key): array
    {
        $turn = self::buildTurn($battle->getId(), $battle->getRound(), $battle->getActiveIndex());

        return [
            'id' => 'spell-' . substr(hash('sha256', $key === '' ? 'spell' : $key), 0, 12),
            'parentId' => $turn['id'],
            'kind' => 'spell',
        ];
    }

    /**
     * Идентификатор треда заклинания касания.
     *
     * @param int $strikeId Удар.
     *
     * @return string Идентификатор.
     */
    public static function spellThreadId(int $strikeId): string
    {
        return 'spell-' . $strikeId;
    }

    /**
     * Тред атаки внутри текущего хода или треда заклинания.
     *
     * @param GameBattleRecord $battle Бой.
     * @param string $threadId Идентификатор атаки.
     * @param string|null $parentId Родительский тред; null — тред хода.
     *
     * @return array{id: string, parentId: string, kind: string} Тред.
     */
    public static function buildAttack(GameBattleRecord $battle, string $threadId, ?string $parentId = null): array
    {
        $turn = self::buildTurn($battle->getId(), $battle->getRound(), $battle->getActiveIndex());

        return [
            'id' => $threadId,
            'parentId' => $parentId ?? $turn['id'],
            'kind' => 'attack',
        ];
    }
}
