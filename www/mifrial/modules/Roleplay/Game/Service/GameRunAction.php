<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Единый вход действий: карточка, удар и рана.
 */
final class GameRunAction
{
    /**
     * Создаёт диспетчер.
     *
     * @param GameRunActionBody $body Разбор конверта.
     * @param GameRunActionDeclare $declare Карточки, реакция и рана.
     * @param GameRunActionStrikeDeclare $strikes Удар.
     * @param GameRunActionRespond $respond Ответ защитой.
     *
     * @return void
     */
    public function __construct(
        private readonly GameRunActionBody $body,
        private readonly GameRunActionDeclare $declare,
        private readonly GameRunActionStrikeDeclare $strikes,
        private readonly GameRunActionRespond $respond,
    ) {
    }

    /**
     * Выполняет действие.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ повтора.
     * @param int $expectedVersion Версия боя.
     * @param array<string, mixed> $action Конверт.
     *
     * @return array<string, mixed> Нормализованный итог.
     *
     * @throws \Mifrial\Core\Kernel\Exception\ActionException AUTH_DENIED или INVALID_PARAMS.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если действие отвергнуто.
     */
    public function run(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedVersion,
        array $action,
    ): array {
        $parsed = $this->body->parse($action);
        $call = $this->call(
            $parsed,
            $gameId,
            $actorUserId,
            $editAll,
            $viewAll,
            $battleId,
            $idempotencyKey,
            $expectedVersion,
        );

        return $this->dispatch($parsed, $call);
    }

    /**
     * Выбирает сценарий по фазе и виду.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param GameRunActionCall $call Снимок вызова.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если актор не объявлен.
     */
    private function dispatch(array $parsed, GameRunActionCall $call): array
    {
        if ($parsed['phase'] === 'respond') {
            return $this->respond->resolve($parsed, $call);
        }

        if ($parsed['actor'] === null) {
            throw new GameInvalidException('Game action actor is invalid');
        }

        return $parsed['kind'] === 'strike'
            ? $this->strikes->declare($parsed, $call)
            : $this->declare->declare($parsed, $call);
    }

    /**
     * Снимает вызов в неизменяемый снимок.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право.
     * @param bool $viewAll Право.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedVersion Версия.
     *
     * @return GameRunActionCall Снимок.
     */
    private function call(
        array $parsed,
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedVersion,
    ): GameRunActionCall {
        return new GameRunActionCall(
            $parsed,
            $gameId,
            $actorUserId,
            $editAll,
            $viewAll,
            $battleId,
            $idempotencyKey,
            $expectedVersion,
        );
    }
}
