<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;

/**
 * Запись открытого удара: списание, долг, строка и цели.
 */
final class GameStrikeOpen
{
    /**
     * Создаёт запись открытия.
     *
     * @param GameBattleRepository $battles Бои.
     * @param GameActionRepository $actions Действия и цели.
     * @param GameStrikeSpend $spend Списание атакующего.
     *
     * @return void
     */
    public function __construct(
        private readonly GameBattleRepository $battles,
        private readonly GameActionRepository $actions,
        private readonly GameStrikeSpend $spend,
    ) {
    }

    /**
     * Списывает стоимость и пишет открытый удар или удержанный долг.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattleVersion Ожидаемая версия.
     * @param array{
     *     actualVersion: int,
     *     sheet: array{choices: array<string, mixed>, sheet: array<string, mixed>}
     * } $snapshot Снимок атакующего.
     * @param array<string, mixed> $action Строка действия.
     * @param list<array<string, mixed>> $targets Строки целей.
     * @param string $lane Полоса долга.
     * @param string $subject Подлежащее сообщений об ошибке.
     *
     * @return array{debt: bool, strikeId: int, version: int} Итог.
     */
    public function open(
        GameRecord $game,
        array $choice,
        GameBattleRecord $battle,
        int $expectedBattleVersion,
        array $snapshot,
        array $action,
        array $targets,
        string $lane,
        string $subject,
    ): array {
        $hold = $this->spend->spendAttacker($game, $choice, $battle, $snapshot, $lane, $subject);
        if ($hold['debtHold'] !== null) {
            $saved = $this->battles->replaceTracked(
                $battle->getId(),
                $expectedBattleVersion,
                $hold['pending'],
                $hold['debtHold'],
                null,
                null,
            );

            return ['debt' => true, 'strikeId' => 0, 'version' => $saved->getStateVersion()];
        }

        $strikeId = $this->actions->addWithTargets($action, $targets);
        $this->battles->replacePending($battle->getId(), $expectedBattleVersion, $hold['pending']);

        return ['debt' => false, 'strikeId' => $strikeId, 'version' => $expectedBattleVersion + 1];
    }
}
