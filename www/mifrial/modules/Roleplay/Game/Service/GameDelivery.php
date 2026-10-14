<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Dto\GameDeliveryRecord;
use Mifrial\Roleplay\Game\Dto\GameNpcRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGameDelivery;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameDeliveryRepository;
use Mifrial\Roleplay\Game\Repository\GameEconomyOperationRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameStrikeCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameTurnCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameWoundCommandRepository;

/**
 * Строка outbox и кадр ключей. Мутацию не повторяет.
 */
final class GameDelivery implements IGameDelivery
{
    private readonly GameDeliveryRepository $rows;

    private readonly GameEconomyOperationRepository $operations;

    private readonly GameStrikeCommandRepository $commands;

    private readonly GameTurnCommandRepository $turns;

    private readonly GameWoundCommandRepository $wounds;

    private readonly GameNpcRepository $npcs;

    private readonly GameDeliveryKeys $keys;

    /**
     * Создаёт фасад.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     * @param IGames $games Строка игры.
     * @param GameCardAccess $cardAccess Фильтр карточки.
     *
     * @return void
     */
    public function __construct(
        ISmartTableGateway $smartTableGateway,
        private readonly IGames $games,
        private readonly GameCardAccess $cardAccess,
    ) {
        $this->rows = new GameDeliveryRepository($smartTableGateway);
        $this->operations = new GameEconomyOperationRepository($smartTableGateway);
        $this->commands = new GameStrikeCommandRepository($smartTableGateway);
        $this->turns = new GameTurnCommandRepository($smartTableGateway);
        $this->wounds = new GameWoundCommandRepository($smartTableGateway);
        $this->npcs = new GameNpcRepository($smartTableGateway);
        $this->keys = new GameDeliveryKeys();
    }

    /**
     * Кладёт строку, если пары ещё нет.
     *
     * @param int $gameId Игра.
     * @param string $source Источник economy или strike.
     * @param int $sourceId Id журнала.
     * @param array $keys Ключи листов.
     *
     * @return void
     */
    public function record(int $gameId, string $source, int $sourceId, array $keys): void
    {
        $this->rows->add($gameId, $source, $sourceId, $keys);
    }

    /**
     * Дописывает строки из журналов.
     *
     * @param int $gameId Игра.
     *
     * @return void
     */
    public function catchUp(int $gameId): void
    {
        foreach ($this->operations->getListByGame($gameId) as $operation) {
            $this->rows->add(
                $gameId,
                'economy',
                $operation->getId(),
                $this->keys->fromEconomy($operation->getResult()),
            );
        }

        foreach ($this->commands->getListByGame($gameId) as $command) {
            $this->rows->add($gameId, 'strike', $command['id'], $this->keys->fromStrike($command['result']));
        }

        foreach ($this->turns->getListByGame($gameId) as $command) {
            $this->rows->add($gameId, 'turn', $command['id'], []);
        }

        foreach ($this->wounds->getListByGame($gameId) as $command) {
            $this->rows->add($gameId, 'wound', $command['id'], []);
        }
    }

    /**
     * Хвост для актора.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     * @param int|null $lastCursor Нижняя граница. null — hello.
     *
     * @return array{cursor: int, events: array<int, array<string, mixed>>} Кадр.
     *
     * @throws GameNotFoundException Если карточка скрыта.
     */
    public function tail(int $gameId, int $actorUserId, bool $viewAll, ?int $lastCursor): array
    {
        $game = $this->visibleGame($gameId, $actorUserId, $viewAll);
        if ($lastCursor === null) {
            return ['cursor' => $this->rows->getMaxCursor($gameId), 'events' => []];
        }

        $stored = $this->rows->getAfter($gameId, $lastCursor);
        $cursor = $stored === [] ? $this->rows->getMaxCursor($gameId) : $stored[array_key_last($stored)]->getId();

        return ['cursor' => $cursor, 'events' => $this->eventsOf($game, $actorUserId, $stored)];
    }

    /**
     * Карточка или отказ.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     *
     * @return GameRecord Игра.
     *
     * @throws GameNotFoundException Если карточка скрыта.
     */
    private function visibleGame(int $gameId, int $actorUserId, bool $viewAll): GameRecord
    {
        $game = $this->games->get($gameId);
        if (!$this->cardAccess->isVisible($game, $actorUserId, $viewAll)) {
            throw new GameNotFoundException('Game was not found');
        }

        return $game;
    }

