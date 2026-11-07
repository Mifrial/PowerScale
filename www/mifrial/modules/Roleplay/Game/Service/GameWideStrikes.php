<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Interface\Service\IGameWideStrikes;

/**
 * Тонкий адаптер широкого удара. Расчёт и запись — в общем контуре.
 */
final class GameWideStrikes implements IGameWideStrikes
{
    /**
     * Создаёт фасад.
     *
     * @param GameStrikeBody $body Разбор выбора.
     * @param GameWideOpen $open Открытие удара.
     * @param GameWideResolve $resolve Закрытие удара.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeBody $body,
        private readonly GameWideOpen $open,
        private readonly GameWideResolve $resolve,
    ) {
    }

    /**
     * Открывает широкий удар.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param array $attack Выбор.
     *
     * @return array<string, mixed> Итог.
     */
    public function declareWideStrike(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $attack,
    ): array {
        return $this->open->declare(
            $gameId,
            $actorUserId,
            $editAll,
            $viewAll,
            $battleId,
            $idempotencyKey,
            $expectedBattleVersion,
            $this->body->wideAttack($attack),
        );
    }

    /**
     * Принимает ответы за цели; последний необходимый ответ закрывает удар.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param array $defense Выбор.
     *
     * @return array<string, mixed> Итог.
     */
    public function resolveWideStrike(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $defense,
    ): array {
        return $this->resolve->run(
            $gameId,
            $actorUserId,
            $editAll,
            $viewAll,
            $battleId,
            $idempotencyKey,
            $expectedBattleVersion,
            $this->body->wideDefense($defense),
        );
    }
}
