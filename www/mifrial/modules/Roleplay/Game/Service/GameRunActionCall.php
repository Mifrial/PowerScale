<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Снимок вызова — Record: параметры конструктора это поля, не порты.

namespace Mifrial\Roleplay\Game\Service;

/**
 * Снимок вызова game.runAction: разбор и адресация команды.
 */
final class GameRunActionCall
{
    /**
     * Создаёт снимок.
     *
     * @param array<string, mixed> $parsed Разбор конверта.
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ повтора.
     * @param int $expectedVersion Версия боя.
     *
     * @return void
     */
    public function __construct(
        public readonly array $parsed,
        public readonly int $gameId,
        public readonly int $actorUserId,
        public readonly bool $editAll,
        public readonly bool $viewAll,
        public readonly int $battleId,
        public readonly string $idempotencyKey,
        public readonly int $expectedVersion,
    ) {
    }
}
