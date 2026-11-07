<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;

/**
 * Ожидание конца хода: карточка wait и её исполнение.
 */
final class GameTurnWait
{
    /**
     * Создаёт шаг.
     *
     * @param GameActionRepository $strikes Открытые удары.
     * @param GameStrikeRules $rules Срез.
     * @param GameActionCards $cards Карточки действий.
     * @param GameActionPerform $action Исполнение карточки.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActionRepository $strikes,
        private readonly GameStrikeRules $rules,
        private readonly GameActionCards $cards,
        private readonly GameActionPerform $action,
    ) {
    }

    /**
     * Проверяет отсутствие удара и исполняет Ожидание.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array{type: string, id: int} $current Текущий.
     * @param int $expectedSheet Версия листа.
     *
     * @return array<string, mixed> Исполнение.
     *
     * @throws GameInvalidException Если удар открыт или карточки нет.
     * @throws GameNotFoundException Если листа нет.
     */
    public function perform(
        GameRecord $game,
        GameBattleRecord $battle,
        array $current,
        int $expectedSheet,
    ): array {
        if ($this->strikes->findOpenAny($battle->getId()) !== null) {
            throw new GameInvalidException('Game strike is open');
        }

        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $waitCode = $this->cards->getByCombatAction($slice, 'wait');
        $performed = $this->action->perform(
            $game,
            $current['type'],
            $current['id'],
            $waitCode,
            $expectedSheet,
            $battle->getPendingActionEffects(),
            true,
        );

        return $performed + ['slice' => $slice, 'waitCode' => $waitCode];
    }
}
