<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Messages\Chat\Exception\ChatInvalidException;
use Mifrial\Messages\Chat\Exception\ChatNotFoundException;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * Конец хода, продолжение и отмена долга.
 */
final class GameTurns
{
    private readonly GameBattleRepository $battles;

    private readonly GameCommandRepository $commands;

    private readonly GameSessionRepository $sessions;

    private readonly GameCharacterRepository $characters;

    private readonly GameMemberRepository $members;

    private readonly GameReplayTransaction $replay;

    private readonly GameDeliverySignal $delivery;

    private readonly GameStrikeSheetWrites $sheets;

    private readonly GameNpcRepository $npcs;

    private readonly GameActionCards $actionCards;

    private readonly GameActionPerform $actionPerform;

    private readonly GameActionChat $actionChat;

    /**
     * Создаёт сценарий.
     *
     * @param ISmartTableGateway $gateway Шлюз.
     * @param IGames $games Игра.
     * @param GameCardAccess $cardAccess Карточка.
     * @param GameStrikeRules $rules Срез.
     * @param ICharacterActualMutations $mutations Лист.
     * @param IChats $chats Чат.
     * @param GameTurnTicks $ticks Тики.
     * @param GameStrikeOpen $open Открытие удара.
     * @param IEventManager $events Доставка.
     * @param GameCombatNotices $notices Заметки боя.
     */
    public function __construct(
        private readonly ISmartTableGateway $gateway,
        private readonly IGames $games,
        private readonly GameCardAccess $cardAccess,
        private readonly GameStrikeRules $rules,
        ICharacterActualMutations $mutations,
        private readonly IChats $chats,
        private readonly GameTurnTicks $ticks,
        private readonly GameStrikeOpen $open,
        IEventManager $events,
        private readonly GameCombatNotices $notices,
    ) {
        $this->battles = new GameBattleRepository($this->gateway);
        $this->commands = new GameCommandRepository($this->gateway);
        $this->sessions = new GameSessionRepository($this->gateway);
        $this->characters = new GameCharacterRepository($this->gateway);
        $this->members = new GameMemberRepository($this->gateway);
        $this->replay = new GameReplayTransaction($this->gateway);
        $this->delivery = new GameDeliverySignal($events);
        $this->npcs = new GameNpcRepository($this->gateway);
        $this->sheets = new GameStrikeSheetWrites($mutations, $this->npcs);
        $this->strikesRepo = new GameActionRepository($this->gateway);
        $this->actionCards = new GameActionCards();
        $this->actionPerform = new GameActionPerform(
            $rules,
            $this->npcs,
            $this->sheets,
            $this->actionCards,
            new GamePendingActionCosts(),
        );
        $this->actionChat = new GameActionChat($chats, new GameStrikeChat($this->npcs, $rules));
    }

    private readonly GameActionRepository $strikesRepo;

    /**
     * Конец хода текущего.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Право.
     * @param bool $viewAll Просмотр.
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Итог.
     */
    public function end(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        return $this->run($gameId, $actorUserId, $editAll, $viewAll, $body, 'end');
    }

    /**
     * Доплата долга текущего.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Право.
     * @param bool $viewAll Просмотр.
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Итог.
     */
    public function continueAction(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        return $this->run($gameId, $actorUserId, $editAll, $viewAll, $body, 'continue');
    }

    /**
     * Снимает долг текущего.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Право.
     * @param bool $viewAll Просмотр.
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Итог.
     */
    public function abort(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        return $this->run($gameId, $actorUserId, $editAll, $viewAll, $body, 'abort');
    }

    /**
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Итог.
     */
    private function run(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
        string $command,
    ): array {
        $game = $this->games->get($gameId);
        if (!$this->cardAccess->isVisible($game, $actorUserId, $viewAll)) {
            throw new GameNotFoundException('Game was not found');
        }

        $sessionId = $this->sessions->findSessionId($gameId);
        if ($sessionId === null) {
            throw new GameInvalidException('Game battle was not found');
        }

        $execution = $this->replay->execute(
            fn (): ?array => $this->commands->findByKey($gameId, 'turn', $this->key($body)),
            fn (): int => $this->commands->reserve($gameId, $sessionId, 'turn', $this->key($body), $body),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            fn (): array => $this->work($game, $sessionId, $actorUserId, $editAll, $body, $command),
            $body,
        );
        if (!$execution['replay']) {
            $stored = $this->commands->findByKey($gameId, 'turn', $this->key($body));
            if ($stored !== null) {
                $this->delivery->recorded($gameId, 'turn', $stored['id'], []);
            }
        }

        return $execution['result'];
    }

