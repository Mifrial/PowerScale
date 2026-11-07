<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Closure;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGameBattles;
use Mifrial\Roleplay\Game\Interface\Service\IGames;

/**
 * Старт, состав и конец боя. Лист не пишет.
 */
final class GameBattles implements IGameBattles
{
    /**
     * Создаёт фасад.
     *
     * @param IGames $games Строка игры.
     * @param GameBattleMutator $battleMutator Запись боя.
     * @param GameCombatNotices $notices Сообщения боя.
     * @param GameDeliverySignal $delivery Сигнал доставки.
     * @param GameBattleAccess $access Права и сессия.
     * @param GameBattleLedger $ledger Журнал команд.
     *
     * @return void
     */
    public function __construct(
        private readonly IGames $games,
        private readonly GameBattleMutator $battleMutator,
        private readonly GameCombatNotices $notices,
        private readonly GameDeliverySignal $delivery,
        private readonly GameBattleAccess $access,
        private readonly GameBattleLedger $ledger,
    ) {
    }

    /**
     * Открывает новый бой.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param string $idempotencyKey Ключ.
     * @param array $participants Состав.
     * @param array<mixed>|null $rolls Способы инициативы или null.
     *
     * @return array<string, mixed> Итог.
     */
    public function start(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        string $idempotencyKey,
        array $participants,
        ?array $rolls = null,
    ): array {
        $normalized = $this->normalize($participants);
        $executed = $this->execute(
            $gameId,
            $actorUserId,
            $editAll,
            $viewAll,
            $idempotencyKey,
            $this->startBody($normalized, $rolls),
            fn (int $sessionId): array => $this->writeStart(
                $gameId,
                $sessionId,
                $idempotencyKey,
                $normalized,
                $rolls,
            ),
        );
        $this->announceOpened($gameId, $actorUserId, $idempotencyKey, $executed);

        return $executed['result'];
    }

    /**
     * Заменяет состав.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param array $participants Состав.
     * @param int $expectedVersion Версия.
     *
     * @return array<string, mixed> Итог.
     */
    public function setRoster(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        array $participants,
        int $expectedVersion,
    ): array {
        $normalized = $this->normalize($participants);

        return $this->execute(
            $gameId,
            $actorUserId,
            $editAll,
            $viewAll,
            $idempotencyKey,
            $this->rosterBody($battleId, $normalized, $expectedVersion),
            fn (int $sessionId): array => $this->writeRoster(
                $gameId,
                $sessionId,
                $battleId,
                $idempotencyKey,
                $normalized,
                $expectedVersion,
            ),
        )['result'];
    }

    /**
     * Закрывает один бой.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedVersion Версия.
     *
     * @return array<string, mixed> Итог.
     */
    public function end(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedVersion,
    ): array {
        return $this->execute(
            $gameId,
            $actorUserId,
            $editAll,
            $viewAll,
            $idempotencyKey,
            ['battleId' => $battleId, 'expectedVersion' => $expectedVersion],
            fn (int $sessionId): array => $this->battleMutator->end(
                $gameId,
                $sessionId,
                $battleId,
                $idempotencyKey,
                $expectedVersion,
            ),
        )['result'];
    }

    /**
     * Сообщение и сигнал, если бой действительно открыт.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param string $idempotencyKey Ключ.
     * @param array{result: array<string, mixed>, sessionId: int, wrote: bool} $executed Итог.
     *
     * @return void
     */
    private function announceOpened(
        int $gameId,
        int $actorUserId,
        string $idempotencyKey,
        array $executed,
    ): void {
        $opened = $executed['result'];
        $order = $opened['order'] ?? null;
        $battleId = $opened['battleId'] ?? null;
        if ($executed['wrote'] && is_array($order) && is_int($battleId)) {
            $this->announce($gameId, $actorUserId, $executed['sessionId'], $battleId, $idempotencyKey, $order);
        }
    }

    /**
     * Тело смены состава.
     *
     * @param int $battleId Бой.
     * @param list<array{type: string, id: int}> $participants Состав.
     * @param int $expectedVersion Версия.
     *
     * @return array<string, mixed> Тело.
     */
    private function rosterBody(int $battleId, array $participants, int $expectedVersion): array
    {
        return ['battleId' => $battleId, 'participants' => $participants, 'expectedVersion' => $expectedVersion];
    }

