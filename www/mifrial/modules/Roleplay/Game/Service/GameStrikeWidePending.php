<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameStrikeWideRun;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Ожидание атакующего и хвост прошлого действия после широкого удара.
 */
final class GameStrikeWidePending
{
    /**
     * Создаёт завершение ожидания.
     *
     * @param GameStrikeTargetChoices $choices Choices целей.
     * @param GameStrikeSpend $spend Списание.
     * @param GameStrikeTargetClose $targets Расчёт цели.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeTargetChoices $choices,
        private readonly GameStrikeSpend $spend,
        private readonly GameStrikeTargetClose $targets,
    ) {
    }

    /**
     * Собирает новую карту ожидания и хвост прошлого действия.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param array<string, mixed> $open Открытый удар.
     * @param array{choices: list<array<string, mixed>>, refusals: list<string|null>, prepared: array<int, array<string, mixed>>, resolved: array<int, array<string, mixed>>, covers: array<int, array<string, mixed>|null>, rows: list<array<string, mixed>>, early: array<string, mixed>|null} $prepared Подготовка.
     * @param list<array<string, mixed>> $results Итоги целей.
     * @param array{slice: \Mifrial\Roleplay\Character\Dto\CharacterRuleSlice, hitCode: string, rows: list<array<string, mixed>>, attackerRoll: array<string, mixed>} $opening Начало.
     * @param array<int, string> $targetKeys Ключи целей.
     * @param bool $skipAfterAction Пропуск последействия действия.
     *
     * @return array<string, mixed> Карта ожидания.
     *
     * @throws GameInvalidException Если атакующий битый.
     */
    public function pending(
        GameStrikeWideRun $run,
        array $open,
        array $prepared,
        array $results,
        array $opening,
        array $targetKeys,
        bool $skipAfterAction = false,
    ): array {
        $attackerKind = $open['attacker_kind'] ?? null;
        $attackerId = $open['attacker_id'] ?? null;
        if (!is_string($attackerKind) || !is_int($attackerId)) {
            throw new GameInvalidException('Game wide strike attacker is invalid');
        }

        $actionRuleCode = is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '';
        $slice = $opening['slice'];
        $pending = (new GamePendingActionCosts())->complete(
            $slice,
            $this->choices->attackerChoices($run->game, $open),
            $actionRuleCode,
            $attackerKind,
            $attackerId,
            $this->targets->pendingAfter(),
            ['action-points' => $this->spend->getActionAp($slice, $actionRuleCode, true)],
            $targetKeys,
            $skipAfterAction,
        );
        $lastStrike = new GameLastStrikeSnapshot();

        return $lastStrike->store(
            $pending,
            $attackerKind,
            $attackerId,
            $actionRuleCode,
            $lastStrike->kindOf($slice, $actionRuleCode),
            $this->hits($results, $prepared['rows']),
        );
    }

    /**
     * Попадания по целям для хвоста прошлого действия.
     *
     * @param list<array<string, mixed>> $results Итоги целей.
     * @param list<array<string, mixed>> $rows Строки.
     *
     * @return list<array<string, mixed>> Попадания.
     */
    private function hits(array $results, array $rows): array
    {
        $hits = [];
        foreach ($results as $index => $record) {
            $target = is_array($record['target'] ?? null) ? $record['target'] : null;
            if ($target === null || !is_string($target['type'] ?? null) || !is_int($target['id'] ?? null)) {
                continue;
            }

            $hits[] = [
                'targetKey' => $target['type'] . ':' . $target['id'],
                'attackSr' => is_int($record['success'] ?? null) ? $record['success'] : 0,
                'reaction' => is_string($rows[$index]['reaction'] ?? null) ? $rows[$index]['reaction'] : 'ignore',
                'damaged' => $this->damaged($record['injury'] ?? null),
            ];
        }

        return $hits;
    }

    /**
     * Есть ли урон в записи цели.
     *
     * @param mixed $injury Запись повреждения.
     *
     * @return bool Признак урона.
     */
    private function damaged(mixed $injury): bool
    {
        if (!is_array($injury)) {
            return false;
        }

        return (new DimensionalNumber(
            (int) ($injury['base'] ?? 0),
            (int) ($injury['size'] ?? 0),
        ))->toInteger() > 0;
    }
}