    /**
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Итог.
     */
    private function work(
        GameRecord $game,
        int $sessionId,
        int $actorUserId,
        bool $editAll,
        array $body,
        string $command,
    ): array {
        $battleId = $body['battleId'] ?? null;
        $expectedBattle = $body['expectedBattleVersion'] ?? null;
        $expectedSheet = $body['expectedSheetVersion'] ?? null;
        if (!is_int($battleId) || !is_int($expectedBattle) || !is_int($expectedSheet)) {
            throw new GameInvalidException('Game turn field is invalid');
        }

        $battle = $this->battles->find($battleId);
        if ($battle === null || $battle->getSessionId() !== $sessionId) {
            throw new GameNotFoundException('Game battle was not found');
        }

        if ($battle->getTurnOrder() === null || $battle->getRound() < 1) {
            throw new GameInvalidException('Game turn order is missing');
        }

        $current = $this->current($battle);
        $this->assertActor($game, $actorUserId, $editAll, $current);
        if ($command === 'end') {
            $ended = $this->endWork($game, $battle, $expectedBattle, $expectedSheet, $current, $actorUserId);
            $order = $battle->getTurnOrder() ?? [];
            $index = $ended['activeIndex'] ?? 0;
            $entry = is_int($index) ? ($order[$index] ?? null) : null;
            $name = is_array($entry) && is_string($entry['name'] ?? null) && $entry['name'] !== ''
                ? $entry['name']
                : 'Участник';
            $round = is_int($ended['round'] ?? null) ? $ended['round'] : 1;
            $this->notices->passed($game, $actorUserId, $battle->getId(), $round, is_int($index) ? $index : 0, $name);

            return $ended;
        }

        if ($command === 'abort') {
            return $this->abortWork($battle, $expectedBattle, $current);
        }

        return $this->continueWork($game, $sessionId, $battle, $expectedBattle, $expectedSheet, $current);
    }

    /**
     * Конец хода: Ожидание, тики и передача хода.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattle Версия боя.
     * @param int $expectedSheet Версия листа текущего.
     * @param array{type: string, id: int} $current Текущий.
     * @param int $actorUserId Пользователь.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameBattleConflictException Если версия боя или листа другая.
     * @throws GameInvalidException Если карточки Ожидания нет или запись отвергнута.
     * @throws GameNotFoundException Если листа нет.
     */
    private function endWork(
        GameRecord $game,
        GameBattleRecord $battle,
        int $expectedBattle,
        int $expectedSheet,
        array $current,
        int $actorUserId,
    ): array {
        if ($this->strikesRepo->findOpenAny($battle->getId()) !== null) {
            throw new GameInvalidException('Game strike is open');
        }

        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $waitCode = $this->actionCards->getByCombatAction($slice, 'wait');
        $performed = $this->actionPerform->perform(
            $game,
            $current['type'],
            $current['id'],
            $waitCode,
            $expectedSheet,
            $battle->getPendingActionEffects(),
            true,
        );
        $pending = $performed['pending'];
        $snapshot = $this->snapshot($current);
        $built = $this->ticks->build($game, $slice, $snapshot['sheet']);
        $sheetVersion = $performed['sheetVersion'];
        if ($built['operations'] !== []) {
            try {
                $sheetVersion = $this->sheets->write(
                    $game,
                    $current['type'],
                    $current['id'],
                    $snapshot['actualVersion'],
                    $built['operations'],
                );
            } catch (CharacterInvalidException $exception) {
                throw new GameInvalidException('Game turn sheet is invalid', $exception);
            }
        }

        $cursor = $this->advanced($battle);
        $saved = $this->battles->replaceTracked(
            $battle->getId(),
            $expectedBattle,
            $pending,
            null,
            $cursor['index'],
            $cursor['round'],
        );
        $this->actionChat->post(
            $game,
            $actorUserId,
            $current['type'],
            $current['id'],
            $waitCode,
            $performed['spent'],
        );
        $this->post($game, $built['rolls']);

        return [
            'battleId' => $battle->getId(),
            'version' => $saved->getStateVersion(),
            'sheetVersion' => $sheetVersion,
            'activeIndex' => $cursor['index'],
            'round' => $cursor['round'],
            'pendingActionEffects' => $pending,
        ];
    }

    /**
     * @param array{type: string, id: int} $current Текущий.
     *
     * @return array<string, mixed> Итог.
     */
    private function continueWork(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        int $expectedBattle,
        int $expectedSheet,
        array $current,
    ): array {
        $debtMap = $battle->getActionDebt();
        $key = $current['type'] . ':' . $current['id'];
        $slot = $debtMap[$key] ?? null;
        if (!is_array($slot)) {
            throw new GameInvalidException('Game action debt is missing');
        }

        $snapshot = $this->snapshot($current);
        if ($snapshot['actualVersion'] !== $expectedSheet) {
            throw new GameBattleConflictException($snapshot['actualVersion']);
        }

        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $paid = (new GameActionDebt())->continuePay($slice, $snapshot['sheet'], $slot);
        $sheetVersion = $expectedSheet;
        if ($paid['spend'] !== null) {
            $sheetVersion = $this->sheets->write(
                $game,
                $current['type'],
                $current['id'],
                $expectedSheet,
                [],
                null,
                null,
                [$paid['spend']],
            );
        }

        $attack = $paid['slot']['attack'] ?? null;
        if ($paid['open'] && is_array($attack)) {
            try {
                $opened = $this->open->openPaidAction(
                    $game,
                    $sessionId,
                    $battle,
                    $expectedBattle,
                    $attack,
                    $this->kindForLane($paid['slot']['lane'] ?? null),
                );
            } catch (GameInvalidException $exception) {
                $debtMap[$key] = $paid['slot'];
                $saved = $this->battles->replaceTracked($battle->getId(), $expectedBattle, null, $debtMap, null, null);

                return [
                    'battleId' => $battle->getId(),
                    'version' => $saved->getStateVersion(),
                    'sheetVersion' => $sheetVersion,
                    'opened' => false,
                    'refused' => $exception->getMessage(),
                ];
            }

            return $opened + ['sheetVersion' => $sheetVersion, 'opened' => true];
        }

        $debtMap[$key] = $paid['slot'];
        $saved = $this->battles->replaceTracked($battle->getId(), $expectedBattle, null, $debtMap, null, null);

        return [
            'battleId' => $battle->getId(),
            'version' => $saved->getStateVersion(),
            'sheetVersion' => $sheetVersion,
            'remaining' => $paid['slot']['remaining'] ?? 0,
            'opened' => false,
        ];
    }

