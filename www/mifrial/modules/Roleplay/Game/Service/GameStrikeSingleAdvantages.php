<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameStrikeSingleRun;
use Mifrial\Roleplay\Mechanic\Constant\AdvantageSourceLabels;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;

/**
 * Преимущества и броски одиночного удара.
 */
final class GameStrikeSingleAdvantages
{
    /**
     * Создаёт расчёт преимуществ.
     *
     * @param GameStrikeSupport $support Поддержки строки.
     * @param GameStrikeResolution $resolution Расчёт.
     * @param GameProcessFlow $processes Сессия процесса.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeSupport $support,
        private readonly GameStrikeResolution $resolution,
        private readonly GameProcessFlow $processes,
    ) {
    }

    /**
     * Собирает преимущества и бросок атаки.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    public function prepare(GameStrikeSingleRun $run): void
    {
        $snapshot = new GameLastStrikeSnapshot();
        $run->previousSr = $snapshot->previousSr(
            $run->pending,
            $run->attackerKind,
            $run->attackerId,
            $run->targetKey,
        );
        $run->supports = $this->support->advantages($run->row);
        $run->advantages = $this->advantages($run);
        $run->flank = $this->flank($run);
        $this->roll($run);
        $run->coverers = $this->jsonRows($run->row, 'coverers');
        $run->coverInfo = $run->resumeState !== null && is_array($run->resumeState['cover'] ?? null)
            ? $run->resumeState['cover']
            : null;
    }

    /**
     * Преимущества атаки в порядке применения.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return list<RollAdvantage> Преимущества.
     */
    private function advantages(GameStrikeSingleRun $run): array
    {
        $supports = $run->supports['attacker'] ?? null;
        $advantages = is_array($supports) ? $supports : [];
        $token = $this->tokenAdvantage($run->row, $run->slice);
        if ($token !== null) {
            $advantages[] = $token;
        }

        $advantages = $this->package($run, $advantages);
        $advantages = $this->repeat($run, $advantages);
        $advantages = $this->multi($run, $advantages);

        return $this->prepared($run, $advantages);
    }

    /**
     * Преимущество карточки действия.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param list<RollAdvantage> $advantages Преимущества.
     *
     * @return list<RollAdvantage> Преимущества.
     */
    private function package(GameStrikeSingleRun $run, array $advantages): array
    {
        $run->strikePackage = $this->processes->strike->strikePackage($run->process);
        if ($run->strikePackage['advantage'] !== 0) {
            $advantages[] = new RollAdvantage(
                'action',
                $run->strikePackage['advantage'],
                AdvantageSourceLabels::getLabel('action'),
            );
        }

        return $advantages;
    }

    /**
     * Штраф за повтор оружия.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param list<RollAdvantage> $advantages Преимущества.
     *
     * @return list<RollAdvantage> Преимущества.
     */
    private function repeat(GameStrikeSingleRun $run, array $advantages): array
    {
        $repeatUses = is_array($run->process) && is_int($run->process['prior'] ?? null) ? $run->process['prior'] : 0;
        if ($repeatUses > 0) {
            $advantages[] = new RollAdvantage(
                'circumstances',
                -$repeatUses,
                AdvantageSourceLabels::getLabel('circumstances'),
            );
        }

        return $advantages;
    }

    /**
     * Штраф за несколько целей.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param list<RollAdvantage> $advantages Преимущества.
     *
     * @return list<RollAdvantage> Преимущества.
     */
    private function multi(GameStrikeSingleRun $run, array $advantages): array
    {
        if ($run->packets > 1) {
            $advantages[] = new RollAdvantage(
                'multi_attack',
                -$run->packets,
                AdvantageSourceLabels::getLabel('multi_attack'),
            );
        } elseif ($run->sequential) {
            $advantages[] = new RollAdvantage(
                'multi_attack',
                -1,
                AdvantageSourceLabels::getLabel('multi_attack'),
            );
        }

        return $advantages;
    }

    /**
     * Преимущество подготовленной защиты.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param list<RollAdvantage> $advantages Преимущества.
     *
     * @return list<RollAdvantage> Преимущества.
     */
    private function prepared(GameStrikeSingleRun $run, array $advantages): array
    {
        $prepared = (new GamePreparedDefense())->modifier(
            $run->pending,
            $run->attackerKind,
            $run->attackerId,
            $run->targetKey,
            $run->choice['reaction'],
        );
        if ($prepared !== null) {
            $advantages[] = $prepared;
        }

        return $advantages;
    }

    /**
     * Фланг для защиты.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return RollAdvantage|null Преимущество.
     */
    private function flank(GameStrikeSingleRun $run): ?RollAdvantage
    {
        return ($run->conditions['flank'] ?? false) === true && !$run->turn
            ? new RollAdvantage('flank', -2, AdvantageSourceLabels::getLabel('flank'))
            : null;
    }

    /**
     * Бросок атаки и восстановление защиты окна.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function roll(GameStrikeSingleRun $run): void
    {
        $run->defenderRoll = null;
        $run->markKeys = $this->stringList($run->resumeState['markKeys'] ?? null);
        $run->pushChecks = [];
        $run->attackerRoll = $run->resumeState !== null
            ? $run->resumeState['attackerRoll']
            : $this->resolution->rolls->rollOf(
                $run->input->game,
                $run->open,
                $run->hitCode,
                $run->pending,
                $run->slice,
                $run->advantages,
                [$run->targetKey],
                $this->tokenAccuracy($run->row),
            );
        $run->defenseRolled = $run->resumeState !== null;
        if ($run->resumeState !== null) {
            $run->defenderRoll = is_array($run->resumeState['defenderRoll'] ?? null)
                ? $run->resumeState['defenderRoll']
                : null;
        }
    }

    /**
     * Преимущество от жетонов концентрации.
     *
     * @param array<string, mixed> $row Строка удара.
     * @param CharacterRuleSlice $slice Срез правил.
     *
     * @return RollAdvantage|null Преимущество.
     */
    private function tokenAdvantage(array $row, CharacterRuleSlice $slice): ?RollAdvantage
    {
        $raw = $row['token_spend'] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (!is_array($raw) || ($raw['mode'] ?? null) !== 'advantage') {
            return null;
        }

        $amount = $raw['amount'] ?? null;
        $source = $raw['source'] ?? null;
        if (!is_int($amount) || $amount < 1 || !is_string($source) || $source === '') {
            return null;
        }

        return new RollAdvantage($source, $amount, $slice->findLive($source)?->getName());
    }

    /**
     * Точность от жетонов карточки Расчётливой атаки.
     *
     * @param array<string, mixed> $row Строка удара.
     *
     * @return int Точность.
     */
    private function tokenAccuracy(array $row): int
    {
        $raw = $row['token_spend'] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (!is_array($raw) || ($raw['mode'] ?? null) !== 'accuracy') {
            return 0;
        }

        return is_int($raw['amount'] ?? null) && $raw['amount'] > 0 ? $raw['amount'] : 0;
    }

    /**
     * JSON-список записей строки удара.
     *
     * @param array<string, mixed> $row Строка удара.
     * @param string $key Колонка.
     *
     * @return list<array<string, mixed>> Записи.
     */
    private function jsonRows(array $row, string $key): array
    {
        $raw = $row[$key] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter($raw, static fn (mixed $entry): bool => is_array($entry)));
    }

    /**
     * Список строк из недоверенного значения.
     *
     * @param mixed $raw Значение.
     *
     * @return array<int, string> Строки.
     */
    private function stringList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $rows = [];
        foreach ($raw as $row) {
            if (is_string($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
