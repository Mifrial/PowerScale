<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
use Mifrial\Roleplay\Game\Dto\GameSpellCastSaturationPlan;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Отложенный каст между проверкой и выбором насыщения.
 */
final class GameSpellCastPending
{
    private const INVALID = 'Game spell pending cast is invalid';

    /**
     * Собирает отложенный каст.
     *
     * @param string $castId Ключ каста.
     * @param int $actionId Открытое действие.
     * @param int $userId Владелец кастера.
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param array<string, mixed> $body Тело каста.
     * @param array<string, mixed> $check Итог проверки.
     * @param int $trainingDelta Сдвиг Сложности.
     * @param int|null $deliverySr Успех касания или null.
     * @param bool $touch Касание.
     * @param int|null $strikeId Удар касания.
     * @param array<string, mixed>|null $thread Нить чата.
     * @param GameSpellCastSaturationPlan $plan Выбор насыщения.
     *
     * @return array<string, mixed> Отложенный каст.
     */
    public function build(
        string $castId,
        int $actionId,
        int $userId,
        GameSpellCastPrepared $prepared,
        array $body,
        array $check,
        int $trainingDelta,
        ?int $deliverySr,
        bool $touch,
        ?int $strikeId,
        ?array $thread,
        GameSpellCastSaturationPlan $plan,
    ): array {
        $target = $touch ? $prepared->damageTarget : $prepared->target;

        return [
            'castId' => $castId,
            'actionId' => $actionId,
            'actor' => ['type' => $prepared->casterKind, 'id' => $prepared->casterId],
            'userId' => $userId,
            'spellCode' => $prepared->spellCode,
            'pathCode' => $prepared->pathCode,
            'body' => $body,
            'check' => $check,
            'trainingDelta' => $trainingDelta,
            'deliverySr' => $deliverySr,
            'cost' => $prepared->spent,
            'casterVersion' => $prepared->casterVersion,
            'target' => $target === null
                ? null
                : ['type' => $target['type'], 'id' => $target['id'], 'version' => $target['version']],
            'touch' => $touch,
            'strikeId' => $strikeId,
            'thread' => $thread,
            'saturation' => $plan->toArray(),
            'createdAt' => time(),
        ];
    }

    /**
     * Проверяет и возвращает отложенный каст из строки боя.
     *
     * @param mixed $value Значение колонки.
     *
     * @return array<string, mixed> Отложенный каст.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    public function from(mixed $value): array
    {
        if (!is_array($value) || array_is_list($value)) {
            throw new GameInvalidException(self::INVALID);
        }

        foreach (['castId', 'spellCode', 'pathCode'] as $key) {
            if (!is_string($value[$key] ?? null) || $value[$key] === '') {
                throw new GameInvalidException(self::INVALID);
            }
        }

        foreach (['actionId', 'userId', 'trainingDelta', 'cost', 'casterVersion', 'createdAt'] as $key) {
            if (!is_int($value[$key] ?? null)) {
                throw new GameInvalidException(self::INVALID);
            }
        }

        if (
            !is_array($value['actor'] ?? null)
            || !is_array($value['body'] ?? null)
            || !is_array($value['check'] ?? null)
        ) {
            throw new GameInvalidException(self::INVALID);
        }

        if (!is_bool($value['touch'] ?? null)) {
            throw new GameInvalidException(self::INVALID);
        }

        return $value;
    }

    /**
     * Тело для повторной подготовки каста.
     *
     * @param array<string, mixed> $pending Отложенный каст.
     * @param int $gameId Игра.
     * @param int $battleId Бой.
     *
     * @return array<string, mixed> Тело.
     */
    public function bodyOf(array $pending, int $gameId, int $battleId): array
    {
        $body = is_array($pending['body'] ?? null) ? $pending['body'] : [];
        $actor = is_array($pending['actor'] ?? null) ? $pending['actor'] : [];

        return $body + [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => $pending['castId'],
            'actor' => $actor,
            'spellCode' => $pending['spellCode'],
        ];
    }
}
