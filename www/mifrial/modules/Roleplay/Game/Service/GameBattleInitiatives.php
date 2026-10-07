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
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameBattleCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * Порядок хода открытого боя. Лист не пишет.
 */
final class GameBattleInitiatives
{
    private readonly GameBattleRepository $battles;

    private readonly GameBattleCommandRepository $commands;

    /**
     * Создаёт фасад.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     * @param IGames $games Строка игры.
     * @param GameCardAccess $cardAccess Фильтр карточки.
     * @param GameMemberRepository $members Люди.
     * @param GameSessionRepository $sessions Сессия.
     * @param GameBattleOrder $order Расчёт порядка.
     *
     * @return void
     */
    public function __construct(
        private readonly ISmartTableGateway $smartTableGateway,
        private readonly IGames $games,
        private readonly GameCardAccess $cardAccess,
        private readonly GameMemberRepository $members,
        private readonly GameSessionRepository $sessions,
        private readonly GameBattleOrder $order,
    ) {
        $this->battles = new GameBattleRepository($smartTableGateway);
        $this->commands = new GameBattleCommandRepository($smartTableGateway);
    }

    /**
     * Считает порядок участников открытого боя.
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
     *
     * @throws GameInvalidException Если ключ, состав или карточка.
     * @throws GameNotFoundException Если карточка или бой скрыты.
     * @throws ActionException AUTH_DENIED.
     * @throws GameBattleConflictException Если версия или тело ключа чужие.
     */
    public function roll(
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
            fn (int $sessionId, GameRecord $game): array => $this->write(
                $game,
                $sessionId,
                $battleId,
                $idempotencyKey,
                $expectedVersion,
            ),
        );
    }

    /**
     * Видимость, повтор и одна транзакция.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Ключ.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $body Тело.
     * @param Closure $write Запись.
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
        if ($idempotencyKey === '') {
            throw new GameInvalidException('Game battle key is empty');
        }

        $game = $this->visible($gameId, $actorUserId, $viewAll);
        $this->assertOpen($game);
        $this->assertWriter($game, $actorUserId, $editAll);
        $replay = $this->replay($gameId, $idempotencyKey, $body);
        if ($replay !== null) {
            return $replay;
        }

        $sessionId = $this->requireSession($gameId);

        return $this->smartTableGateway->transaction(
            fn (): array => $write($sessionId, $game),
        );
    }

    /**
     * Сверка версии до броска, затем запись порядка.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedVersion Версия.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameNotFoundException Если боя нет.
     * @throws GameInvalidException Если состав или карточка.
     * @throws GameBattleConflictException Если версия другая.
     */
    private function write(
        GameRecord $game,
        int $sessionId,
        int $battleId,
        string $idempotencyKey,
        int $expectedVersion,
    ): array {
        $battle = $this->battles->find($battleId);
        if ($battle === null || $battle->getSessionId() !== $sessionId) {
            throw new GameNotFoundException('Game battle was not found');
        }

        if ($battle->getStateVersion() !== $expectedVersion) {
            throw new GameBattleConflictException($battle->getStateVersion());
        }

        $order = $this->order->rollAll(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $this->battles->findParticipants($battleId),
        );
        $saved = $this->battles->advanceVersion($battleId, $expectedVersion);
        $this->battles->replaceTurnOrder($battleId, $order);
        $result = [
            'battleId' => $battleId,
            'version' => $saved->getStateVersion(),
            'order' => $order,
        ];
        $this->commands->add($game->getId(), $sessionId, $idempotencyKey, [
            'battleId' => $battleId,
            'expectedVersion' => $expectedVersion,
        ], $result);

        return $result;
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
     * Итог с порядком.
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
        $version = $result['version'] ?? null;
        $order = $result['order'] ?? null;
        if (!is_int($battleId) || !is_int($version) || !is_array($order)) {
            throw new GameInvalidException('Game battle result is invalid');
        }

        return ['battleId' => $battleId, 'version' => $version, 'order' => $this->ordered($order)];
    }

    /**
     * Записи порядка в фиксированных ключах. JSON MySQL их переставляет.
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
}
