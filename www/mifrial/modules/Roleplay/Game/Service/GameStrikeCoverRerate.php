<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

/**
 * Пересчёт броска атаки после броска защиты цели.
 */
final class GameStrikeCoverRerate
{
    /**
     * Создаёт пересчёт.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeResolution $resolution Расчёт бросков.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeResolution $resolution,
    ) {
    }

    /**
     * Пересчёт атаки против защиты.
     *
     * @param GameStrikeCoverRun $run Вход расчёта.
     * @param array<string, mixed> $open Строка удара.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed>|null $defenderRoll Бросок защиты.
     *
     * @return array<string, mixed> Бросок атаки.
     */
    public function of(
        GameStrikeCoverRun $run,
        array $open,
        array $choice,
        ?array $defenderRoll,
    ): array {
        if ($defenderRoll === null) {
            return $run->attackerRoll;
        }

        if ($choice['reaction'] === 'block' && $run->isPush) {
            return $this->rules->hitCheck->rerate($run->attackerRoll, $defenderRoll);
        }

        return $this->ranged($run->profileType)
            ? $this->rangedRerate($run, $open, $defenderRoll, (string) $choice['reaction'])
            : $this->meleeRerate($run, $choice, $defenderRoll);
    }

    /**
     * Пересчёт дальней атаки.
     *
     * @param GameStrikeCoverRun $run Вход расчёта.
     * @param array<string, mixed> $open Строка удара.
     * @param array<string, mixed> $defenderRoll Бросок защиты.
     * @param string $reaction Реакция.
     *
     * @return array<string, mixed> Бросок атаки.
     */
    private function rangedRerate(
        GameStrikeCoverRun $run,
        array $open,
        array $defenderRoll,
        string $reaction,
    ): array {
        return $this->resolution->defense->rerateRangedHit(
            $run->game,
            $open,
            $run->conditions,
            $run->attackerRoll,
            $defenderRoll,
            $reaction,
        );
    }

    /**
     * Пересчёт ближней атаки.
     *
     * @param GameStrikeCoverRun $run Вход расчёта.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed> $defenderRoll Бросок защиты.
     *
     * @return array<string, mixed> Бросок атаки.
     */
    private function meleeRerate(GameStrikeCoverRun $run, array $choice, array $defenderRoll): array
    {
        return $choice['reaction'] === 'dodge'
            ? $this->rules->hitCheck->rerate($run->attackerRoll, $defenderRoll)
            : $run->attackerRoll;
    }

    /**
     * Дальний профиль удара.
     *
     * @param string $profileType Профиль.
     *
     * @return bool true для стрельбы и броска.
     */
    private function ranged(string $profileType): bool
    {
        return $profileType === 'shoot' || $profileType === 'throw';
    }
}
