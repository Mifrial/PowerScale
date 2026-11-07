<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

/**
 * Описание прикрытия строки удара для чата.
 */
final class GameStrikeCoverInfo
{
    /**
     * Собирает описание прикрытия для чата.
     *
     * @param array<string, mixed> $candidates Кандидаты защит.
     * @param string $primaryKey Ключ исходной цели.
     * @param string|null $winnerKey Ключ победителя.
     *
     * @return array<string, mixed>|null Описание или null.
     */
    public function info(array $candidates, string $primaryKey, ?string $winnerKey): ?array
    {
        $primary = $candidates[$primaryKey] ?? null;
        if (!is_array($primary)) {
            return null;
        }

        $target = $this->target($primary);
        if ($target === null) {
            return null;
        }

        return [
            'target' => $target['actor'],
            'targetReaction' => $target['reaction'],
            'targetReactionRuleCode' => $target['reactionRuleCode'],
            'targetRoll' => $target['roll'],
            'targetActual' => $winnerKey === $primaryKey,
            'attempts' => $this->attempts($candidates, $primaryKey, $winnerKey),
        ];
    }

    /**
     * Подписи исходной цели.
     *
     * @param array<string, mixed> $primary Кандидат исходной цели.
     *
     * @return array{
     *     actor: array{type: string, id: int},
     *     reaction: string,
     *     reactionRuleCode: string|null,
     *     roll: array<string, mixed>|null
     * }|null Подписи или null.
     */
    private function target(array $primary): ?array
    {
        $actor = GameStrikeTargetRow::pair($primary['actor'] ?? null);
        $choice = $primary['choice'] ?? null;
        if ($actor === null || !is_array($choice)) {
            return null;
        }

        return [
            'actor' => $actor,
            'reaction' => is_string($choice['reaction'] ?? null) ? $choice['reaction'] : 'ignore',
            'reactionRuleCode' => is_string($primary['reactionRuleCode'] ?? null)
                ? $primary['reactionRuleCode']
                : null,
            'roll' => is_array($primary['defenderRoll'] ?? null) ? $primary['defenderRoll'] : null,
        ];
    }

    /**
     * Описания попыток прикрытия, кроме исходной цели.
     *
     * @param array<string, mixed> $candidates Кандидаты.
     * @param string $primaryKey Ключ исходной цели.
     * @param string|null $winnerKey Ключ победителя.
     *
     * @return list<array<string, mixed>> Попытки.
     */
    private function attempts(array $candidates, string $primaryKey, ?string $winnerKey): array
    {
        $attempts = [];
        foreach ($candidates as $key => $candidate) {
            $attempt = $this->attempt($key, $candidate, $primaryKey, $winnerKey);
            if ($attempt !== null) {
                $attempts[] = $attempt;
            }
        }

        return $attempts;
    }

    /**
     * Описание одной попытки прикрытия.
     *
     * @param string $key Ключ кандидата.
     * @param mixed $candidate Кандидат.
     * @param string $primaryKey Ключ исходной цели.
     * @param string|null $winnerKey Ключ победителя.
     *
     * @return array<string, mixed>|null Описание или null.
     */
    private function attempt(string $key, mixed $candidate, string $primaryKey, ?string $winnerKey): ?array
    {
        if ($key === $primaryKey || !is_array($candidate)) {
            return null;
        }

        $actor = GameStrikeTargetRow::pair($candidate['actor'] ?? null);
        $choice = $candidate['choice'] ?? null;
        if ($actor === null || !is_array($choice)) {
            return null;
        }

        return [
            'key' => $key,
            'actor' => $actor,
            'ruleCode' => is_string($candidate['ruleCode'] ?? null) ? $candidate['ruleCode'] : '',
            'reaction' => is_string($choice['reaction'] ?? null) ? $choice['reaction'] : '',
            'blockItemRuleCode' => is_string($choice['blockItemRuleCode'] ?? null)
                ? $choice['blockItemRuleCode']
                : '',
            'cost' => is_int($candidate['cost'] ?? null) ? $candidate['cost'] : 0,
            'roll' => is_array($candidate['defenderRoll'] ?? null) ? $candidate['defenderRoll'] : null,
            'passed' => ($candidate['passed'] ?? false) === true,
            'winner' => $winnerKey !== null && $key === $winnerKey,
        ];
    }

}