    /**
     * Кадр без чужих NPC.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param array<int, GameDeliveryRecord> $stored Хвост.
     *
     * @return array<int, array<string, mixed>> События.
     */
    private function eventsOf(GameRecord $game, int $actorUserId, array $stored): array
    {
        $npcs = [];
        foreach ($this->npcs->getListByGame($game->getId()) as $npc) {
            $npcs[$npc->getId()] = $npc;
        }

        $events = [];
        foreach ($stored as $row) {
            $keys = $this->visibleKeys($game, $actorUserId, $row->getKeys(), $npcs);
            if ($keys === [] && $row->getKeys() !== []) {
                continue;
            }

            $events[] = $this->eventOf($game, $row, $keys);
        }

        return $events;
    }

    /**
     * Кадр одной строки.
     *
     * @param GameRecord $game Игра.
     * @param GameDeliveryRecord $row Строка.
     * @param array<int, array<string, mixed>> $keys Видимые ключи.
     *
     * @return array<string, mixed> Событие.
     */
    private function eventOf(GameRecord $game, GameDeliveryRecord $row, array $keys): array
    {
        $event = [
            'eventId' => $game->getId() . '.' . $row->getId(),
            'cursor' => $row->getId(),
            'source' => $row->getSource(),
            'sourceId' => $row->getSourceId(),
            'keys' => $keys,
        ];
        $battleId = $this->battleIdOf($row->getSource(), $row->getSourceId());
        if ($battleId !== null) {
            $event['battleId'] = $battleId;
        }

        return $event;
    }

    /**
     * Id боя журнала strike, turn или wound.
     *
     * @param string $source Источник.
     * @param int $sourceId Id журнала.
     *
     * @return int|null Id боя.
     */
    private function battleIdOf(string $source, int $sourceId): ?int
    {
        $stored = match ($source) {
            'strike' => $this->commands->find($sourceId),
            'turn' => $this->turns->find($sourceId),
            'wound' => $this->wounds->find($sourceId),
            default => null,
        };
        $fromResult = is_array($stored) ? ($stored['result']['battleId'] ?? null) : null;
        $fromBody = is_array($stored) ? ($stored['body']['battleId'] ?? null) : null;
        $battleId = is_int($fromResult) ? $fromResult : $fromBody;

        return is_int($battleId) ? $battleId : null;
    }

    /**
     * Оставляет ключи, которые roster показал бы.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param array<int, array<string, mixed>> $keys Ключи строки.
     * @param array<int, GameNpcRecord> $npcs NPC игры.
     *
     * @return array<int, array<string, mixed>> Видимые.
     */
    private function visibleKeys(GameRecord $game, int $actorUserId, array $keys, array $npcs): array
    {
        $visible = [];
        foreach ($keys as $key) {
            if (($key['type'] ?? null) === 'character') {
                $visible[] = $key;
                continue;
            }

            $npc = $npcs[$key['id'] ?? null] ?? null;
            if ($npc instanceof GameNpcRecord && $this->isNpcVisible($game, $npc, $actorUserId)) {
                $visible[] = $key;
            }
        }

        return $visible;
    }

    /**
     * Scope NPC, тот же смысл, что у roster.
     *
     * @param GameRecord $game Игра.
     * @param GameNpcRecord $npc Строка.
     * @param int $actorUserId Актор.
     *
     * @return bool true, если ключ можно отдать.
     */
    private function isNpcVisible(GameRecord $game, GameNpcRecord $npc, int $actorUserId): bool
    {
        $role = $this->roleOf($game->getId(), $actorUserId);
        $visibility = $npc->getVisibility();
        $scope = $visibility['scope'] ?? null;
        if ($game->getOwnerId() === $actorUserId || $role === 'gm' || ($scope === 'all' && $role !== null)) {
            return true;
        }

        if ($scope !== 'users' || !is_array($visibility['userIds'] ?? null)) {
            return false;
        }

        return in_array($actorUserId, $visibility['userIds'], true);
    }

    /**
     * Роль участника или null.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     *
     * @return string|null gm, player или null.
     */
    private function roleOf(int $gameId, int $actorUserId): ?string
    {
        try {
            return $this->games->getMember($gameId, $actorUserId)->getRole();
        } catch (GameNotFoundException) {
            return null;
        }
    }
}
