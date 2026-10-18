<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * Ручная правка листа: ACL цели, CAS, чат, delivery.
 */
final class GameManualSheetActions
{
    private readonly GameCommandRepository $commands;

    private readonly GameNpcRepository $npcs;

    private readonly GameCharacterRepository $characters;

    private readonly GameMemberRepository $members;

    private readonly GameDeliverySignal $delivery;

    private readonly GameSessionRepository $sessions;

    private readonly GameBattleRepository $battles;

    private readonly GameStrikeSheetWrites $sheetWrites;

    private readonly GameReplayTransaction $replayTransaction;

    private readonly GameManualSheetOperations $operations;

    private readonly GameDeliveryKeys $deliveryKeys;

    /**
     * Создаёт сценарий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     * @param IGames $games Строка игры.
     * @param GameCardAccess $cardAccess Фильтр карточки.
     * @param GameStrikeRules $rules Срез.
     * @param ICharacterActualMutations $mutations Порт листа.
     * @param IChats $chats Чат игры.
     * @param IEventManager $events Доставка.
     *
     * @return void
     */
    public function __construct(
        ISmartTableGateway $smartTableGateway,
        private readonly IGames $games,
        private readonly GameCardAccess $cardAccess,
        private readonly GameStrikeRules $rules,
        ICharacterActualMutations $mutations,
        private readonly IChats $chats,
        IEventManager $events,
    ) {
        $this->commands = new GameCommandRepository($smartTableGateway);
        $this->npcs = new GameNpcRepository($smartTableGateway);
        $this->characters = new GameCharacterRepository($smartTableGateway);
        $this->members = new GameMemberRepository($smartTableGateway);
        $this->sessions = new GameSessionRepository($smartTableGateway);
        $this->battles = new GameBattleRepository($smartTableGateway);
        $this->sheetWrites = new GameStrikeSheetWrites($mutations, $this->npcs);
        $this->replayTransaction = new GameReplayTransaction($smartTableGateway);
        $this->operations = new GameManualSheetOperations();
        $this->deliveryKeys = new GameDeliveryKeys();
        $this->delivery = new GameDeliverySignal($events);
    }

    /**
     * Выполняет правку.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Тело команды.
     *
     * @return array<string, mixed> Узкий итог.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameNotFoundException Если игры или цели нет.
     * @throws GameInvalidException Если команда отвергнута.
     * @throws GameBattleConflictException Если версия или тело ключа чужие.
     */
    public function act(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        $game = $this->games->get($gameId);
        if (!$this->cardAccess->isVisible($game, $actorUserId, $viewAll)) {
            throw new GameNotFoundException('Game was not found');
        }

        $this->assertTargetWriter($game, $actorUserId, $editAll, $body);
        $sessionId = $this->sessions->findSessionId($gameId);
        $key = $body['idempotencyKey'] ?? null;
        if ($sessionId === null || !is_string($key) || $key === '') {
            throw new GameInvalidException('Game manual sheet action is invalid');
        }

        return $this->commit($game, $actorUserId, $sessionId, $key, $body);
    }

    /**
     * Пишет журнал и сигнал.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param int $sessionId Сессия.
     * @param string $key Ключ.
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Итог.
     */
    private function commit(
        GameRecord $game,
        int $actorUserId,
        int $sessionId,
        string $key,
        array $body,
    ): array {
        $execution = $this->replayTransaction->execute(
            fn (): ?array => $this->commands->findByKey($game->getId(), 'manual_sheet', $key),
            fn (): int => $this->commands->reserve($game->getId(), $sessionId, 'manual_sheet', $key, $body),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            fn (): array => $this->perform($game, $actorUserId, $sessionId, $body),
            $body,
        );
        $this->announce($game->getId(), $key, $execution['replay']);

        return $execution['result'];
    }

    /**
     * Шлёт сигнал после первой записи.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ.
     * @param bool $replay Повтор.
     *
     * @return void
     */
    private function announce(int $gameId, string $idempotencyKey, bool $replay): void
    {
        if ($replay) {
            return;
        }

        $stored = $this->commands->findByKey($gameId, 'manual_sheet', $idempotencyKey);
        if ($stored !== null) {
            $this->delivery->recorded(
                $gameId,
                'manual_sheet',
                $stored['id'],
                $this->deliveryKeys->fromManualSheet($stored['result']),
            );
        }
    }

    /**
     * Пишет лист и чат.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param int $sessionId Сессия.
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если команда отвергнута.
     * @throws GameNotFoundException Если цели нет.
     * @throws GameBattleConflictException Если версия листа чужая.
     */
    private function perform(GameRecord $game, int $actorUserId, int $sessionId, array $body): array
    {
        $operation = $body['operation'] ?? null;
        $target = $this->pair($body['target'] ?? null);
        $expected = $body['expectedSheetVersion'] ?? null;
        $details = $body['details'] ?? null;
        if (!is_string($operation) || !is_int($expected) || !is_array($details)) {
            throw new GameInvalidException('Game manual sheet action is invalid');
        }

        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $before = $this->document($game, $target['kind'], $target['id']);
        $effects = $this->operations->effects($operation, $details, $slice);
        $version = $this->sheetWrites->write($game, $target['kind'], $target['id'], $expected, $effects);
        $subject = $target['kind'] === 'npc'
            ? $this->npcs->getById($target['id'])->getName()
            : (is_string($before['choices']['name'] ?? null) ? $before['choices']['name'] : '');
        $this->say($game, $actorUserId, $this->operations->chatText($operation, $details, $slice, $before['sheet'], $subject));
        $battleIds = $this->battles->findIdsBySession($sessionId);
        $battleId = $battleIds === [] ? null : $battleIds[array_key_last($battleIds)];

        return [
            'actualVersion' => $version,
            'battleId' => $battleId,
            'characters' => $target['kind'] === 'character'
                ? [['characterId' => $target['id'], 'actualVersion' => $version]]
                : [],
            'npcs' => $target['kind'] === 'npc'
                ? [['npcId' => $target['id'], 'actualVersion' => $version]]
                : [],
        ];
    }