    /**
     * Тело старта в стабильном порядке ключей.
     *
     * @param list<array{type: string, id: int}> $participants Состав.
     * @param array<mixed>|null $rolls Способы или null.
     *
     * @return array<string, mixed> Тело.
     */
    private function startBody(array $participants, ?array $rolls): array
    {
        $body = ['participants' => $participants];
        if ($rolls !== null) {
            $body['rolls'] = $rolls;
        }

        return $body;
    }

    /**
     * Сообщение и сигнал после первого старта.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param array<int, array<string, mixed>> $order Порядок.
     *
     * @return void
     */
    private function announce(
        int $gameId,
        int $actorUserId,
        int $sessionId,
        int $battleId,
        string $idempotencyKey,
        array $order,
    ): void {
        $this->notices->opened($this->games->get($gameId), $actorUserId, $sessionId, $battleId, $order);
        $stored = $this->ledger->find($gameId, 'battle', $idempotencyKey);
        if ($stored !== null) {
            $this->delivery->recorded($gameId, 'battle', $stored['id'], []);
        }
    }

    /**
     * Видимость, повтор, право и одна транзакция.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $body Тело.
     * @param Closure $write Запись внутри транзакции.
     *
     * @return array{result: array<string, mixed>, sessionId: int, wrote: bool} Итог.
     *
     * @throws GameInvalidException Если ключ, completed или сессии нет.
     * @throws GameNotFoundException Если карточка скрыта.
     * @throws ActionException AUTH_DENIED.
     * @throws GameBattleConflictException Если тело ключа другое.
     */
    private function execute(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        string $idempotencyKey,
        array $body,
        Closure $write,
    ): array {
        $ready = $this->ready($gameId, $actorUserId, $editAll, $viewAll, $idempotencyKey, $body);
        if ($ready['replay'] !== null) {
            return ['result' => $ready['replay'], 'sessionId' => 0, 'wrote' => false];
        }

        $result = $this->ledger->transaction(fn (): array => $write($ready['sessionId']));

        return ['result' => $result, 'sessionId' => $ready['sessionId'], 'wrote' => true];
    }

    /**
     * Проверки до записи. replay — итог или null.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $body Тело.
     *
     * @return array{replay: array<string, mixed>|null, sessionId: int} Готовность.
     *
     * @throws GameInvalidException Если ключ, completed или сессии нет.
     * @throws GameNotFoundException Если карточка скрыта.
     * @throws ActionException AUTH_DENIED.
     * @throws GameBattleConflictException Если тело ключа другое.
     */
    private function ready(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        string $idempotencyKey,
        array $body,
    ): array {
        if ($idempotencyKey === '') {
            throw new GameInvalidException('Game battle key is empty');
        }

        $game = $this->access->visible($gameId, $actorUserId, $viewAll);
        $replay = $this->ledger->replay($gameId, 'battle', $idempotencyKey, $body, $this->present(...));
        if ($replay !== null) {
            return ['replay' => $replay, 'sessionId' => 0];
        }

        $this->access->assertOpen($game);
        $this->access->assertWriter($game, $actorUserId, $editAll);

        return ['replay' => null, 'sessionId' => $this->access->session($gameId)];
    }

    /**
     * Итог в фиксированном порядке ключей. JSON MySQL их переставляет.
     *
     * @param array<string, mixed> $result Сохранённый итог.
     *
     * @return array<string, mixed> Ответ.
     *
     * @throws GameInvalidException Если итог битый.
     */
    private function present(array $result): array
    {
        $battleId = $result['battleId'] ?? null;
        $ended = $result['ended'] ?? null;
        if (!is_int($battleId) || !is_bool($ended)) {
            throw new GameInvalidException('Game battle result is invalid');
        }

        if ($ended) {
            return ['battleId' => $battleId, 'ended' => true];
        }

        $version = $result['version'] ?? null;
        if (!is_int($version)) {
            throw new GameInvalidException('Game battle result is invalid');
        }

        $presented = ['battleId' => $battleId, 'version' => $version, 'ended' => false];
        $order = $result['order'] ?? null;
        if (is_array($order) && array_is_list($order)) {
            $presented['order'] = $this->ordered($order);
        }

        return $presented;
    }

