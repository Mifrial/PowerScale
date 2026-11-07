<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use JsonException;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Значения строк удара и трат: пары, вложения, суммы.
 */
final class GameActionValue
{
    /**
     * Строка широкого удара по выбранной цели.
     *
     * @param list<array<string, mixed>> $strikes Строки удара.
     * @param array<string, mixed> $context Контекст реакции.
     *
     * @return array<string, mixed>|null Строка или null.
     */
    public function rowOfTarget(array $strikes, array $context): ?array
    {
        $target = is_array($context['target'] ?? null) ? $context['target'] : null;
        if ($target === null || !is_string($target['type'] ?? null) || !is_int($target['id'] ?? null)) {
            return null;
        }

        foreach ($strikes as $row) {
            if (
                ($row['target_type'] ?? null) === $target['type']
                && ($row['target_id'] ?? null) === $target['id']
            ) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Уже объявленные записи строки удара.
     *
     * @param array<string, mixed> $row Строка удара.
     * @param string $key Колонка.
     *
     * @return list<array<string, mixed>> Записи.
     */
    public function attachments(array $row, string $key): array
    {
        $raw = $row[$key] ?? null;
        if (is_string($raw)) {
            try {
                $raw = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return [];
            }
        }

        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter($raw, static fn (mixed $entry): bool => is_array($entry)));
    }

    /**
     * Пара участника удара из колонок.
     *
     * @param mixed $type Вид.
     * @param mixed $id Идентификатор.
     *
     * @return array{type: string, id: int}|null Пара или null.
     */
    public function strikeParticipant(mixed $type, mixed $id): ?array
    {
        if (($type !== 'character' && $type !== 'npc') || !is_int($id) || $id < 1) {
            return null;
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * Суммы трат по ресурсам.
     *
     * @param array<int, ResourceSpend> $spends Траты.
     *
     * @return array<string, int> Суммы.
     */
    public function amountsOf(array $spends): array
    {
        $amounts = [];
        foreach ($spends as $spend) {
            $native = $spend->getAmount()->toNative();
            if (!is_int($native)) {
                continue;
            }

            $code = $spend->getResourceCode();
            $amounts[$code] = ($amounts[$code] ?? 0) + $native;
        }

        return $amounts;
    }

    /**
     * Сумма ОД в тратах.
     *
     * @param array<int, ResourceSpend> $spends Траты.
     *
     * @return int Сумма.
     */
    public function spentOf(array $spends): int
    {
        $sum = 0;
        foreach ($spends as $spend) {
            if ($spend->getResourceCode() !== 'action-points') {
                continue;
            }

            $native = $spend->getAmount()->toNative();
            if (is_int($native)) {
                $sum += $native;
            }
        }

        return $sum;
    }

    /**
     * Пара type/id из ключа цели.
     *
     * @param string $key Ключ character:N или npc:N.
     *
     * @return array{type: string, id: int} Пара.
     *
     * @throws GameInvalidException Если ключ чужой.
     */
    public function targetOfKey(string $key): array
    {
        $parts = explode(':', $key, 2);
        $type = $parts[0];
        $rawId = $parts[1] ?? null;
        $id = is_string($rawId) && ctype_digit($rawId) ? (int) $rawId : 0;
        if (($type !== 'character' && $type !== 'npc') || $id < 1) {
            throw new GameInvalidException('Game action target is invalid');
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * Идентификатор нити удара.
     *
     * @param array<string, mixed> $target Цель реакции.
     *
     * @return string Идентификатор.
     */
    public function chatId(array $target): string
    {
        $strikeId = is_int($target['open']['id'] ?? null) ? $target['open']['id'] : $target['strikeId'];

        return ($target['wide'] ? 'attack-wide-' : 'attack-') . $strikeId;
    }
}