<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Closure;
use JsonException;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Dto\GameBattleCommandRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGameBattles;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameBattleCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * Старт, состав и конец боя. Лист не пишет.
 */
final class GameBattles implements IGameBattles
{
    private readonly GameBattleCommandRepository $commands;

    /**
     * Создаёт фасад.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     * @param IGames $games Строка игры.
     * @param GameCardAccess $cardAccess Фильтр карточки.
     * @param GameMemberRepository $members Люди.
     * @param GameSessionRepository $sessions Сессия.
     * @param GameBattleMutator $battleMutator Запись боя.
     *
     * @return void
     */
    public function __construct(
        private readonly ISmartTableGateway $smartTableGateway,
        private readonly IGames $games,
        private readonly GameCardAccess $cardAccess,
        private readonly GameMemberRepository $members,
        private readonly GameSessionRepository $sessions,
        private readonly GameBattleMutator $battleMutator,
    ) {
        $this->commands = new GameBattleCommandRepository($smartTableGateway);
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
    ): array {
        $normalized = $this->normalize($participants);

        return $this->execute(
            $gameId,
            $actorUserId,
            $editAll,
            $viewAll,
            $idempotencyKey,
            ['participants' => $normalized],
            fn (int $sessionId): array => $this->battleMutator->start(
                $gameId,
                $sessionId,
                $idempotencyKey,
                $normalized,
            ),
        );
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
            [
                'battleId' => $battleId,
                'participants' => $normalized,
                'expectedVersion' => $expectedVersion,
            ],
            fn (int $sessionId): array => $this->writeRoster(
                $gameId,
                $sessionId,
                $battleId,
                $idempotencyKey,
                $normalized,
                $expectedVersion,
            ),
        );
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
            [
                'battleId' => $battleId,
                'expectedVersion' => $expectedVersion,
            ],
            fn (int $sessionId): array => $this->battleMutator->end(
                $gameId,
                $sessionId,
                $battleId,
                $idempotencyKey,
                $expectedVersion,
            ),
        );
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
     * @return array<string, mixed> Итог.
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
            return $ready['replay'];
        }

        return $this->smartTableGateway->transaction(function () use ($write, $ready): array {
            return $write($ready['sessionId']);
        });
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

        $game = $this->visible($gameId, $actorUserId, $viewAll);
        $replay = $this->replay($gameId, $idempotencyKey, $body);
        if ($replay !== null) {
            return ['replay' => $replay, 'sessionId' => 0];
        }

        $this->assertOpen($game);
        $this->assertWriter($game, $actorUserId, $editAll);

        return ['replay' => null, 'sessionId' => $this->requireSession($gameId)];
    }

    /**
     * Повтор ключа или null.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed>|null Итог.
     *
     * @throws GameBattleConflictException Если тело другое.
     * @throws GameInvalidException Если JSON.
     */
    private function replay(int $gameId, string $idempotencyKey, array $body): ?array
    {
        $stored = $this->commands->findByKey($gameId, $idempotencyKey);
        if (!$stored instanceof GameBattleCommandRecord) {
            return null;
        }

        if ($this->canonical($stored->getBody()) !== $this->canonical($body)) {
            throw new GameBattleConflictException(null);
        }

        return $this->present($stored->getResult());
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
     * Стабильный JSON.
     *
     * @param mixed $value Значение.
     *
     * @return string JSON.
     *
     * @throws GameInvalidException Если значение не кодируется.
     */
    private function canonical(mixed $value): string
    {
        try {
            $encoded = json_encode($this->sortKeys($value), JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new GameInvalidException('Game battle body is invalid', $exception);
        }

        return $encoded;
    }

    /**
     * Сортирует ключи объектов.
     *
     * @param mixed $value Значение.
     *
     * @return mixed Значение.
     */
    private function sortKeys(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map($this->sortKeys(...), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->sortKeys($item);
        }

        return $value;
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
     * Видимая игра.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ.
     *
     * @return GameRecord Строка.
     *
     * @throws GameNotFoundException Если скрыта.
     */
    private function visible(int $gameId, int $actorUserId, bool $viewAll): GameRecord
    {
        $game = $this->games->get($gameId);
        if (!$this->cardAccess->isVisible($game, $actorUserId, $viewAll)) {
            throw new GameNotFoundException();
        }

        return $game;
    }

    /**
     * completed не пишет.
     *
     * @param GameRecord $game Игра.
     *
     * @return void
     *
     * @throws GameInvalidException Если завершена.
     */
    private function assertOpen(GameRecord $game): void
    {
        if ($game->getStatus() === 'completed') {
            throw new GameInvalidException('Game is completed');
        }
    }

    /**
     * Владелец, gm или edit_all.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     */
    private function assertWriter(GameRecord $game, int $actorUserId, bool $editAll): void
    {
        if ($game->getOwnerId() === $actorUserId || $editAll || $this->isGm($game->getId(), $actorUserId)) {
            return;
        }

        throw new ActionException('AUTH_DENIED', 'Permission denied');
    }

    /**
     * Роль gm.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     *
     * @return bool Да, если gm.
     */
    private function isGm(int $gameId, int $actorUserId): bool
    {
        try {
            $member = $this->members->getByPair($gameId, $actorUserId);
        } catch (GameNotFoundException) {
            return false;
        }

        return $member->getRole() === 'gm';
    }

    /**
     * Текущая сессия.
     *
     * @param int $gameId Игра.
     *
     * @return int Id.
     *
     * @throws GameInvalidException Если сессии нет.
     */
    private function requireSession(int $gameId): int
    {
        $sessionId = $this->sessions->findSessionId($gameId);
        if ($sessionId === null) {
            throw new GameInvalidException('Game session is not running');
        }

        return $sessionId;
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
