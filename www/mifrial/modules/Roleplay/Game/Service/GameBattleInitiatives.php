<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Closure;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;

/**
 * Порядок хода открытого боя. Лист не пишет.
 */
final class GameBattleInitiatives
{
    private const ORDER_KEYS = [
        'name',
        'difficulty',
        'roll',
        'success',
        'rating',
        'faces',
        'adjustedRolls',
        'successes',
        'droppedRolls',
        'dieFaces',
        'efficiency',
        'advantages',
        'min_success_size',
        'label',
    ];

    /**
     * Создаёт фасад.
     *
     * @param GameBattleRepository $battles Строки боя.
     * @param GameBattleOrder $order Расчёт порядка.
     * @param GameBattleAccess $access Права и сессия.
     * @param GameBattleLedger $ledger Журнал команд.
     *
     * @return void
     */
    public function __construct(
        private readonly GameBattleRepository $battles,
        private readonly GameBattleOrder $order,
        private readonly GameBattleAccess $access,
        private readonly GameBattleLedger $ledger,
    ) {
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
     * @param array<mixed>|null $rolls Способы или null.
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
        ?array $rolls = null,
    ): array {
        return $this->execute(
            $gameId,
            $actorUserId,
            $editAll,
            $viewAll,
            $idempotencyKey,
            $this->bodyOf($battleId, $expectedVersion, $rolls),
            fn (int $sessionId, GameRecord $game): array => $this->write(
                $game,
                $sessionId,
                $battleId,
                $idempotencyKey,
                $expectedVersion,
                $rolls,
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

        $game = $this->access->visible($gameId, $actorUserId, $viewAll);
        $this->access->assertOpen($game);
        $this->access->assertWriter($game, $actorUserId, $editAll);
        $replay = $this->ledger->replay($gameId, 'battle', $idempotencyKey, $body, $this->present(...));
        if ($replay !== null) {
            return $replay;
        }

        $sessionId = $this->access->session($gameId);

        return $this->ledger->transaction(fn (): array => $write($sessionId, $game));
    }

    /**
     * Тело команды в стабильном порядке ключей.
     *
     * @param int $battleId Бой.
     * @param int $expectedVersion Версия.
     * @param array<mixed>|null $rolls Способы или null.
     *
     * @return array<string, mixed> Тело.
     */
    private function bodyOf(int $battleId, int $expectedVersion, ?array $rolls): array
    {
        $body = ['battleId' => $battleId, 'expectedVersion' => $expectedVersion];
        if ($rolls !== null) {
            $body['rolls'] = $rolls;
        }

        return $body;
    }

    /**
     * Сверка версии до броска, затем запись порядка.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedVersion Версия.
     * @param array<mixed>|null $rolls Способы или null.
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
        ?array $rolls,
    ): array {
        $battle = $this->battle($battleId, $sessionId);
        if ($battle->getStateVersion() !== $expectedVersion) {
            throw new GameBattleConflictException($battle->getStateVersion());
        }

        $order = $this->order->rollAll(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $this->battles->participants->find($battleId),
            $rolls,
        );

        return $this->save($game, $sessionId, $battleId, $idempotencyKey, $expectedVersion, $rolls, $order);
    }

    /**
     * Бой открытой сессии.
     *
     * @param int $battleId Бой.
     * @param int $sessionId Сессия.
     *
     * @return GameBattleRecord Строка.
     *
     * @throws GameNotFoundException Если боя нет или он чужой.
     * @throws GameInvalidException Если строка битая.
     */
    private function battle(int $battleId, int $sessionId): GameBattleRecord
    {
        $battle = $this->battles->find($battleId);
        if ($battle === null || $battle->getSessionId() !== $sessionId) {
            throw new GameNotFoundException('Game battle was not found');
        }

        return $battle;
    }

    /**
     * Сохраняет порядок и команду.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedVersion Версия.
     * @param array<mixed>|null $rolls Способы или null.
     * @param list<array<string, mixed>> $order Порядок.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameNotFoundException Если боя нет.
     * @throws GameInvalidException Если поле.
     */
    private function save(
        GameRecord $game,
        int $sessionId,
        int $battleId,
        string $idempotencyKey,
        int $expectedVersion,
        ?array $rolls,
        array $order,
    ): array {
        $saved = $this->battles->advanceVersion($battleId, $expectedVersion);
        $this->battles->replaceTurnOrder($battleId, $order, 0, 1);
        $result = [
            'battleId' => $battleId,
            'version' => $saved->getStateVersion(),
            'order' => $order,
        ];
        $this->ledger->record(
            $game->getId(),
            $sessionId,
            'battle',
            $idempotencyKey,
            $this->bodyOf($battleId, $expectedVersion, $rolls),
            $result,
        );

        return $result;
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
            $entries[] = $this->orderedRow($entry);
        }

        return $entries;
    }

    /**
     * Одна запись порядка в фиксированных ключах.
     *
     * @param mixed $entry Запись.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameInvalidException Если запись битая.
     */
    private function orderedRow(mixed $entry): array
    {
        if (!is_array($entry) || !is_string($entry['type'] ?? null) || !is_int($entry['id'] ?? null)) {
            throw new GameInvalidException('Game battle result is invalid');
        }

        $row = ['type' => $entry['type'], 'id' => $entry['id']];
        foreach (self::ORDER_KEYS as $key) {
            if (array_key_exists($key, $entry)) {
                $row[$key] = $entry[$key];
            }
        }

        return $row;
    }
}
