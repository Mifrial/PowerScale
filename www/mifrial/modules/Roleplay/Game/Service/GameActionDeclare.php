<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassTooLong
// Три команды делят CAS, ключ и транзакцию; шаги вынесены в GameActionApply/GameActionReaction.

use Closure;
use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;

/**
 * HTTP-команда не-атакующего действия.
 */
final class GameActionDeclare
{
    private readonly GameActionPorts $ports;

    private readonly GameActionApply $apply;

    private readonly GameActionReaction $reaction;

    /**
     * Создаёт сценарий.
     *
     * @param ISmartTableGateway $gateway Шлюз таблиц.
     * @param IGames $games Игры.
     * @param GameCardAccess $cardAccess Видимость карточки.
     * @param GameActionPerform $perform Общий сценарий действия.
     * @param GamePreparedDefense $prepared Подготовка.
     * @param GameActionChat $chat Сообщение чата.
     * @param GameStrikeSheets $sheets Проекция конфликта листа.
     * @param IEventManager $events События доставки.
     * @param GameProcessFlow $processes Сессия процесса.
     * @param GameStrikeRules $rules Срез.
     * @param GameActionManeuver $maneuver Манёвр после удара.
     *
     * @return void
     */
    public function __construct(
        ISmartTableGateway $gateway,
        IGames $games,
        GameCardAccess $cardAccess,
        GameActionPerform $perform,
        GamePreparedDefense $prepared,
        GameActionChat $chat,
        GameStrikeSheets $sheets,
        IEventManager $events,
        GameProcessFlow $processes,
        GameStrikeRules $rules,
        private readonly GameActionManeuver $maneuver,
    ) {
        $this->ports = new GameActionPorts(
            $gateway,
            $games,
            $cardAccess,
            $perform,
            $prepared,
            $chat,
            $sheets,
            $events,
            $processes,
            $rules,
        );
        [$this->apply, $this->reaction] = $this->helpers();
    }

    /**
     * Выполняет команду.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Тело команды.
     *
     * @return array<string, mixed> Узкий итог.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameNotFoundException Если игры или боя нет.
     * @throws GameInvalidException Если команда отвергнута.
     * @throws GameBattleConflictException Если версия или ключ чужие.
     */
    public function act(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        $game = $this->ports->games->get($gameId);
        $this->ports->access->assertVisible($game, $actorUserId, $viewAll);
        $key = $this->key($gameId, 'action', $body);

        return $this->execute(
            $gameId,
            'action',
            $key,
            $body,
            fn (): array => $this->apply->act($game, $actorUserId, $editAll, $viewAll, $body),
        );
    }

    /**
     * Объявляет реакцию в открытом ударе.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Тело команды.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameNotFoundException Если игры или боя нет.
     * @throws GameInvalidException Если реакция отвергнута.
     * @throws GameBattleConflictException Если версия боя или листа чужая.
     */
    public function reaction(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        $game = $this->ports->games->get($gameId);
        $this->ports->access->assertVisible($game, $actorUserId, $viewAll);
        $key = $this->key($gameId, 'reaction', $body);

        return $this->execute(
            $gameId,
            'reaction',
            $key,
            $body,
            fn (): array => $this->reaction->declare($game, $actorUserId, $editAll, $body),
        );
    }

    /**
     * Исполняет действие после доплаты долга.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Сохранённое объявление.
     * @param int $expectedBattle Версия боя.
     * @param int $expectedSheet Версия листа после доплаты.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если действие отвергнуто.
     * @throws GameNotFoundException Если игры или боя нет.
     */
    public function resume(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
        int $expectedBattle,
        int $expectedSheet,
    ): array {
        $game = $this->ports->games->get($gameId);
        $this->ports->access->assertVisible($game, $actorUserId, $viewAll);

        return $this->apply->resume($game, $actorUserId, $editAll, $viewAll, $body, $expectedBattle, $expectedSheet);
    }

    /**
     * Собирает исполнителей действия.
     *
     * @return array{0: GameActionApply, 1: GameActionReaction} Исполнители.
     */
    private function helpers(): array
    {
        $input = new GameActionInput();
        $effects = new GameActionEffectInput();
        $value = new GameActionValue();
        $battle = new GameActionBattle($this->ports, $value);
        $cover = new GameActionCover($this->ports, $value, $effects, $battle);
        $debt = new GameActionDebtStep($this->ports, $value);
        $outcome = new GameActionOutcome($this->ports, $battle);

        return [
            new GameActionApply($this->ports, $input, $battle, $value, $debt, $outcome, $this->maneuver),
            new GameActionReaction($this->ports, $input, $battle, $value, $cover, $effects),
        ];
    }

    /**
     * Выполняет команду с повтором ключа.
     *
     * @param int $gameId Игра.
     * @param string $kind action или reaction.
     * @param string $key Ключ.
     * @param array<string, mixed> $body Тело.
     * @param Closure(): array $work Первая запись.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если команда отвергнута.
     * @throws GameBattleConflictException Если ключ чужой.
     */
    private function execute(int $gameId, string $kind, string $key, array $body, Closure $work): array
    {
        $execution = $this->ports->replay->execute(
            fn (): ?array => $this->ports->commands->findByKey($gameId, $kind, $key),
            fn (): int => $this->ports->commands->reserve($gameId, $this->sessionId($gameId), $kind, $key, $body),
            function (int $reservationId, array $result): void {
                $this->ports->commands->complete($reservationId, $result);
            },
            $work,
            $body,
        );
        $this->announce($gameId, $kind, $key, $execution['replay']);

        return $execution['result'];
    }

    /**
     * Ключ команды и сессия.
     *
     * @param int $gameId Игра.
     * @param string $kind action или reaction.
     * @param array<string, mixed> $body Тело.
     *
     * @return string Ключ.
     *
     * @throws GameInvalidException Если ключа или сессии нет.
     */
    private function key(int $gameId, string $kind, array $body): string
    {
        $key = $body['idempotencyKey'] ?? null;
        $sessionId = $this->ports->sessions->findSessionId($gameId);
        if ($sessionId !== null && is_string($key) && $key !== '') {
            return $key;
        }

        throw new GameInvalidException($kind === 'action' ? 'Game action is invalid' : 'Game strike reaction is invalid');
    }

    /**
     * Сессия игры.
     *
     * @param int $gameId Игра.
     *
     * @return int Сессия.
     *
     * @throws GameInvalidException Если сессии нет.
     */
    private function sessionId(int $gameId): int
    {
        $sessionId = $this->ports->sessions->findSessionId($gameId);
        if ($sessionId === null) {
            throw new GameInvalidException('Game action is invalid');
        }

        return $sessionId;
    }

    /**
     * Шлёт сигнал после первой записи.
     *
     * @param int $gameId Игра.
     * @param string $kind Вид команды action или reaction.
     * @param string $idempotencyKey Ключ.
     * @param bool $replay Повтор.
     *
     * @return void
     */
    private function announce(int $gameId, string $kind, string $idempotencyKey, bool $replay): void
    {
        if ($replay) {
            return;
        }

        $stored = $this->ports->commands->findByKey($gameId, $kind, $idempotencyKey);
        if ($stored !== null) {
            $this->ports->delivery->recorded($gameId, $kind, $stored['id'], []);
        }
    }
}
