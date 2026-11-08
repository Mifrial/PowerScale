<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Выбор эффекта объявленного манёвра по итоговому РУ удара.
 *
 * Усиление подменяет эффекты базового манёвра: селекторы `replaces` убирают
 * совпавшие эффекты, затем добавляются эффекты усиления. Пустой `replaces`
 * означает «ничего не убирать»: эффекты усиления добавляются к базовым.
 */
final class GameStrikeManeuverResolution
{
    /**
     * Эффекты базового манёвра или его усиления.
     *
     * @param array<string, mixed> $maneuver Снимок манёвра из строки удара.
     * @param int $success Итоговый РУ удара.
     *
     * @return array{
     *     ruleCode: string,
     *     boosted: bool,
     *     effects: list<array{kind: string, state: string, amount: int}>
     * }|null Выбранные эффекты или null, если порог не достигнут.
     *
     * @throws GameInvalidException Если снимок манёвра битый.
     */
    public function select(array $maneuver, int $success): ?array
    {
        if ($maneuver === [] || $success <= 0) {
            return null;
        }

        $ruleCode = $maneuver['ruleCode'] ?? null;
        $minSuccess = $maneuver['minSuccess'] ?? null;
        if (!is_string($ruleCode) || !is_int($minSuccess)) {
            throw new GameInvalidException('Game strike maneuver is invalid');
        }

        $base = $this->effects($maneuver['effects'] ?? null);
        $boost = is_array($maneuver['boost'] ?? null) ? $maneuver['boost'] : null;
        if ($boost !== null) {
            $boostSuccess = $boost['minSuccess'] ?? null;
            $boostCode = $boost['ruleCode'] ?? null;
            if (!is_int($boostSuccess) || !is_string($boostCode)) {
                throw new GameInvalidException('Game strike maneuver boost is invalid');
            }

            $boostEffects = $this->effects($boost['effects'] ?? null);
            if ($success >= $boostSuccess && $boostEffects !== []) {
                $effects = $this->replace($base, $boost['replaces'] ?? null, $boostEffects);

                return ['ruleCode' => $ruleCode, 'boosted' => true, 'effects' => $effects];
            }
        }

        if ($success < $minSuccess || $base === []) {
            return null;
        }

        return ['ruleCode' => $ruleCode, 'boosted' => false, 'effects' => $base];
    }

    /**
     * Подменяет базовые эффекты эффектами усиления.
     *
     * @param list<array{kind: string, state: string, amount: int}> $base Базовые эффекты.
     * @param mixed $replaces Селекторы замены.
     * @param list<array{kind: string, state: string, amount: int}> $effects Новые эффекты.
     *
     * @return list<array{kind: string, state: string, amount: int}> Итоговые эффекты.
     *
     * @throws GameInvalidException Если селектор битый.
     */
    private function replace(array $base, mixed $replaces, array $effects): array
    {
        if (!is_array($replaces) || $replaces === []) {
            return [...$base, ...$effects];
        }

        $selectors = [];
        foreach ($replaces as $selector) {
            $kind = is_array($selector) ? ($selector['kind'] ?? null) : null;
            $state = is_array($selector) ? ($selector['state'] ?? null) : null;
            if (!is_string($kind) || !is_string($state) || $kind === '' || $state === '') {
                throw new GameInvalidException('Game strike maneuver boost replace is invalid');
            }

            $selectors[] = ['kind' => $kind, 'state' => $state];
        }

        $kept = [];
        foreach ($base as $effect) {
            if ($this->matchesAny($effect, $selectors)) {
                continue;
            }

            $kept[] = $effect;
        }

        return [...$kept, ...$effects];
    }

    /**
     * Эффект совпал с одним из селекторов.
     *
     * @param array{kind: string, state: string, amount: int} $effect Эффект.
     * @param list<array{kind: string, state: string}> $selectors Селекторы.
     *
     * @return bool Истина, если совпал.
     */
    private function matchesAny(array $effect, array $selectors): bool
    {
        foreach ($selectors as $selector) {
            if ($effect['kind'] === $selector['kind'] && $effect['state'] === $selector['state']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Типизированные эффекты снимка.
     *
     * @param mixed $rows Сырое поле effects.
     *
     * @return list<array{kind: string, state: string, amount: int}> Эффекты.
     *
     * @throws GameInvalidException Если эффект битый.
     */
    private function effects(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $effects = [];
        foreach ($rows as $row) {
            $kind = is_array($row) ? ($row['kind'] ?? null) : null;
            $state = is_array($row) ? ($row['state'] ?? null) : null;
            $amount = is_array($row) ? ($row['amount'] ?? null) : null;
            if (!is_string($kind) || !is_string($state) || $state === '' || !is_int($amount) || $amount < 1) {
                throw new GameInvalidException('Game strike maneuver effect is invalid');
            }

            $effects[] = ['kind' => $kind, 'state' => $state, 'amount' => $amount];
        }

        return $effects;
    }
}
