<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameStrikeTargetOptions;
use Mifrial\Roleplay\Game\Dto\GameStrikeWriteScope;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Расчёт и запись одной цели удара.
 */
final class GameStrikeTargetClose
{
    public GameStrikeTargetRolls $rolls;

    /**
     * Создаёт расчёт цели.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeTargetReaction $reaction Реакция цели.
     * @param GameStrikeCover $cover Броски прикрытия.
     * @param GameStrikeTargetFlow $flow Расчёт цели.
     * @param GameStrikeTargetRolls $rolls Броски и урон цели.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeTargetReaction $reaction,
        private readonly GameStrikeCover $cover,
        private readonly GameStrikeTargetFlow $flow,
        GameStrikeTargetRolls $rolls,
    ) {
        $this->rolls = $rolls;
    }

    /**
     * Начинает расчёт с карты боя.
     *
     * @param array<string, mixed> $pending Карта.
     * @param array<string, mixed> $cycle Цикл жетонов.
     *
     * @return void
     */
    public function begin(array $pending, array $cycle = []): void
    {
        $this->reaction->begin($pending, $cycle);
        $this->flow->reset();
    }

    /**
     * Карта ожидания после расчёта.
     *
     * @return array<string, mixed> Карта.
     */
    public function pendingAfter(): array
    {
        return $this->reaction->pendingAfter();
    }

    /**
     * Цикл жетонов после расчёта.
     *
     * @return array<string, mixed> Карта.
     */
    public function cycleAfter(): array
    {
        return $this->reaction->cycleAfter();
    }

    /**
     * Был ли потрачен жетон концентрации.
     *
     * @return bool Признак.
     */
    public function tokenUsed(): bool
    {
        return $this->reaction->tokenUsed();
    }

    /**
     * Ключи меток, чьи защиты пошли в итог.
     *
     * @return array<int, string> Ключи.
     */
    public function markKeys(): array
    {
        return $this->flow->markKeys();
    }

    /**
     * Готовит реакцию цели и броски прикрывающих до применения.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param array<string, mixed> $row Строка цели.
     * @param array<string, mixed> $choice Защита.
     * @param string $hitCode Код проверки.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param string $profileType Профиль.
     * @param bool $isPush Толчок.
     * @param DimensionalNumber|null $shotDistance Дистанция выстрела.
     * @param array<int, array<string, mixed>> $marks Метки боя.
     *
     * @return array{reaction: array<string, mixed>, cover: array<string, mixed>|null} Подготовка.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если строка или карточка битые.
     */
    public function prepare(
        GameRecord $game, array $open, array $row, array $choice, string $hitCode,
        array $attackerRoll, string $profileType, bool $isPush,
        ?DimensionalNumber $shotDistance = null, array $marks = [],
    ): array {
        $target = GameStrikeTargetRow::defender($row);
        $conditions = GameStrikeTargetRow::conditions($row);
        $reaction = $this->reaction->reactionOf($game, $target, $choice, $conditions, $hitCode);

        return [
            'reaction' => $reaction,
            'cover' => $this->coverOf(
                $game, $open, $row, $choice, $reaction, $attackerRoll, $hitCode,
                $profileType, $isPush, $shotDistance, $marks, $conditions,
            ),
        ];
    }

    /**
     * Одна цель широкого удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param array<string, mixed> $row Строка цели.
     * @param array<string, mixed> $choice Защита.
     * @param string|null $refusalCode Non-CAS отказ.
     * @param DimensionalNumber $damage Урон.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param string $hitCode Код проверки.
     * @param callable(): DimensionalNumber $penetrationFactory Ленивое проникновение.
     * @param GameStrikeWriteScope $scope Права записи.
     * @param GameStrikeTargetOptions $options Дополнительные данные.
     *
     * @return array<string, mixed> Запись цели.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если строка или лист битые.
     */
    public function one(
        GameRecord $game, array $open, array $row, array $choice, ?string $refusalCode,
        DimensionalNumber $damage, array $attackerRoll, string $hitCode, callable $penetrationFactory,
        GameStrikeWriteScope $scope, GameStrikeTargetOptions $options,
    ): array {
        return $this->flow->one(
            $game, $open, $row, $choice, $refusalCode, $damage, $attackerRoll,
            $hitCode, $penetrationFactory, $scope, $options,
        );
    }

    /**
     * Прикрытие строки, если оно ещё не разрешено.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param array<string, mixed> $row Строка цели.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed> $reaction Реакция цели.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param string $hitCode Код проверки.
     * @param string $profileType Профиль.
     * @param bool $isPush Толчок.
     * @param DimensionalNumber|null $shotDistance Дистанция.
     * @param array<int, array<string, mixed>> $marks Метки боя.
     * @param array<string, mixed> $conditions Условия цели.
     *
     * @return array<string, mixed>|null Прикрытие.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если строка или лист битые.
     */
    private function coverOf(
        GameRecord $game, array $open, array $row, array $choice, array $reaction,
        array $attackerRoll, string $hitCode, string $profileType, bool $isPush,
        ?DimensionalNumber $shotDistance, array $marks, array $conditions,
    ): ?array {
        if ($reaction['insufficient'] || GameStrikeTargetRow::jsonRows($row, 'coverers') === []) {
            return null;
        }

        return $this->cover->resolve(
            $this->coverRun(
                $game, $open, $choice, $reaction, $attackerRoll, $hitCode,
                $profileType, $isPush, $shotDistance, $marks, $conditions,
            ),
            $row,
        );
    }

    /**
     * Вход расчёта прикрытия строки.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed> $reaction Реакция цели.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param string $hitCode Код проверки.
     * @param string $profileType Профиль.
     * @param bool $isPush Толчок.
     * @param DimensionalNumber|null $shotDistance Дистанция.
     * @param array<int, array<string, mixed>> $marks Метки боя.
     * @param array<string, mixed> $conditions Условия цели.
     *
     * @return GameStrikeCoverRun Вход расчёта.
     */
    private function coverRun(
        GameRecord $game, array $open, array $choice, array $reaction, array $attackerRoll,
        string $hitCode, string $profileType, bool $isPush, ?DimensionalNumber $shotDistance,
        array $marks, array $conditions,
    ): GameStrikeCoverRun {
        $run = new GameStrikeCoverRun();
        $run->game = $game;
        $run->open = $open;
        $run->conditions = $conditions;
        $run->choice = $choice;
        $run->attackerRoll = $attackerRoll;
        $run->hitCode = $hitCode;
        $run->slice = $reaction['slice'] instanceof CharacterRuleSlice
            ? $reaction['slice']
            : $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $run->pending = $this->reaction->pendingAfter();
        $run->targetPending = $reaction['pending'];
        $run->profileType = $profileType;
        $run->isPush = $isPush;
        $run->shotDistance = $shotDistance;
        $run->targetSheetVersion = is_int($choice['expectedSheetVersion'] ?? null)
            ? $choice['expectedSheetVersion']
            : 0;
        $run->marks = $marks;

        return $run;
    }

}
