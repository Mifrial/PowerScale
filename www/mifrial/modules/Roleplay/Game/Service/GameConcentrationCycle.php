<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

/**
 * Цикл жетонов в бою: тратил ли актор и можно ли тратить после refill.
 */
final class GameConcentrationCycle
{
    /**
     * Состояние актора.
     *
     * @param array<string, mixed> $cycle Карта боя.
     * @param string $actorKey Ключ.
     *
     * @return array{used: bool, blocked: bool} Состояние.
     */
    public function state(array $cycle, string $actorKey): array
    {
        $row = is_array($cycle[$actorKey] ?? null) ? $cycle[$actorKey] : [];

        return [
            'used' => ($row['used'] ?? false) === true,
            'blocked' => ($row['blocked'] ?? false) === true,
        ];
    }

    /**
     * Можно ли тратить сейчас.
     *
     * @param array<string, mixed> $cycle Карта боя.
     * @param string $actorKey Ключ.
     *
     * @return bool Можно.
     */
    public function canSpend(array $cycle, string $actorKey): bool
    {
        return !$this->state($cycle, $actorKey)['blocked'];
    }

    /**
     * Отмечает трату в цикле.
     *
     * @param array<string, mixed> $cycle Карта боя.
     * @param string $actorKey Ключ.
     *
     * @return array<string, mixed> Карта.
     */
    public function markUsed(array $cycle, string $actorKey): array
    {
        $state = $this->state($cycle, $actorKey);
        $state['used'] = true;
        $cycle[$actorKey] = $state;

        return $cycle;
    }

    /**
     * Начало своего хода: снимает блок после refill.
     *
     * @param array<string, mixed> $cycle Карта боя.
     * @param string $actorKey Ключ.
     *
     * @return array<string, mixed> Карта.
     */
    public function beginTurn(array $cycle, string $actorKey): array
    {
        if (!array_key_exists($actorKey, $cycle)) {
            return $cycle;
        }

        $state = $this->state($cycle, $actorKey);
        $state['blocked'] = false;
        $cycle[$actorKey] = $state;

        return $cycle;
    }

    /**
     * Конец своего хода: refill только если не тратил.
     *
     * @param array<string, mixed> $cycle Карта боя.
     * @param string $actorKey Ключ.
     *
     * @return array{cycle: array<string, mixed>, refill: bool} Итог.
     */
    public function endTurn(array $cycle, string $actorKey): array
    {
        $state = $this->state($cycle, $actorKey);
        $refill = !$state['used'];
        $cycle[$actorKey] = [
            'used' => false,
            'blocked' => $refill,
        ];

        return ['cycle' => $cycle, 'refill' => $refill];
    }
}
