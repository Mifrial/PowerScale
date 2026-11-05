<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Mechanic\Constant\AdvantageSourceLabels;
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
    private const KIND_ADVANTAGE = 'advantage';

    private const CONSUMES_FIRST_DEFENSE = 'first_defense';

    private const EXPIRES_TURN = 'turn';

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
        $allowed = ['subject', 'kind', 'value', 'source_code', 'source_rule_code', 'scope', 'consumes', 'expires'];
        $keys = array_keys($mark);
        sort($keys);
        $expected = $allowed;
        sort($expected);
        if ($keys !== $expected) {
            throw new GameInvalidException('Game battle mark is invalid');
        }

        $kind = $mark['kind'] ?? null;
        if ($kind !== self::KIND_ADVANTAGE) {
            throw new GameInvalidException('Game battle mark kind is invalid');
        }

        $value = $mark['value'] ?? null;
        if (!is_int($value) || $value === 0) {
            throw new GameInvalidException('Game battle mark value is invalid');
        }

        $sourceCode = $mark['source_code'] ?? null;
        if (!is_string($sourceCode) || $sourceCode === '') {
            throw new GameInvalidException('Game battle mark source is invalid');
        }

        $sourceRuleCode = $mark['source_rule_code'] ?? null;
        if ($sourceRuleCode !== null && (!is_string($sourceRuleCode) || $sourceRuleCode === '')) {
            throw new GameInvalidException('Game battle mark source rule is invalid');
        }

        $scope = $mark['scope'] ?? null;
        if (!is_array($scope) || !is_array($scope['defense_against'] ?? null)) {
            throw new GameInvalidException('Game battle mark scope is invalid');
        }

        $scopeKeys = array_keys($scope);
        sort($scopeKeys);
        if ($scopeKeys !== ['defense_against']) {
            throw new GameInvalidException('Game battle mark scope is invalid');
        }

        $expires = $mark['expires'] ?? null;
        if (!is_array($expires)) {
            throw new GameInvalidException('Game battle mark expiry is invalid');
        }

        $expireKeys = array_keys($expires);
        sort($expireKeys);
        if ($expireKeys !== ['kind', 'of'] || ($expires['kind'] ?? null) !== self::EXPIRES_TURN) {
            throw new GameInvalidException('Game battle mark expiry is invalid');
        }

        if (($mark['consumes'] ?? null) !== self::CONSUMES_FIRST_DEFENSE) {
            throw new GameInvalidException('Game battle mark consumption is invalid');
        }

        return [
            'subject' => $this->actor($mark['subject'] ?? null),
            'kind' => $kind,
            'value' => $value,
            'source_code' => $sourceCode,
            'source_rule_code' => $sourceRuleCode,
            'scope' => ['defense_against' => $this->actor($scope['defense_against'])],
            'consumes' => self::CONSUMES_FIRST_DEFENSE,
            'expires' => ['kind' => self::EXPIRES_TURN, 'of' => $this->actor($expires['of'] ?? null)],
        ];
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
        $mark = $this->normalize($mark);
        $key = $this->keyOf($mark);
        $result = [];
        $replaced = false;
        foreach ($marks as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            try {
                $current = $this->normalize($entry);
            } catch (GameInvalidException) {
                continue;
            }

            if ($this->keyOf($current) === $key) {
                $result[] = $mark;
                $replaced = true;
                continue;
            }

            $result[] = $current;
        }

        if (!$replaced) {
            $result[] = $mark;
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
            if (!is_array($entry)) {
                continue;
            }

            try {
                $mark = $this->normalize($entry);
            } catch (GameInvalidException) {
                continue;
            }

            if ($this->actorKey($mark['subject']) !== $subjectKey) {
                continue;
            }

            if ($this->actorKey($mark['scope']['defense_against']) !== $attackerKey) {
                continue;
            }

            $advantages[] = new RollAdvantage(
                $mark['source_code'],
                $mark['value'],
                AdvantageSourceLabels::getLabel($mark['source_code']),
            );
            $keys[] = $this->keyOf($mark);
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
            if (!is_array($entry)) {
                continue;
            }

            try {
                $mark = $this->normalize($entry);
            } catch (GameInvalidException) {
                continue;
            }

            if (isset($remove[$this->keyOf($mark)])) {
                continue;
            }

            $result[] = $mark;
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
            if (!is_array($entry)) {
                continue;
            }

            try {
                $mark = $this->normalize($entry);
            } catch (GameInvalidException) {
                continue;
            }

            if ($this->actorKey($mark['expires']['of']) === $actorKey) {
                continue;
            }

            $result[] = $mark;
        }

        return $result;
    }

    /**
     * Ключ наложения: субъект, вид и область.
     *
     * @param array<string, mixed> $mark Нормализованная метка.
     *
     * @return string Ключ.
     */
    public function keyOf(array $mark): string
    {
        return $this->actorKey($mark['subject'])
            . '|' . $mark['kind']
            . '|' . $this->actorKey($mark['scope']['defense_against']);
    }

    /**
     * Разбирает пару актора.
     *
     * @param mixed $raw Значение.
     *
     * @return array{type: string, id: int} Пара.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private function actor(mixed $raw): array
    {
        if (
            !is_array($raw)
            || !is_string($raw['type'] ?? null)
            || !in_array($raw['type'], ['character', 'npc'], true)
            || !is_int($raw['id'] ?? null)
            || $raw['id'] < 1
        ) {
            throw new GameInvalidException('Game battle mark actor is invalid');
        }

        return ['type' => $raw['type'], 'id' => $raw['id']];
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
