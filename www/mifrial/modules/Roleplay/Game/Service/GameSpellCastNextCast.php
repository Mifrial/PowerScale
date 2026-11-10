<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameSpellCastNextCastSource;

/**
 * Хвост снижения Сложности следующего сотворения в ожидании боя.
 */
final class GameSpellCastNextCast
{
    use GameSpellCastAbilityLevelTrait;

    private const EFFECT = 'next_spell_cast_difficulty';

    /**
     * Изученные источники снижения Сложности.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param CharacterChoices $model Выборы кастера.
     * @param array<string, mixed> $abilityLevels Эффективные уровни.
     *
     * @return array<int, GameSpellCastNextCastSource> Источники.
     */
    public function sources(
        CharacterRuleSlice $slice,
        CharacterChoices $model,
        array $abilityLevels,
    ): array {
        $sources = [];
        $seen = [];
        foreach ($model->getAbilities() as $ability) {
            $code = $ability->getRuleCode();
            if (isset($seen[$code])) {
                continue;
            }

            $spec = $this->learnedAbility($slice, $abilityLevels, $ability);
            if ($spec === null) {
                continue;
            }

            $nextCast = $spec->getBase()->getNextCastDifficulty();
            if ($nextCast === null) {
                continue;
            }

            $seen[$code] = true;
            $sources[] = new GameSpellCastNextCastSource($code, $nextCast);
        }

        return $sources;
    }

    /**
     * Дельты подходящих записей без их снятия.
     *
     * @param array<string, mixed> $pending Карта ожидания.
     * @param string $actorKey Ключ кастера.
     * @param int $actionCost Стоимость этого сотворения.
     *
     * @return int Суммарная дельта.
     */
    public function peek(array $pending, string $actorKey, int $actionCost): int
    {
        $waiting = is_array($pending[$actorKey] ?? null) ? $pending[$actorKey] : [];
        $delta = 0;
        foreach ($waiting as $entry) {
            $effect = $this->effectOf($entry);
            if ($effect === null) {
                continue;
            }

            $max = $effect['max_total_action_cost'] ?? null;
            $value = $effect['delta'] ?? null;
            if (is_int($max) && is_int($value) && $actionCost <= $max) {
                $delta += $value;
            }
        }

        return $delta;
    }

    /**
     * Снимает все записи снижения Сложности у кастера.
     *
     * @param array<string, mixed> $pending Карта ожидания.
     * @param string $actorKey Ключ кастера.
     *
     * @return array<string, mixed> Новая карта.
     */
    public function consume(array $pending, string $actorKey): array
    {
        $waiting = is_array($pending[$actorKey] ?? null) ? $pending[$actorKey] : [];
        $kept = [];
        foreach ($waiting as $entry) {
            if ($this->effectOf($entry) === null) {
                $kept[] = $entry;
            }
        }

        if ($kept === []) {
            unset($pending[$actorKey]);

            return $pending;
        }

        $pending[$actorKey] = $kept;

        return $pending;
    }

    /**
     * Ставит новый хвост по источникам, прошедшим порог остатка.
     *
     * @param array<string, mixed> $pending Карта ожидания.
     * @param string $actorKey Ключ кастера.
     * @param array<int, GameSpellCastNextCastSource> $sources Источники.
     * @param int $actionCost Стоимость этого сотворения.
     * @param int $remainingRating Остаток успеха после насыщения.
     *
     * @return array<string, mixed> Новая карта.
     */
    public function queue(
        array $pending,
        string $actorKey,
        array $sources,
        int $actionCost,
        int $remainingRating,
    ): array {
        $waiting = is_array($pending[$actorKey] ?? null) ? $pending[$actorKey] : [];
        foreach ($sources as $source) {
            if ($remainingRating < $source->getMinRemainingRating()) {
                continue;
            }

            $effect = [
                'type' => self::EFFECT,
                'delta' => $source->getDelta(),
                'max_total_action_cost' => $actionCost,
            ];
            $sourceCode = $source->getSourceCode();
            if ($sourceCode !== null) {
                $effect['source_code'] = $sourceCode;
            }

            $waiting[] = ['sourceRuleCode' => $source->getRuleCode(), 'effect' => $effect];
        }

        if ($waiting === []) {
            unset($pending[$actorKey]);

            return $pending;
        }

        $pending[$actorKey] = $waiting;

        return $pending;
    }

    /**
     * Запись хвоста, если это снижение Сложности.
     *
     * @param mixed $entry Запись ожидания.
     *
     * @return array<string, mixed>|null Эффект или null.
     */
    private function effectOf(mixed $entry): ?array
    {
        $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
        if (!is_array($effect) || ($effect['type'] ?? null) !== self::EFFECT) {
            return null;
        }

        return $effect;
    }

    /**
     * Строки хвостов для ответа и чата.
     *
     * @param array<string, mixed> $pending Карта ожидания.
     * @param string $actorKey Ключ кастера.
     *
     * @return array<int, array<string, mixed>> Строки.
     */
    public function rows(array $pending, string $actorKey): array
    {
        $waiting = is_array($pending[$actorKey] ?? null) ? $pending[$actorKey] : [];
        $rows = [];
        foreach ($waiting as $entry) {
            $effect = $this->effectOf($entry);
            if ($effect === null) {
                continue;
            }

            $rows[] = [
                'sourceRuleCode' => is_array($entry) ? ($entry['sourceRuleCode'] ?? null) : null,
                'delta' => $effect['delta'] ?? null,
                'maxTotalActionCost' => $effect['max_total_action_cost'] ?? null,
                'sourceCode' => $effect['source_code'] ?? null,
            ];
        }

        return $rows;
    }
}
