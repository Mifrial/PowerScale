<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;

/**
 * Метки боя: временные модификаторы, переживающие один удар.
 *
 * В v1 единственный вид — `advantage`: знаковая дельта кубов, которая
 * отдаётся защите как обычный RollAdvantage. Метка привязана к субъекту и
 * области действия, тратится на первой реальной защите и снимается в конце
 * хода атакующего. Наложения нет: одна метка на (subject, kind, scope).
 */
final class GameBattleMarks
{
    /**
     * Разбирает колонку marks, отбрасывая не-записи.
     *
     * @param mixed $raw Значение колонки.
     *
     * @return array<int, array<string, mixed>> Список записей.
     */
    public function decode(mixed $raw): array
    {
        if (!is_array($raw) || !array_is_list($raw)) {
            return [];
        }

        $marks = [];
        foreach ($raw as $entry) {
            if (is_array($entry)) {
                $marks[] = $entry;
            }
        }

        return $marks;
    }

    /**
     * Приводит метку к канонической форме.
     *
     * @param array<string, mixed> $mark Метка.
     *
     * @return array<string, mixed> Метка.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    public function normalize(array $mark): array
    {
        return GameBattleMark::fromArray($mark)->toArray();
    }

    /**
     * Кладёт метку в список: повторная запись того же ключа обновляет её.
     *
     * @param array<int, mixed> $marks Метки.
     * @param array<string, mixed> $mark Новая метка.
     *
     * @return array<int, array<string, mixed>> Метки.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    public function upsert(array $marks, array $mark): array
    {
        $mark = GameBattleMark::fromArray($mark);
        $result = [];
        $replaced = false;
        foreach ($marks as $entry) {
            $current = $this->markOrNull($entry);
            if ($current === null) {
                continue;
            }

            if ($current->key() === $mark->key()) {
                $result[] = $mark->toArray();
                $replaced = true;
                continue;
            }

            $result[] = $current->toArray();
        }

        if (!$replaced) {
            $result[] = $mark->toArray();
        }

        return $result;
    }

    /**
     * Преимущества защиты субъекта против атакующего и ключи применённых меток.
     *
     * Метки не списываются: вызывающий решает, пошёл ли этот бросок в итог.
     *
     * @param array<int, mixed> $marks Метки боя.
     * @param array{type: string, id: int} $subject Защищающийся.
     * @param array{type: string, id: int} $attacker Атакующий.
     *
     * @return array{advantages: array<int, RollAdvantage>, keys: array<int, string>} Итог.
     */
    public function forDefense(array $marks, array $subject, array $attacker): array
    {
        $subjectKey = $this->actorKey($subject);
        $attackerKey = $this->actorKey($attacker);
        $advantages = [];
        $keys = [];
        foreach ($marks as $entry) {
            $mark = $this->markOrNull($entry);
            if ($mark === null || $mark->subjectKey() !== $subjectKey) {
                continue;
            }

            if ($mark->defenseKey() !== $attackerKey) {
                continue;
            }

            $advantages[] = $mark->advantage();
            $keys[] = $mark->key();
        }

        return ['advantages' => $advantages, 'keys' => $keys];
    }

    /**
     * Снимает метки по ключам.
     *
     * @param array<int, mixed> $marks Метки.
     * @param array<int, string> $keys Ключи.
     *
     * @return array<int, array<string, mixed>> Метки.
     */
    public function consume(array $marks, array $keys): array
    {
        if ($keys === []) {
            return $marks;
        }

        $remove = array_fill_keys($keys, true);
        $result = [];
        foreach ($marks as $entry) {
            $mark = $this->markOrNull($entry);
            if ($mark === null || isset($remove[$mark->key()])) {
                continue;
            }

            $result[] = $mark->toArray();
        }

        return $result;
    }

    /**
     * Снимает метки, срок которых истёк в конце хода актора.
     *
     * @param array<int, mixed> $marks Метки.
     * @param array{type: string, id: int} $actor Актор, чей ход закончился.
     *
     * @return array<int, array<string, mixed>> Метки.
     */
    public function expireTurn(array $marks, array $actor): array
    {
        $actorKey = $this->actorKey($actor);
        $result = [];
        foreach ($marks as $entry) {
            $mark = $this->markOrNull($entry);
            if ($mark === null || $mark->expiresKey() === $actorKey) {
                continue;
            }

            $result[] = $mark->toArray();
        }

        return $result;
    }

    /**
     * Ключ наложения: субъект, вид и область.
     *
     * @param array<string, mixed> $mark Нормализованная метка.
     *
     * @return string Ключ.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    public function keyOf(array $mark): string
    {
        return GameBattleMark::fromArray($mark)->key();
    }

    /**
     * Нормализованная запись или null, если она битая.
     *
     * @param mixed $entry Запись.
     *
     * @return GameBattleMark|null Метка или null.
     */
    private function markOrNull(mixed $entry): ?GameBattleMark
    {
        if (!is_array($entry)) {
            return null;
        }

        try {
            return GameBattleMark::fromArray($entry);
        } catch (GameInvalidException) {
            return null;
        }
    }

    /**
     * Ключ актора.
     *
     * @param array{type: string, id: int} $actor Пара.
     *
     * @return string Ключ.
     */
    private function actorKey(array $actor): string
    {
        return $actor['type'] . ':' . $actor['id'];
    }
}
