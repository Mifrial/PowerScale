<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

/**
 * Цикл жетонов в бою: тратил ли актор с конца предыдущего хода.
 */
final class GameConcentrationCycle
{
    /**
     * Состояние актора.
     *
     * @param array<string, mixed> $cycle Карта боя.
     * @param string $actorKey Ключ.
     *
     * @return array{used: bool} Состояние.
     */
    public function state(array $cycle, string $actorKey): array
    {
        $row = is_array($cycle[$actorKey] ?? null) ? $cycle[$actorKey] : [];

        return [
            'used' => ($row['used'] ?? false) === true,
        ];
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
        $cycle[$actorKey] = ['used' => false];

        return ['cycle' => $cycle, 'refill' => $refill];
    }
}
