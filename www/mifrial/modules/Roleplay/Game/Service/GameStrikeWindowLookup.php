<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;

/**
 * Открытая строка удара и её ожидания.
 */
final class GameStrikeWindowLookup
{
    /**
     * Создаёт поиск окна.
     *
     * @param GameActionRepository $actions Действия и цели.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActionRepository $actions,
    ) {
    }

    /**
     * Открытый одиночный или широкий удар.
     *
     * @param int $battleId Бой.
     *
     * @return array{open: array<string, mixed>, strikes: list<array<string, mixed>>, wide: bool} Окно.
     *
     * @throws GameInvalidException Если окно не открыто.
     */
    public function open(int $battleId): array
    {
        $open = $this->actions->findOpenWithStrikes($battleId, 'strike');
        $strikes = is_array($open['strikes'] ?? null) ? $open['strikes'] : [];
        if ($open !== null && count($strikes) === 1) {
            return ['open' => $open, 'strikes' => array_values($strikes), 'wide' => false];
        }

        $open = $this->actions->findOpenWithStrikes($battleId, 'wide_strike');
        $strikes = is_array($open['strikes'] ?? null) ? $open['strikes'] : [];
        if ($open === null || $strikes === []) {
            throw new GameInvalidException('Game strike window is not open');
        }

        return ['open' => $open, 'strikes' => array_values($strikes), 'wide' => true];
    }

    /**
     * Открыто ли окно раскрытия манёвра.
     *
     * @param array<string, mixed> $row Строка удара.
     *
     * @return bool Признак.
     */
    public function isManeuverOpen(array $row): bool
    {
        $resolution = $row['resolution'] ?? null;
        if (is_string($resolution)) {
            $resolution = json_decode($resolution, true);
        }

        if (!is_array($resolution) || !in_array($resolution['phase'] ?? null, ['check', 'decision'], true)) {
            return false;
        }

        foreach (is_array($resolution['awaiting'] ?? null) ? $resolution['awaiting'] : [] as $entry) {
            if (is_array($entry) && ($entry['kind'] ?? null) === 'maneuver_reveal') {
                return true;
            }
        }

        return false;
    }

    /**
     * Ожидания строки удара.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return list<array<string, mixed>> Ожидания.
     */
    public function awaitingOfRow(array $row): array
    {
        $resolution = $row['resolution'] ?? null;
        if (is_string($resolution)) {
            $resolution = json_decode($resolution, true);
        }

        $awaiting = is_array($resolution) && is_array($resolution['awaiting'] ?? null)
            ? $resolution['awaiting']
            : [];

        return array_values(array_filter($awaiting, static fn (mixed $entry): bool => is_array($entry)));
    }

    /**
     * Выбор входит в ожидания.
     *
     * @param list<array<string, mixed>> $awaiting Ожидания.
     * @param string $target Ключ цели.
     *
     * @return bool Признак.
     */
    public function optionAllowed(array $awaiting, string $target): bool
    {
        foreach ($awaiting as $entry) {
            if (($entry['kind'] ?? null) === 'actual_target' && $this->hasOption($entry, $target)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Есть ли ключ среди опций ожидания.
     *
     * @param array<string, mixed> $entry Ожидание.
     * @param string $target Ключ цели.
     *
     * @return bool Признак.
     */
    private function hasOption(array $entry, string $target): bool
    {
        foreach (is_array($entry['options'] ?? null) ? $entry['options'] : [] as $option) {
            if (is_array($option) && ($option['key'] ?? null) === $target) {
                return true;
            }
        }

        return false;
    }
}
