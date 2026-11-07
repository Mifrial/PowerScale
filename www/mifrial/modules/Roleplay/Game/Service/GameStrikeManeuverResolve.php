<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Принимает выбор характеристики или решения об отказе.
 */
final class GameStrikeManeuverResolve
{
    /**
     * Создаёт приём решений окна.
     *
     * @param GameStrikeManeuverRow $row Разбор строки.
     * @param GameStrikeManeuverCheck $check Проверка пары.
     * @param GameStrikeManeuverRoll $roll Броски защитников.
     * @param GameStrikeManeuverState $state Ожидания и описание.
     * @param GameStrikeManeuverPersist $persist Запись окна.
     * @param GameStrikeManeuverDecisions $decisions Применение решений.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeManeuverRow $row,
        private readonly GameStrikeManeuverCheck $check,
        private readonly GameStrikeManeuverRoll $roll,
        private readonly GameStrikeManeuverState $state,
        private readonly GameStrikeManeuverPersist $persist,
        private readonly GameStrikeManeuverDecisions $decisions,
    ) {
    }

    /**
     * Принимает выбор характеристики или решения об отказе.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $row Строка удара.
     * @param array<string, mixed> $window Решение окна.
     * @param int $expectedBattleVersion Версия боя.
     * @param GameCheckRoll $checkRoll Бросок совместной проверки.
     *
     * @return array{closed: bool, version: int, awaiting?: array<int, array<string, mixed>>,
     *               maneuver?: array<string, mixed>, finish?: array<string, mixed>} Итог.
     *
     * @throws GameInvalidException Если окно или выбор чужие.
     */
    public function resolve(
        GameRecord $game,
        GameBattleRecord $battle,
        array $row,
        array $window,
        int $expectedBattleVersion,
        GameCheckRoll $checkRoll,
    ): array {
        $resolution = $this->row->resolutionOf($row);
        $targets = is_array($resolution['targets'] ?? null) ? $resolution['targets'] : [];
        $phase = $this->phase($resolution, $targets);
        $key = $this->key($targets, $window['actor'] ?? null);
        if ($phase === 'check') {
            $early = $this->checks($game, $resolution, $targets, $key, $window, $checkRoll, $battle, $row, $expectedBattleVersion);
            if ($early !== null) {
                return $early;
            }

            $phase = 'decision';
        } else {
            $this->decide($targets, $key, $window);
        }

        $resolution['targets'] = $targets;
        $resolution['phase'] = $phase;

        return $this->close($battle, $row, $resolution, $targets, $expectedBattleVersion);
    }

    /**
     * Сохраняет открытые решения или закрывает окно.
     *
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $row Строка.
     * @param array<string, mixed> $resolution Резолюция.
     * @param array<string, array<string, mixed>> $targets Защитники.
     * @param int $expectedBattleVersion Версия боя.
     *
     * @return array<string, mixed> Итог.
     */
    private function close(
        GameBattleRecord $battle,
        array $row,
        array $resolution,
        array $targets,
        int $expectedBattleVersion,
    ): array {
        if ($this->state->pendingDecisions($targets) !== []) {
            return $this->persist->persist($battle, $row, $resolution, $targets, 'decision', $expectedBattleVersion);
        }

        return $this->finish($battle, $row, $resolution, $targets, $expectedBattleVersion);
    }

    /**
     * Фаза открытого окна.
     *
     * @param array<string, mixed> $resolution Резолюция.
     * @param array<string, array<string, mixed>> $targets Защитники.
     *
     * @return string Фаза.
     *
     * @throws GameInvalidException Если окно закрыто.
     */
    private function phase(array $resolution, array $targets): string
    {
        $phase = is_string($resolution['phase'] ?? null) ? $resolution['phase'] : '';
        if ($targets === [] || !in_array($phase, ['check', 'decision'], true)) {
            throw new GameInvalidException('Game maneuver window is not open');
        }

        return $phase;
    }

    /**
     * Ключ защитника из окна.
     *
     * @param array<string, array<string, mixed>> $targets Защитники.
     * @param mixed $raw Актор окна.
     *
     * @return string Ключ.
     *
     * @throws GameInvalidException Если актор чужой.
     */
    private function key(array $targets, mixed $raw): string
    {
        $actor = $this->row->actorOf($raw);
        $key = $actor['type'] . ':' . $actor['id'];
        if (!is_array($targets[$key] ?? null)) {
            throw new GameInvalidException('Game maneuver actor is invalid');
        }

        return $key;
    }

    /**
     * Выбор пары и бросок, когда все пары выбраны.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $resolution Резолюция.
     * @param array<string, array<string, mixed>> $targets Защитники.
     * @param string $key Ключ защитника.
     * @param array<string, mixed> $window Решение окна.
     * @param GameCheckRoll $checkRoll Бросок.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $row Строка.
     * @param int $expectedBattleVersion Версия боя.
     *
     * @return array<string, mixed>|null Промежуточный итог.
     */
    private function checks(
        GameRecord $game,
        array $resolution,
        array &$targets,
        string $key,
        array $window,
        GameCheckRoll $checkRoll,
        GameBattleRecord $battle,
        array $row,
        int $expectedBattleVersion,
    ): ?array {
        $choice = $this->check->choice($window, $targets[$key]['options'] ?? []);
        $targets[$key] = ['check' => $choice] + $targets[$key];
        if ($this->state->pendingCheck($targets) !== []) {
            return $this->persist->persist($battle, $row, $resolution, $targets, 'check', $expectedBattleVersion);
        }

        $this->roll->roll($game, $resolution, $targets, $checkRoll);

        return null;
    }

    /**
     * Решение победителя об отказе или сохранении реакции.
     *
     * @param array<string, array<string, mixed>> $targets Защитники.
     * @param string $key Ключ защитника.
     * @param array<string, mixed> $window Решение окна.
     *
     * @return void
     *
     * @throws GameInvalidException Если решение чужое.
     */
    private function decide(array &$targets, string $key, array $window): void
    {
        $decision = $window['decision'] ?? null;
        if (!in_array($decision, ['keep', 'refuse'], true)) {
            throw new GameInvalidException('Game maneuver decision is invalid');
        }

        $targets[$key] = ['decision' => $decision] + $targets[$key];
    }

    /**
     * Закрывает окно и переносит решения в строку.
     *
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $row Строка.
     * @param array<string, mixed> $resolution Резолюция.
     * @param array<string, array<string, mixed>> $targets Защитники.
     * @param int $expectedBattleVersion Версия боя.
     *
     * @return array<string, mixed> Итог.
     */
    private function finish(
        GameBattleRecord $battle,
        array $row,
        array $resolution,
        array $targets,
        int $expectedBattleVersion,
    ): array {
        $resolution['phase'] = 'done';
        $resolution['awaiting'] = [];
        $row = $this->decisions->apply($row, $targets);
        $this->persist->save($row, $resolution);
        $saved = $this->persist->replace($battle, $expectedBattleVersion);

        return [
            'closed' => false,
            'version' => $saved->getStateVersion(),
            'maneuver' => $this->state->publicManeuver($resolution),
            'finish' => [
                'subjects' => $this->state->keepSubjects($targets),
                'marks' => is_array($resolution['maneuver']['marks'] ?? null)
                    ? $resolution['maneuver']['marks']
                    : [],
            ],
        ];
    }
}
