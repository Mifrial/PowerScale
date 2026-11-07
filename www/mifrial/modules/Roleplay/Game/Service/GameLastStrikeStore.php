<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

/**
 * Снимок прошлого удара в карте ожидания боя.
 */
final class GameLastStrikeStore
{
    /**
     * Заменяет снимок прошлого удара в карте ожидания.
     *
     * @param array<string, mixed> $pending Карта боя.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     * @param string $sourceRuleCode Карточка удара.
     * @param string $kind Вид lethal или other.
     * @param array<int, array<string, mixed>> $hits Удары.
     *
     * @return array<string, mixed> Новая карта.
     */
    public function store(
        array $pending,
        string $actorKind,
        int $actorId,
        string $sourceRuleCode,
        string $kind,
        array $hits,
    ): array {
        $key = $actorKind . ':' . $actorId;
        $waiting = is_array($pending[$key] ?? null) ? $pending[$key] : [];
        $kept = $this->withoutSnapshot($waiting);
        $kept[] = [
            'sourceRuleCode' => $sourceRuleCode,
            'effect' => [
                'type' => 'last_strike_snapshot',
                'kind' => $kind,
                'hits' => $hits,
            ],
        ];
        $pending[$key] = $kept;

        return $pending;
    }

    /**
     * Снимок прошлого удара актора.
     *
     * @param array<string, mixed> $pending Карта боя.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     *
     * @return array<string, mixed>|null Снимок или null.
     */
    public function snapshotOf(array $pending, string $actorKind, int $actorId): ?array
    {
        $key = $actorKind . ':' . $actorId;
        $waiting = is_array($pending[$key] ?? null) ? $pending[$key] : [];
        foreach (array_reverse($waiting) as $entry) {
            $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
            if (!is_array($effect) || ($effect['type'] ?? null) !== 'last_strike_snapshot') {
                continue;
            }

            return $effect;
        }

        return null;
    }

    /**
     * Успех прошлого удара по цели.
     *
     * @param array<string, mixed> $pending Карта боя.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     * @param string $targetKey Ключ цели.
     *
     * @return int Успех или 0.
     */
    public function previousSr(array $pending, string $actorKind, int $actorId, string $targetKey): int
    {
        $snapshot = $this->snapshotOf($pending, $actorKind, $actorId);
        if ($snapshot === null) {
            return 0;
        }

        foreach ($this->hitsOf($snapshot) as $hit) {
            if (($hit['targetKey'] ?? null) === $targetKey) {
                return is_int($hit['attackSr'] ?? null) ? $hit['attackSr'] : 0;
            }
        }

        return 0;
    }

    /**
     * Реакция цели в снимке прошлого удара.
     *
     * @param array<string, mixed> $pending Карта боя.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     * @param string $targetKey Ключ цели.
     *
     * @return string|null Реакция или null.
     */
    public function reactionOf(
        array $pending,
        string $actorKind,
        int $actorId,
        string $targetKey,
    ): ?string {
        $snapshot = $this->snapshotOf($pending, $actorKind, $actorId);
        if ($snapshot === null) {
            return null;
        }

        foreach ($this->hitsOf($snapshot) as $hit) {
            if (($hit['targetKey'] ?? null) !== $targetKey) {
                continue;
            }

            $reaction = $hit['reaction'] ?? null;

            return is_string($reaction) ? $reaction : null;
        }

        return null;
    }

    /**
     * Удары снимка списком.
     *
     * @param array<string, mixed> $snapshot Снимок.
     *
     * @return array<int, array<string, mixed>> Удары.
     */
    public function hitsOf(array $snapshot): array
    {
        $hits = $snapshot['hits'] ?? null;
        if (!is_array($hits)) {
            return [];
        }

        return array_values(array_filter($hits, static fn (mixed $hit): bool => is_array($hit)));
    }

    /**
     * Записи актора без прежнего снимка.
     *
     * @param array<int, mixed> $waiting Ожидание актора.
     *
     * @return array<int, mixed> Оставшиеся записи.
     */
    private function withoutSnapshot(array $waiting): array
    {
        $kept = [];
        foreach ($waiting as $entry) {
            $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
            if (is_array($effect) && ($effect['type'] ?? null) === 'last_strike_snapshot') {
                continue;
            }

            $kept[] = $entry;
        }

        return $kept;
    }
}