    /**
     * Вид удара по полосе долга.
     *
     * @param mixed $lane Полоса слота долга.
     *
     * @return string Вид удара.
     */
    private function kindForLane(mixed $lane): string
    {
        return $lane === '1-n' ? 'wide_strike' : 'strike';
    }

    /**
     * @param array{type: string, id: int} $current Текущий.
     *
     * @return array<string, mixed> Итог.
     */
    private function abortWork(GameBattleRecord $battle, int $expectedBattle, array $current): array
    {
        $debtMap = $battle->getActionDebt();
        $key = $current['type'] . ':' . $current['id'];
        unset($debtMap[$key]);
        $saved = $this->battles->replaceTracked($battle->getId(), $expectedBattle, null, $debtMap, null, null);

        return ['battleId' => $battle->getId(), 'version' => $saved->getStateVersion(), 'aborted' => true];
    }

    /**
     * @return array{type: string, id: int}
     */
    private function current(GameBattleRecord $battle): array
    {
        $order = $battle->getTurnOrder() ?? [];
        $entry = $order[$battle->getActiveIndex()] ?? null;
        if (!is_array($entry) || !is_string($entry['type'] ?? null) || !is_int($entry['id'] ?? null)) {
            throw new GameInvalidException('Game turn order is missing');
        }

        return ['type' => $entry['type'], 'id' => $entry['id']];
    }

    /**
     * @param array{type: string, id: int} $current Текущий.
     */
    private function assertActor(GameRecord $game, int $actorUserId, bool $editAll, array $current): void
    {
        if ($game->getOwnerId() === $actorUserId || $editAll || $this->isGm($game->getId(), $actorUserId)) {
            return;
        }

        if ($current['type'] !== 'character') {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }

        $row = $this->characters->getByPair($game->getId(), $current['id']);
        if ($row->getCharacterOwnerId() !== $actorUserId) {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }
    }

    /**
     * @param array{type: string, id: int} $current Текущий.
     *
     * @return array{actualVersion: int, sheet: array<string, mixed>} Снимок.
     */
    private function snapshot(array $current): array
    {
        if ($current['type'] === 'character') {
            $snapshot = $this->rules->characterSnapshot($current['id']);

            return ['actualVersion' => $snapshot['actualVersion'], 'sheet' => $snapshot['sheet']];
        }

        $npc = (new GameNpcRepository($this->gateway))->getById($current['id']);

        return ['actualVersion' => $npc->getActualVersion(), 'sheet' => $npc->getVersion()['sheet'] ?? []];
    }

    /**
     * @return array{index: int, round: int}
     */
    private function advanced(GameBattleRecord $battle): array
    {
        $order = $battle->getTurnOrder() ?? [];
        $next = $battle->getActiveIndex() + 1;
        if (!array_key_exists($next, $order)) {
            return ['index' => 0, 'round' => $battle->getRound() + 1];
        }

        return ['index' => $next, 'round' => $battle->getRound()];
    }

    /**
     * @param list<array{roll: array<string, mixed>, text: string}> $rolls Сообщения.
     */
    private function post(GameRecord $game, array $rolls): void
    {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            throw new GameInvalidException('Game chat is invalid');
        }

        try {
            foreach ($rolls as $roll) {
                $payload = $roll['roll'];
                $attachment = isset($payload['roll']) ? [['type' => 'roll', 'payload' => $payload['roll']]] : [];
                $this->chats->send($chatId, $game->getOwnerId(), $roll['text'], $attachment, null);
            }
        } catch (ChatNotFoundException | ChatInvalidException $exception) {
            throw new GameInvalidException('Game chat member is invalid', $exception);
        }
    }

    private function isGm(int $gameId, int $actorUserId): bool
    {
        try {
            return $this->members->getByPair($gameId, $actorUserId)->getRole() === 'gm';
        } catch (GameNotFoundException) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $body Тело.
     */
    private function key(array $body): string
    {
        $key = $body['idempotencyKey'] ?? null;
        if (!is_string($key) || $key === '') {
            throw new GameInvalidException('Game turn field is invalid');
        }

        return $key;
    }
}
