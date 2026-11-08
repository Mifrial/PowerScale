<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameStrikeWideRun;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Хвост действия после широкого удара: модификаторы карточки.
 */
final class GameStrikeWideTrail
{
    /**
     * Создаёт хвост широкого удара.
     *
     * @param GameActionModifierRunner $modifiers Модификаторы действия.
     *
     * @return void
     */
    public function __construct(private readonly GameActionModifierRunner $modifiers)
    {
    }

    /**
     * Исполняет модификаторы действия один раз после закрытия удара.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param array<string, mixed> $open Открытый удар.
     * @param list<array<string, mixed>> $rows Строки целей.
     *
     * @return array<string, mixed>|null Итог или null без модификаторов.
     *
     * @throws GameInvalidException Если удар или коды битые.
     */
    public function apply(GameStrikeWideRun $run, array $open, array $rows): ?array
    {
        $codes = GameActionModifierCodes::sharedRows($rows);
        if ($codes === []) {
            return null;
        }

        $actionId = $open['id'] ?? null;
        $actionRuleCode = $open['action_rule_code'] ?? null;
        $attackerKind = $open['attacker_kind'] ?? null;
        $attackerId = $open['attacker_id'] ?? null;
        if (
            !is_int($actionId)
            || !is_string($actionRuleCode)
            || !is_string($attackerKind)
            || !is_int($attackerId)
        ) {
            throw new GameInvalidException('Game wide strike is invalid');
        }

        return $this->modifiers->perform(new GameActionModifierInput(
            $run->game,
            $run->battle,
            $open,
            $actionRuleCode,
            $attackerKind,
            $attackerId,
            $codes,
            $rows,
            'attack-wide-' . $actionId,
            $run->scope->actorUserId,
        ));
    }
}