    /**
     * Записи порядка в фиксированных ключах.
     *
     * @param array<int, mixed> $order Список.
     *
     * @return list<array<string, mixed>> Порядок.
     *
     * @throws GameInvalidException Если запись битая.
     */
    private function ordered(array $order): array
    {
        $entries = [];
        foreach ($order as $entry) {
            if (!is_array($entry) || !is_string($entry['type'] ?? null) || !is_int($entry['id'] ?? null)) {
                throw new GameInvalidException('Game battle result is invalid');
            }

            $entries[] = [
                'type' => $entry['type'],
                'id' => $entry['id'],
                'difficulty' => $entry['difficulty'] ?? null,
                'roll' => $entry['roll'] ?? null,
                'success' => $entry['success'] ?? null,
                'rating' => $entry['rating'] ?? null,
            ];
        }

        return $entries;
    }

    /**
     * Список пар без лишних ключей.
     *
     * @param array $participants Вход.
     *
     * @return list<array{type: string, id: int}> Пары.
     *
     * @throws GameInvalidException Если форма.
     */
    private function normalize(array $participants): array
    {
        if (!array_is_list($participants)) {
            throw new GameInvalidException('Game battle participants are invalid');
        }

        $normalized = [];
        $seen = [];
        foreach ($participants as $participant) {
            $pair = $this->pair($participant);
            $key = $pair['type'] . ':' . $pair['id'];
            if (isset($seen[$key])) {
                throw new GameInvalidException('Game battle participant is duplicated');
            }

            $seen[$key] = true;
            $normalized[] = $pair;
        }

        return $normalized;
    }

    /**
     * Одна пара.
     *
     * @param mixed $participant Элемент.
     *
     * @return array{type: string, id: int} Пара.
     *
     * @throws GameInvalidException Если форма.
     */
    private function pair(mixed $participant): array
    {
        $keys = is_array($participant) ? array_keys($participant) : [];
        $known = $keys === ['type', 'id'] || $keys === ['id', 'type'];
        if (!is_array($participant) || !$known) {
            throw new GameInvalidException('Game battle participant is invalid');
        }

        $type = $participant['type'];
        $id = $participant['id'];
        if (($type !== 'character' && $type !== 'npc') || !is_int($id) || $id < 1) {
            throw new GameInvalidException('Game battle participant is invalid');
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * Старт боя. Ревизия нужна, если сразу пишется порядок.
     *
     * @param int $gameId Игра.
     * @param int $sessionId Сессия.
     * @param string $idempotencyKey Ключ.
     * @param list<array{type: string, id: int}> $participants Состав.
     * @param array<mixed>|null $rolls Способы или null.
     *
     * @return array<string, mixed> Итог.
     */
    private function writeStart(
        int $gameId,
        int $sessionId,
        string $idempotencyKey,
        array $participants,
        ?array $rolls,
    ): array {
        $game = $this->games->get($gameId);

        return $this->battleMutator->start(
            $gameId,
            $sessionId,
            $idempotencyKey,
            $participants,
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $rolls,
        );
    }

    /**
     * Смена состава. Ревизия нужна только если порядок уже записан.
     *
     * @param int $gameId Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param list<array{type: string, id: int}> $participants Состав.
     * @param int $expectedVersion Версия.
     *
     * @return array<string, mixed> Итог.
     */
    private function writeRoster(
        int $gameId,
        int $sessionId,
        int $battleId,
        string $idempotencyKey,
        array $participants,
        int $expectedVersion,
    ): array {
        $game = $this->games->get($gameId);

        return $this->battleMutator->setRoster(
            $gameId,
            $sessionId,
            $battleId,
            $idempotencyKey,
            $participants,
            $expectedVersion,
            $game->getSpaceId(),
            $game->getRulesRevision(),
        );
    }
}
