<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

/**
 * Ожидания, публичное описание и уцелевшие защитники манёвра.
 */
final class GameStrikeManeuverState
{
    /**
     * Открытые выборы характеристики.
     *
     * @param array<string, array<string, mixed>> $targets Защитники.
     *
     * @return array<int, array<string, mixed>> Ожидания.
     */
    public function pendingCheck(array $targets): array
    {
        return $this->awaiting($targets, 'check');
    }

    /**
     * Открытые решения победителей.
     *
     * @param array<string, array<string, mixed>> $targets Защитники.
     *
     * @return array<int, array<string, mixed>> Ожидания.
     */
    public function pendingDecisions(array $targets): array
    {
        return $this->awaiting($targets, 'decision');
    }

    /**
     * Ожидания фазы.
     *
     * @param array<string, array<string, mixed>> $targets Защитники.
     * @param string $phase Фаза.
     *
     * @return array<int, array<string, mixed>> Ожидания.
     */
    public function awaiting(array $targets, string $phase): array
    {
        $rows = [];
        foreach ($targets as $target) {
            $row = $phase === 'check' ? $this->checkRow($target) : $this->decisionRow($target, $phase);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Публичное описание раскрытого манёвра.
     *
     * @param array<string, mixed> $resolution Резолюция.
     *
     * @return array<string, mixed> Описание.
     */
    public function publicManeuver(array $resolution): array
    {
        $targets = is_array($resolution['targets'] ?? null) ? $resolution['targets'] : [];

        return [
            'ruleCode' => is_string($resolution['maneuver']['ruleCode'] ?? null)
                ? $resolution['maneuver']['ruleCode']
                : '',
            'phase' => is_string($resolution['phase'] ?? null) ? $resolution['phase'] : '',
            'attacker' => is_array($resolution['attacker'] ?? null) ? $resolution['attacker'] : null,
            'targets' => $this->targetRows($targets, ($resolution['phase'] ?? 'check') !== 'check'),
        ];
    }

    /**
     * Ключи защитников, сохранивших реакцию.
     *
     * @param array<string, array<string, mixed>> $targets Защитники.
     *
     * @return array<int, array{type: string, id: int}> Пары.
     */
    public function keepSubjects(array $targets): array
    {
        $subjects = [];
        foreach ($targets as $target) {
            if (($target['decision'] ?? 'keep') === 'refuse') {
                continue;
            }

            $actor = $target['actor'] ?? null;
            if (is_array($actor) && is_string($actor['type'] ?? null) && is_int($actor['id'] ?? null)) {
                $subjects[] = ['type' => $actor['type'], 'id' => $actor['id']];
            }
        }

        return $subjects;
    }

    /**
     * Ожидание выбора характеристики.
     *
     * @param array<string, mixed> $target Защитник.
     *
     * @return array<string, mixed>|null Ожидание.
     */
    private function checkRow(array $target): ?array
    {
        if (is_array($target['check'] ?? null)) {
            return null;
        }

        return [
            'kind' => 'maneuver_reveal',
            'phase' => 'check',
            'actor' => $target['actor'],
            'options' => $target['options'],
        ];
    }

    /**
     * Ожидание решения победителя.
     *
     * @param array<string, mixed> $target Защитник.
     * @param string $phase Фаза.
     *
     * @return array<string, mixed>|null Ожидание.
     */
    private function decisionRow(array $target, string $phase): ?array
    {
        if ($phase !== 'decision' || ($target['won'] ?? false) !== true) {
            return null;
        }

        if (($target['decision'] ?? null) !== null) {
            return null;
        }

        return [
            'kind' => 'maneuver_reveal',
            'phase' => 'decision',
            'actor' => $target['actor'],
            'roll' => $target['roll'],
            'options' => [
                ['key' => 'keep'],
                ['key' => 'refuse'],
            ],
        ];
    }

    /**
     * Публичные строки защитников.
     *
     * @param array<string, array<string, mixed>> $targets Защитники.
     * @param bool $reveal Раскрывать проверки.
     *
     * @return array<string, array<string, mixed>> Строки.
     */
    private function targetRows(array $targets, bool $reveal): array
    {
        $rows = [];
        foreach ($targets as $key => $target) {
            $rows[$key] = [
                'actor' => $target['actor'] ?? null,
                'check' => $reveal ? ($target['check'] ?? null) : null,
                'roll' => $reveal ? ($target['roll'] ?? null) : null,
                'won' => $reveal ? ($target['won'] ?? null) : null,
                'decision' => $target['decision'] ?? null,
            ];
        }

        return $rows;
    }
}
