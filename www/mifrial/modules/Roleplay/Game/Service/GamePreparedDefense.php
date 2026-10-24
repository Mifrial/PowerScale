<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;

/**
 * Подготовка «Противодействовать защите»: запись и преимущество по реакции цели.
 */
final class GamePreparedDefense
{
    /**
     * Заменяет подготовку актора одной записью.
     *
     * @param array<string, mixed> $pending Карта ожидания.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     * @param string $targetKey Ключ цели.
     * @param string $reaction Реакция цели.
     * @param string $sourceRuleCode Карточка подготовки.
     *
     * @return array<string, mixed> Новая карта.
     */
    public function replace(
        array $pending,
        string $actorKind,
        int $actorId,
        string $targetKey,
        string $reaction,
        string $sourceRuleCode,
    ): array {
        $key = $actorKind . ':' . $actorId;
        $waiting = is_array($pending[$key] ?? null) ? $pending[$key] : [];
        $kept = $this->withoutPrepared($waiting);
        $kept[] = [
            'sourceRuleCode' => $sourceRuleCode,
            'effect' => [
                'type' => 'prepared_defense_counter',
                'targetKey' => $targetKey,
                'reaction' => $reaction,
            ],
        ];
        $pending[$key] = $kept;

        return $pending;
    }

    /**
     * Преимущество или помеха подготовки для удара по цели.
     *
     * @param array<string, mixed> $pending Карта ожидания.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     * @param string $targetKey Ключ цели.
     * @param string $reaction Фактическая реакция цели.
     *
     * @return RollAdvantage|null Запись или null.
     */
    public function modifier(
        array $pending,
        string $actorKind,
        int $actorId,
        string $targetKey,
        string $reaction,
    ): ?RollAdvantage {
        $prepared = $this->of($pending, $actorKind, $actorId);
        if ($prepared === null || ($prepared['targetKey'] ?? null) !== $targetKey) {
            return null;
        }

        $previous = $prepared['reaction'] ?? null;
        if (!is_string($previous)) {
            return null;
        }

        return new RollAdvantage('action', $previous === $reaction ? 1 : -1);
    }

    /**
     * Последняя подготовка актора.
     *
     * @param array<string, mixed> $pending Карта ожидания.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     *
     * @return array<string, mixed>|null Эффект или null.
     */
    private function of(array $pending, string $actorKind, int $actorId): ?array
    {
        $key = $actorKind . ':' . $actorId;
        $waiting = is_array($pending[$key] ?? null) ? $pending[$key] : [];
        foreach (array_reverse($waiting) as $entry) {
            $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
            if (is_array($effect) && ($effect['type'] ?? null) === 'prepared_defense_counter') {
                return $effect;
            }
        }

        return null;
    }

    /**
     * Снимает старые записи подготовки.
     *
     * @param array<int, mixed> $waiting Ожидание актора.
     *
     * @return array<int, mixed> Оставшиеся записи.
     */
    private function withoutPrepared(array $waiting): array
    {
        $kept = [];
        foreach ($waiting as $entry) {
            $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
            if (is_array($effect) && ($effect['type'] ?? null) === 'prepared_defense_counter') {
                continue;
            }

            $kept[] = $entry;
        }

        return $kept;
    }
}
