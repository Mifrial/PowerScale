<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;

/**
 * Чат одиночного удара.
 */
final class GameStrikeSingleChat
{
    /**
     * Создаёт чат.
     *
     * @param GameStrikeSingleView $view Подписи удара.
     * @param GameStrikeSingleNarrative $narrative Нарратив.
     * @param GameStrikeSingleChecks $checks Проверки.
     * @param GameStrikeSingleOutcome $outcome Итоги.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeSingleView $view,
        private readonly GameStrikeSingleNarrative $narrative,
        private readonly GameStrikeSingleChecks $checks,
        private readonly GameStrikeSingleOutcome $outcome,
    ) {
    }

    /**
     * Чат одиночного удара.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $open Открытый удар.
     * @param int $actorUserId Актор.
     * @param int $strikeId Удар.
     * @param string $reaction Реакция.
     * @param array<string, mixed> $result Итог.
     * @param array<string, mixed>|null $strengthRoll Проверка Силы до итога.
     * @param array<string, mixed>|null $cover Прикрытие: исходная цель и прикрывающий.
     * @param array<string, mixed>|null $maneuver Раскрытый манёвр.
     *
     * @return void
     */
    public function post(
        GameRecord $game,
        GameBattleRecord $battle,
        array $open,
        int $actorUserId,
        int $strikeId,
        string $reaction,
        array $result,
        ?array $strengthRoll = null,
        ?array $cover = null,
        ?array $maneuver = null,
    ): void {
        $header = $this->view->header($game, $battle, $open, $result, $cover, $strikeId);
        $split = $this->view->cover($cover);
        $narrative = $this->narrative->of($game, $header, $open, $result, $reaction, $cover, $split, $maneuver);
        $pre = $this->checks->before($header, $cover, $split, $strengthRoll, $reaction);
        $bits = $this->outcome->bits($header, $result);
        $this->outcome->post(
            $game,
            $actorUserId,
            $header,
            $result,
            $narrative,
            $this->outcome->text($header, $open, $result, $reaction, $bits),
            $pre,
        );
        $this->outcome->extras($game, $actorUserId, $header);
    }

    /**
     * Дополнительные проверки после итога: прирост состояний.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $open Открытый удар.
     * @param int $actorUserId Актор.
     * @param int $strikeId Удар.
     * @param list<array{roll: array<string, mixed>, text: string, verdict: string|null}> $checks Проверки.
     *
     * @return void
     */
    public function postChecks(
        GameRecord $game,
        GameBattleRecord $battle,
        array $open,
        int $actorUserId,
        int $strikeId,
        array $checks,
    ): void {
        $this->checks->postChecks($game, $battle, $open, $actorUserId, $strikeId, $checks);
    }

    /**
     * Строка урона дополнительного пакета.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $open Открытый удар.
     * @param int $actorUserId Актор.
     * @param int $strikeId Удар.
     * @param int $packet Номер пакета.
     * @param array<string, mixed> $result Итог.
     *
     * @return void
     */
    public function postPacket(
        GameRecord $game,
        GameBattleRecord $battle,
        array $open,
        int $actorUserId,
        int $strikeId,
        int $packet,
        array $result,
    ): void {
        $this->outcome->packet(
            $game,
            $actorUserId,
            $this->view->thread($battle, $strikeId, $open),
            $this->view->defenderToken($open),
            $this->view->attackerSpeaker($open),
            $packet,
            $result,
        );
    }
}