    /**
     * ACL по цели: GM любой лист, игрок — свой active-персонаж.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param array<string, mixed> $body Тело.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     */
    private function assertTargetWriter(GameRecord $game, int $actorUserId, bool $editAll, array $body): void
    {
        if ($game->getOwnerId() === $actorUserId || $editAll || $this->isGm($game->getId(), $actorUserId)) {
            $this->pair($body['target'] ?? null);

            return;
        }

        $target = $this->pair($body['target'] ?? null);
        if ($target['kind'] !== 'character') {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }

        try {
            $membership = $this->characters->getByPair($game->getId(), $target['id']);
        } catch (GameNotFoundException) {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }

        if ($membership->getCharacterOwnerId() !== $actorUserId || $membership->getStatus() !== 'active') {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }
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
            return $this->members->getByPair($gameId, $actorUserId)->getRole() === 'gm';
        } catch (GameNotFoundException) {
            return false;
        }
    }

    /**
     * Пара kind и id.
     *
     * @param mixed $pair Объект.
     *
     * @return array{kind: string, id: int} Пара.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private function pair(mixed $pair): array
    {
        $kind = is_array($pair) ? ($pair['kind'] ?? null) : null;
        $id = is_array($pair) ? ($pair['id'] ?? null) : null;
        if (($kind !== 'character' && $kind !== 'npc') || !is_int($id) || $id < 1) {
            throw new GameInvalidException('Game manual sheet action is invalid');
        }

        return ['kind' => $kind, 'id' => $id];
    }

    /**
     * Лист цели.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид.
     * @param int $id Id.
     *
     * @return array{choices: array<string, mixed>, sheet: array<string, mixed>} Документ.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если лист NPC битый.
     */
    private function document(GameRecord $game, string $kind, int $id): array
    {
        if ($kind === 'character') {
            $snapshot = $this->rules->characterSnapshot($id);

            return ['choices' => $snapshot['choices'], 'sheet' => $snapshot['sheet']];
        }

        $npc = $this->npcs->getById($id);
        if ($npc->getGameId() !== $game->getId()) {
            throw new GameNotFoundException('Game npc was not found');
        }

        $version = $npc->getVersion();
        $choices = $version['choices'] ?? [];
        $sheet = $version['sheet'] ?? [];
        if (!is_array($choices) || !is_array($sheet)) {
            throw new GameInvalidException('Game npc sheet is invalid');
        }

        return ['choices' => $choices, 'sheet' => $sheet];
    }

    /**
     * Одно текстовое сообщение.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор.
     * @param string $text Текст.
     *
     * @return void
     *
     * @throws GameInvalidException Если чата нет.
     */
    private function say(GameRecord $game, int $actorUserId, string $text): void
    {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            throw new GameInvalidException('Game chat is missing');
        }

        $thread = $this->turnThread($game) ?? $this->sessionThread($game);
        $attachments = $thread === null ? [] : [['type' => 'chat_system', 'payload' => ['thread' => $thread]]];
        $this->chats->send($chatId, $actorUserId, $text, $attachments);
    }

    /**
     * Тред текущего хода открытого боя.
     *
     * @param GameRecord $game Игра.
     *
     * @return array{id: string, parentId: string, kind: string}|null Тред или null.
     */
    private function turnThread(GameRecord $game): ?array
    {
        $sessionId = $this->sessions->findSessionId($game->getId());
        if ($sessionId === null) {
            return null;
        }

        $ids = $this->battles->findIdsBySession($sessionId);
        $battleId = $ids === [] ? null : $ids[array_key_last($ids)];
        if ($battleId === null) {
            return null;
        }

        $battle = $this->battles->find($battleId);
        $order = $battle?->getTurnOrder();
        if ($battle === null || $battle->getRound() < 1 || $order === null) {
            return null;
        }

        $round = $battle->getRound();
        $index = $battle->getActiveIndex();

        return [
            'id' => 'b' . $battleId . '-r' . $round . '-t' . $index,
            'parentId' => 'b' . $battleId . '-r' . $round,
            'kind' => 'turn',
        ];
    }

    /**
     * Тред открытой сессии, когда боя нет.
     *
     * @param GameRecord $game Игра.
     *
     * @return array{id: string, kind: string}|null Тред или null.
     */
    private function sessionThread(GameRecord $game): ?array
    {
        $sessionId = $this->sessions->findSessionId($game->getId());
        if ($sessionId === null) {
            return null;
        }

        return ['id' => 's' . $sessionId, 'kind' => 'session'];
    }
}
