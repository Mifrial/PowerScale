<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

/**
 * Открытие удара или нескольких ударов одним выбором.
 */
final class GameStrikeDeclare
{
    /**
     * Создаёт объявление удара.
     *
     * @param GameStrikeBody $body Разбор выбора.
     * @param GameStrikeReady $ready Общая проверка запроса.
     * @param GameStrikeOpenFlow $openFlow Открытие.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeBody $body,
        private readonly GameStrikeReady $ready,
        private readonly GameStrikeOpenFlow $openFlow,
    ) {
    }

    /**
     * Открывает удар.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param array<string, mixed> $attack Выбор.
     *
     * @return array<string, mixed> Итог.
     */
    public function strike(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $attack,
    ): array {
        $choice = $this->body->attack($attack);
        $ready = $this->ready->check($gameId, $actorUserId, $editAll, $viewAll, $idempotencyKey, [
            'attack' => $choice,
            'battleId' => $battleId,
            'expectedVersion' => $expectedBattleVersion,
        ], $choice['attacker']);
        if ($ready['replay'] !== null) {
            return $ready['replay'];
        }

        return $this->openFlow->open(
            $ready['game'],
            $ready['sessionId'],
            $battleId,
            $idempotencyKey,
            $expectedBattleVersion,
            $choice,
            $actorUserId,
        );
    }

    /**
     * Открывает удар из нескольких экземпляров одного оружия.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param array<string, mixed> $choice Разобранный выбор со strikes.
     *
     * @return array<string, mixed> Итог.
     */
    public function multi(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $choice,
    ): array {
        $ready = $this->ready->check($gameId, $actorUserId, $editAll, $viewAll, $idempotencyKey, [
            'attack' => $choice,
            'battleId' => $battleId,
            'expectedVersion' => $expectedBattleVersion,
        ], $choice['attacker']);
        if ($ready['replay'] !== null) {
            return $ready['replay'];
        }

        return $this->openFlow->open(
            $ready['game'],
            $ready['sessionId'],
            $battleId,
            $idempotencyKey,
            $expectedBattleVersion,
            $choice,
            $actorUserId,
        );
    }
}
