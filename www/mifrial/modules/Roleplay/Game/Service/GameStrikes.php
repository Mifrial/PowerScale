<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Interface\Service\IGameStrikes;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Атака и защита одного удара.
 */
final class GameStrikes implements IGameStrikes
{
    private readonly GameBattleRepository $battles;

    private readonly GameActionRepository $actions;

    private readonly GameCommandRepository $commands;

    private readonly GameNpcRepository $npcs;

    private readonly GameMemberRepository $members;

    private readonly GameCharacterRepository $characters;

    private readonly GameStrikeBody $body;

    private readonly GameDeliverySignal $deliverySignal;

    private readonly GameStrikeAmounts $amounts;

    private readonly GameStrikeSheetWrites $sheetWrites;

    private readonly GameReplayTransaction $replayTransaction;

    private readonly ConflictSheetProjection $conflictProjection;

    private readonly GameStrikeChat $chat;

    private readonly GameStrikeAccess $access;

    private readonly GameStrikeSheets $sheets;

    private readonly GameStrikeSpend $spend;

    private readonly GameStrikeOpen $open;

    private readonly GameStrikeResolution $resolution;

    /**
     * Создаёт фасад.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     * @param IGames $games Строка игры.
     * @param GameCardAccess $cardAccess Фильтр карточки.
     * @param GameSessionRepository $sessions Сессия.
     * @param ICharacterActualMutations $mutations Порт листа.
     * @param GameStrikeRules $rules Срез и версия персонажа.
     * @param ConflictSheetProjection $conflictProjection Проекция конфликтов.
     * @param IEventManager $events Сигнал доставки.
     * @param GameStrikeConsequences $consequences Проверки удара.
     *
     * @return void
     */
    public function __construct(
        ISmartTableGateway $smartTableGateway,
        private readonly IGames $games,
        private readonly GameCardAccess $cardAccess,
        private readonly GameSessionRepository $sessions,
        private readonly ICharacterActualMutations $mutations,
        private readonly GameStrikeRules $rules,
        ConflictSheetProjection $conflictProjection,
        IEventManager $events,
        private readonly GameStrikeConsequences $consequences,
    ) {
        $this->battles = new GameBattleRepository($smartTableGateway);
        $this->actions = new GameActionRepository($smartTableGateway);
        $this->commands = new GameCommandRepository($smartTableGateway);
        $this->npcs = new GameNpcRepository($smartTableGateway);
        $this->characters = new GameCharacterRepository($smartTableGateway);
        $this->members = new GameMemberRepository($smartTableGateway);
        $this->body = new GameStrikeBody();
        $this->deliverySignal = new GameDeliverySignal($events);
        $this->amounts = new GameStrikeAmounts();
        $this->sheetWrites = new GameStrikeSheetWrites($mutations, $this->npcs);
        $this->replayTransaction = new GameReplayTransaction($smartTableGateway);
        $this->conflictProjection = $conflictProjection;
        $this->chat = new GameStrikeChat($this->npcs, $rules);
        $this->access = new GameStrikeAccess($this->members, $this->characters, $this->battles, $cardAccess);
        $this->sheets = new GameStrikeSheets(
            $rules,
            $this->npcs,
            $this->characters,
            $this->members,
            $conflictProjection,
        );
        $this->spend = new GameStrikeSpend($rules, $this->sheetWrites);
        $this->open = new GameStrikeOpen($this->battles, $this->actions, $this->spend);
        $this->resolution = new GameStrikeResolution(
            $rules,
            $this->sheets,
            $this->npcs,
            $consequences,
            $this->amounts,
        );
    }

    /**
     * Открывает удар. Писатель игры или владелец персонажа-атакующего; NPC — только писатель.
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
    public function declareStrike(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $attack,
    ): array {
        $choice = $this->body->attack($attack);
        $ready = $this->ready($gameId, $actorUserId, $editAll, $viewAll, $idempotencyKey, [
            'attack' => $choice,
            'battleId' => $battleId,
            'expectedVersion' => $expectedBattleVersion,
        ], $choice['attacker']);
        if ($ready['replay'] !== null) {
            return $ready['replay'];
        }

        return $this->open(
            $ready['game'],
            $ready['sessionId'],
            $battleId,
            $idempotencyKey,
            $expectedBattleVersion,
            $choice,
        );
    }

    /**
     * Закрывает удар. Писатель игры или владелец персонажа-защитника; NPC — только писатель.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param int $expectedSheetVersion Версия листа.
     * @param array $defense Выбор.
     *
     * @return array<string, mixed> Итог.
     */
    public function resolveStrike(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        int $expectedSheetVersion,
        array $defense,
    ): array {
        $choice = $this->body->defense($defense);
        $ready = $this->ready($gameId, $actorUserId, $editAll, $viewAll, $idempotencyKey, [
            'battleId' => $battleId,
            'defense' => $choice,
            'expectedSheetVersion' => $expectedSheetVersion,
            'expectedVersion' => $expectedBattleVersion,
        ]);
        if ($ready['replay'] !== null) {
            return $ready['replay'];
        }

        return $this->close(
            $ready['game'],
            $ready['sessionId'],
            $battleId,
            $idempotencyKey,
            $expectedBattleVersion,
            $expectedSheetVersion,
            $choice,
            $actorUserId,
            $editAll,
            $viewAll,
        );
    }

    /**
     * Видимость, повтор, право и сессия.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Ключ.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $requestBody Тело.
     * @param array<string, mixed>|null $actingEntity Действующий персонаж или NPC.
     *
     * @return array{game: GameRecord, sessionId: int, replay: array<string, mixed>|null} Ход.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameNotFoundException Если карточки нет.
     * @throws GameInvalidException Если ключ, статус или сессия.
     * @throws GameBattleConflictException Если тело ключа чужое.
     */
    private function ready(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        string $idempotencyKey,
        array $requestBody,
        ?array $actingEntity = null,
    ): array {
        $game = $this->games->get($gameId);
        $this->access->assertVisible($game, $actorUserId, $viewAll);

        $replay = $this->findReplay($gameId, $idempotencyKey, $requestBody);
        if ($replay !== null) {
            return ['game' => $game, 'sessionId' => 0, 'replay' => $replay];
        }

        if ($idempotencyKey === '' || $game->getStatus() === 'completed') {
            throw new GameInvalidException('Game strike is invalid');
        }

        if ($actingEntity !== null) {
            $this->access->assertAttacker($game, $actorUserId, $editAll, $actingEntity);
        }

        $sessionId = $this->sessions->findSessionId($gameId);
        if ($sessionId === null) {
            throw new GameInvalidException('Game session is not running');
        }

        return ['game' => $game, 'sessionId' => $sessionId, 'replay' => null];
    }

    /**
     * Пишет открытый удар и версию боя.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия.
     * @param array $choice Выбор.
     *
     * @return array<string, mixed> Итог.
     */
    private function open(
        GameRecord $game,
        int $sessionId,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $choice,
    ): array {
        $battle = $this->battleOf($sessionId, $battleId, $expectedBattleVersion);
        $this->access->assertRoster($battleId, $choice['attacker'], [$choice['defender']]);
        if ($this->actions->findOpenAny($battleId) !== null) {
            throw new GameInvalidException('Game strike is already open');
        }

        $this->rules->assertAttack(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['actionRuleCode'],
            $choice['itemInventoryId'],
            $choice['itemRuleCode'],
            $choice['profileType'],
            $choice['profileIndex'],
            $choice['attacker']['type'],
            $choice['attacker']['id'],
            $choice['attacker']['type'] === 'npc'
                ? $this->npcChoices($game->getId(), $choice['attacker']['id'])
                : null,
        );
        $this->rules->resolveInventorySpend(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['itemRuleCode'],
            $choice['profileType'],
            $choice['profileIndex'],
            $choice['itemInventoryId'],
            $choice['ammunitionInventoryId'] ?? null,
            $choice['attacker']['type'],
            $choice['attacker']['id'],
            $choice['attacker']['type'] === 'npc'
                ? $this->npcChoices($game->getId(), $choice['attacker']['id'])
                : null,
        );
        $body = [
            'attack' => $choice,
            'battleId' => $battleId,
            'expectedVersion' => $expectedBattleVersion,
        ];

        $execution = $this->replayTransaction->execute(
            fn (): ?array => $this->commands->findByKey($game->getId(), 'strike', $idempotencyKey),
            fn (): int => $this->commands->reserve(
                $game->getId(),
                $sessionId,
                'strike',
                $idempotencyKey,
                $body,
            ),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            fn (): array => $this->executeOpenTransaction(
                $game,
                $sessionId,
                $battle,
                $expectedBattleVersion,
                $choice,
            ),
            $body,
        );
        if ($execution['replay']) {
            return $execution['result'];
        }

        $this->announce($game->getId(), $idempotencyKey);

        return $execution['result'];
    }

    /**
     * Закрывает удар и пишет деление повреждения в лист цели.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param int $expectedSheetVersion Версия листа.
     * @param array{reaction: string, blockItemInventoryId: int|null, blockItemProfileIndex: int|null, blockItemRuleCode: string|null} $choice Защита.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameInvalidException Если удара нет.
     */
    private function close(
        GameRecord $game,
        int $sessionId,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        int $expectedSheetVersion,
        array $choice,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
    ): array {
        $open = $this->openForClose($battleId, $game, $actorUserId, $editAll);
        $battle = $this->battleOf($sessionId, $battleId, $expectedBattleVersion);
        $requestChoice = $choice;
        $this->assertReaction($game, $open, $choice);
        $choice = $this->authoritativeChoice($game, $open, $choice);
        $strikeId = $open['id'] ?? null;
        if (!is_int($strikeId)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        $this->sheets->assertDefenderSheet($game, $open, $expectedSheetVersion, $actorUserId, $viewAll);
        $hitCode = $this->rules->findHitCode($game->getSpaceId(), $game->getRulesRevision());
        $body = [
            'battleId' => $battleId,
            'defense' => $requestChoice,
            'expectedSheetVersion' => $expectedSheetVersion,
            'expectedVersion' => $expectedBattleVersion,
        ];
        $execution = $this->replayTransaction->execute(
            fn (): ?array => $this->commands->findByKey($game->getId(), 'strike', $idempotencyKey),
            fn (): int => $this->commands->reserve(
                $game->getId(),
                $sessionId,
                'strike',
                $idempotencyKey,
                $body,
            ),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            fn (): array => $this->executeCloseTransaction(
                $game,
                $battle,
                $expectedBattleVersion,
                $expectedSheetVersion,
                $choice,
                $strikeId,
                $open,
                $hitCode,
                $actorUserId,
                $viewAll,
            ),
            $body,
        );
        if ($execution['replay']) {
            return $execution['result'];
        }

        $this->announce($game->getId(), $idempotencyKey);

        return $execution['result'];
    }

    /**
     * Сигнал доставки по id уже лежащей команды. Лист не пишет.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ.
     *
     * @return void
     */
    private function announce(int $gameId, string $idempotencyKey): void
    {
        $stored = $this->commands->findByKey($gameId, 'strike', $idempotencyKey);
        if ($stored === null) {
            return;
        }

        $this->deliverySignal->recorded($gameId, 'strike', $stored['id'], []);
    }

    /**
     * Ищет сохранённый итог команды.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $requestBody Тело.
     *
     * @return array<string, mixed>|null Итог или null.
     *
     * @throws GameBattleConflictException Если тело отличается.
     * @throws GameInvalidException Если тело битое.
     */
    private function findReplay(int $gameId, string $idempotencyKey, array $requestBody): ?array
    {
        return $this->replayTransaction->find(
            fn (): ?array => $this->commands->findByKey($gameId, 'strike', $idempotencyKey),
            $requestBody,
        );
    }

    /**
     * Пишет открытие удара внутри transaction.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattleVersion Ожидаемая версия.
     * @param array<string, mixed> $choice Выбор.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если поле удара невалидно.
     * @throws GameNotFoundException Если сессия не найдена.
     * @throws GameBattleConflictException Если версия боя другая.
     */
    private function executeOpenTransaction(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        int $expectedBattleVersion,
        array $choice,
    ): array {
        $kind = $choice['attacker']['type'] ?? null;
        $id = $choice['attacker']['id'] ?? null;
        if (!is_string($kind) || !is_int($id)) {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        $snapshot = $kind === 'character'
            ? $this->sheets->getCharacterSnapshot($id)
            : $this->sheets->getNpcSnapshot($game->getId(), $id);
        $row = $this->openRow($battle, $sessionId, $choice);
        $opened = $this->open->open(
            $game,
            $choice,
            $battle,
            $expectedBattleVersion,
            $snapshot,
            $row['action'],
            $row['targets'],
            '1-1',
            'Game strike',
        );
        $result = [
            'battleId' => $battle->getId(),
            'strikeId' => $opened['strikeId'],
            'version' => $opened['version'],
            'sheetVersion' => null,
        ];
        if ($opened['debt']) {
            $result['debt'] = true;
        }

        return $result;
    }

    /**
     * Открывает удар, когда F1 уже уплачены. Инвентарь при отказе не коммитит этот вызов.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattleVersion Версия.
     * @param array<string, mixed> $choice Тело declare.
     *
     * @return array<string, mixed> Итог.
     */
    public function openPaid(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        int $expectedBattleVersion,
        array $choice,
    ): array {
        $kind = $choice['attacker']['type'] ?? null;
        $id = $choice['attacker']['id'] ?? null;
        if (!is_string($kind) || !is_int($id)) {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        $snapshot = $kind === 'character'
            ? $this->sheets->getCharacterSnapshot($id)
            : $this->sheets->getNpcSnapshot($game->getId(), $id);
        $inventorySpend = $this->rules->resolveInventorySpend(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['itemRuleCode'],
            $choice['profileType'],
            $choice['profileIndex'],
            $choice['itemInventoryId'],
            $choice['ammunitionInventoryId'] ?? null,
            $kind,
            $id,
            $kind === 'npc' ? $snapshot['sheet']['choices'] : null,
        );
        if ($inventorySpend !== null) {
            $this->sheetWrites->write(
                $game,
                $kind,
                $id,
                $snapshot['actualVersion'],
                [],
                null,
                null,
                [],
                $inventorySpend,
            );
        }

        $debt = $this->spend->releaseDebt($battle, $kind, $id);
        $saved = $this->battles->replaceTracked(
            $battle->getId(),
            $expectedBattleVersion,
            $battle->getPendingActionEffects(),
            $debt,
            null,
            null,
        );

        $row = $this->openRow($battle, $sessionId, $choice);

        return [
            'battleId' => $battle->getId(),
            'strikeId' => $this->actions->addWithTargets($row['action'], $row['targets']),
            'version' => $saved->getStateVersion(),
        ];
    }

    /**
     * Пишет закрытие удара внутри transaction.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattleVersion Ожидаемая версия.
     * @param int $expectedSheetVersion Ожидаемая версия листа.
     * @param array<string, mixed> $open Открытый удар.
     * @param array{reaction: string, blockItemInventoryId: int|null, blockItemProfileIndex: int|null, blockItemRuleCode: string|null} $choice Защита.
     * @param int $strikeId Удар.
     * @param array<string, mixed> $open Открытый удар.
     * @param string $hitCode Код попадания.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если поле удара невалидно.
     * @throws GameNotFoundException Если цель не найдена.
     * @throws GameBattleConflictException Если версия цели или боя другая.
     */
    private function executeCloseTransaction(
        GameRecord $game,
        GameBattleRecord $battle,
        int $expectedBattleVersion,
        int $expectedSheetVersion,
        array $choice,
        int $strikeId,
        array $open,
        string $hitCode,
        int $actorUserId,
        bool $viewAll,
    ): array {
        try {
            return $this->runCloseTransaction(
                $game,
                $battle,
                $expectedBattleVersion,
                $expectedSheetVersion,
                $choice,
                $strikeId,
                $open,
                $hitCode,
                $actorUserId,
                $viewAll,
            );
        } catch (GameInvalidException $exception) {
            throw $exception;
        }
    }

    /**
     * Тело закрытия удара.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattleVersion Версия боя.
     * @param int $expectedSheetVersion Версия листа.
     * @param array{reaction: string, blockItemInventoryId: int|null, blockItemProfileIndex: int|null, blockItemRuleCode: string|null} $choice Защита.
     * @param int $strikeId Удар.
     * @param array<string, mixed> $open Строка.
     * @param string $hitCode Код попадания.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array<string, mixed> Итог.
     */
    private function runCloseTransaction(
        GameRecord $game,
        GameBattleRecord $battle,
        int $expectedBattleVersion,
        int $expectedSheetVersion,
        array $choice,
        int $strikeId,
        array $open,
        string $hitCode,
        int $actorUserId,
        bool $viewAll,
    ): array {
        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $pending = $battle->getPendingActionEffects();
        $defenderPending = $pending;
        $defenderKind = $open['defender_kind'] ?? null;
        $defenderId = $open['defender_id'] ?? null;
        if (!is_string($defenderKind) || !is_int($defenderId)) {
            throw new GameInvalidException('Game strike defender is invalid');
        }

        $reactionRuleCode = $choice['reaction'] === 'ignore'
            ? null
            : $this->reactionRuleCode($slice, $choice['reaction']);
        $defenderChoices = [];
        if ($reactionRuleCode !== null || $choice['reaction'] === 'block') {
            $defenderChoices = $this->defenderChoices($game, $defenderKind, $defenderId);
        }

        $spends = $choice['reaction'] === 'ignore'
            ? []
            : $this->spend->getReactionSpends(
                $game,
                $choice,
                $defenderChoices,
                $this->sheets->getDefenderSheet($game, $open),
            );
        if ($reactionRuleCode !== null) {
            $declared = (new GamePendingActionCosts())->declare(
                $slice,
                $defenderChoices,
                $reactionRuleCode,
                $defenderKind,
                $defenderId,
                $defenderPending,
                $this->spend->getSpendAmounts($spends),
                false,
            );
            $spends = $this->spend->getSpendRows($declared['amounts']);
            $defenderPending = $declared['pending'];
            $defenderKey = $defenderKind . ':' . $defenderId;
            if (array_key_exists($defenderKey, $defenderPending)) {
                $pending[$defenderKey] = $defenderPending[$defenderKey];
            } else {
                unset($pending[$defenderKey]);
            }
        }

        $attackerRoll = $this->resolution->rollOf($game, $open, $hitCode, $pending, $slice);
        $defenderRoll = null;
        if ($choice['reaction'] !== 'ignore' && $this->rules->isUnconscious($game->getSpaceId(), $game->getRulesRevision(), $this->sheets->getDefenderSheet($game, $open))) {
            throw new GameInvalidException('Game strike defender is unconscious');
        }

        if ($choice['reaction'] === 'dodge') {
            $defenderRoll = $this->resolution->dodgeRollOf(
                $game,
                $open,
                $hitCode,
                $attackerRoll,
                $defenderPending,
                $reactionRuleCode,
                $slice,
            );
            $attackerRoll = $this->rules->rerateHit($attackerRoll, $defenderRoll);
        }

        if ($choice['reaction'] === 'block') {
            $defenderRoll = $this->resolution->blockRollOf(
                $game,
                $open,
                $choice,
                $hitCode,
                $attackerRoll,
                $defenderPending,
                $reactionRuleCode,
                $slice,
            );
        }

        if (
            $choice['reaction'] !== 'ignore'
            && $spends !== [] && !$this->rules->hasSufficientResources(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $this->sheets->getDefenderSheet($game, $open),
                $spends,
            )
        ) {
            return $this->resourceIgnoredResult($battle, $strikeId, $expectedBattleVersion, $attackerRoll, $defenderRoll);
        }

        $autoFail = $this->rules->isHitAutoFail($attackerRoll['roll']);
        $damage = $autoFail ? $this->amounts->zero() : $this->resolution->damageOf($game, $open);
        $result = $this->closeResult(
            $game,
            $battle,
            $open,
            $hitCode,
            $damage,
            $strikeId,
            $expectedBattleVersion,
            $attackerRoll,
            $defenderRoll,
            $choice['reaction'],
        );
        $resistance = $result['success'] === 0
            ? $this->amounts->zero()
            : $this->resolution->resistanceOf(
                $game,
                $open,
                $this->resolution->resistanceChoice($choice, $result['defenderRoll'] ?? null),
                $result['success'],
                $this->resolution->penetrationOf($game, $open),
            );
        $soak = $choice['reaction'] === 'dodge' ? $this->resolution->soakOf($game, $open) : null;
        $result['resistance'] = $this->amounts->view($resistance);
        if ($soak !== null) {
            $result['S'] = $this->amounts->view($soak);
        }

        $effectiveDamage = $result['success'] === 0 ? $this->amounts->zero() : $damage;
        $injury = $this->amounts->injury($effectiveDamage, $resistance, $result['success'], $soak);
        $result['injury'] = $this->amounts->view($injury);
        $prepared = $autoFail ? null : $this->resolution->prepareSheet(
            $game,
            $open,
            $injury,
            $result['success'],
        );
        if (is_array($prepared)) {
            $result = array_merge($result, $prepared['consequence']);
        }

        $result['sheetVersion'] = $autoFail && $spends === []
            ? null
            : ($autoFail
                ? $this->writeResources(
                    $game,
                    $open,
                    $expectedSheetVersion,
                    $actorUserId,
                    $viewAll,
                    $spends,
                )
                : $this->writePrepared(
                    $game,
                    $open,
                    $expectedSheetVersion,
                    $prepared['operations'] ?? [],
                    $actorUserId,
                    $viewAll,
                    $spends,
                ));
        $this->postStrikeChat($game, $battle, $open, $actorUserId, $strikeId, $choice['reaction'], $result);
        $this->actions->closeWithAnswer($strikeId, [
            'reaction' => $choice['reaction'],
            'block_item_inventory_id' => $choice['blockItemInventoryId'],
            'block_item_profile_index' => $choice['blockItemProfileIndex'],
            'block_item_rule_code' => $choice['blockItemRuleCode'],
        ]);

        $attackerKind = $open['attacker_kind'] ?? null;
        $attackerId = $open['attacker_id'] ?? null;
        if (!is_string($attackerKind) || !is_int($attackerId)) {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        $actionRuleCode = is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '';
        // Close не хранит фактическую цену с учётом cost-эффектов, поэтому
        // уменьшение идёт по базовой стоимости карточки. Для текущих карточек
        // порог висящей помехи (2 ОД) меньше базовых 4 ОД, остатка не возникает.
        $pending = (new GamePendingActionCosts())->complete(
            $slice,
            $this->attackerChoices($game, $open),
            $actionRuleCode,
            $attackerKind,
            $attackerId,
            $pending,
            ['action-points' => $this->spend->getActionAp($slice, $actionRuleCode)],
        );
        if ($reactionRuleCode !== null) {
            $pending = (new GamePendingActionCosts())->complete(
                $slice,
                $defenderChoices,
                $reactionRuleCode,
                $defenderKind,
                $defenderId,
                $pending,
                $this->spend->getSpendAmounts($spends),
            );
        }

        $this->battles->replacePending($battle->getId(), $expectedBattleVersion, $pending);

        return $result;
    }

    /**
     * Списывает reaction resource без принятого эффекта auto-fail.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param int $expectedSheetVersion Ожидаемая версия листа.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     * @param array<int, ResourceSpend> $spends Списания.
     *
     * @return int Версия после записи.
     *
     * @throws GameInvalidException Если строка невалидна.
     * @throws GameNotFoundException Если цель отсутствует.
     * @throws GameBattleConflictException Если версия листа другая.
     */
    private function writeResources(
        GameRecord $game,
        array $open,
        int $expectedSheetVersion,
        int $actorUserId,
        bool $viewAll,
        array $spends,
    ): int {
        $kind = $open['defender_kind'] ?? null;
        $defenderId = $open['defender_id'] ?? null;
        if (($kind !== 'character' && $kind !== 'npc') || !is_int($defenderId)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        return $this->sheetWrites->writeResources(
            $game,
            $kind,
            $defenderId,
            $expectedSheetVersion,
            $spends,
            ['type' => $kind, 'id' => $defenderId],
            fn (CharacterConflictException|GameEconomyConflictException $exception): array => $this->sheets->projectWriteConflict(
                $game,
                ['type' => $kind, 'id' => $defenderId],
                $exception,
                $actorUserId,
                $viewAll,
            ),
        );
    }

    /**
     * Возвращает authoritative choices защитника.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид цели.
     * @param int $id Идентификатор.
     *
     * @return array<string, mixed> Choices.
     *
     * @throws GameInvalidException If NPC document is invalid.
     * @throws GameNotFoundException If target is absent.
     */
    private function defenderChoices(GameRecord $game, string $kind, int $id): array
    {
        if ($kind === 'character') {
            return $this->rules->characterSnapshot($id)['choices'];
        }

        return $this->npcChoices($game->getId(), $id);
    }

    /**
     * Подменяет код блока кодом выбранного inventory instance.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param array<string, mixed> $choice Защита.
     *
     * @return array<string, mixed> Authoritative выбор.
     *
     * @throws GameInvalidException Если строка или выбор битые.
     * @throws GameNotFoundException Если цель отсутствует.
     */
    private function authoritativeChoice(GameRecord $game, array $open, array $choice): array
    {
        if ($choice['reaction'] !== 'block') {
            return $choice;
        }

        $kind = $open['defender_kind'] ?? null;
        $id = $open['defender_id'] ?? null;
        $inventoryId = $choice['blockItemInventoryId'] ?? null;
        if (!is_string($kind) || !is_int($id) || !is_int($inventoryId)) {
            throw new GameInvalidException('Game strike block item is invalid');
        }

        $choice['blockItemRuleCode'] = $this->rules->getBlockItemRuleCode(
            $this->defenderChoices($game, $kind, $id),
            $inventoryId,
        );

        return $choice;
    }

    /**
     * Формирует существующий result envelope для automatic ignore.
     *
     * @param GameBattleRecord $battle Бой.
     * @param int $strikeId Удар.
     * @param int $expectedBattleVersion Ожидаемая версия.
     *
     * @return array<string, mixed> Result без mutation.
     */
    private function resourceIgnoredResult(
        GameBattleRecord $battle,
        int $strikeId,
        int $expectedBattleVersion,
        array $attackerRoll,
        ?array $defenderRoll,
    ): array {
        return [
            'battleId' => $battle->getId(),
            'strikeId' => $strikeId,
            'version' => $expectedBattleVersion,
            'attackerRoll' => $attackerRoll,
            'defenderRoll' => $defenderRoll,
            'success' => 0,
            'damage' => $this->amounts->view($this->amounts->zero()),
            'resistance' => $this->amounts->view($this->amounts->zero()),
            'injury' => $this->amounts->view($this->amounts->zero()),
            'sheetVersion' => null,
        ];
    }

    /**
     * Создаёт базовый итог закрытия удара.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $open Открытый удар.
     * @param array{blockItemInventoryId: int|null, blockItemProfileIndex: int|null} $choice Защита.
     * @param string $hitCode Код попадания.
     * @param DimensionalNumber $damage Урон.
     * @param int $strikeId Удар.
     * @param int $expectedBattleVersion Ожидаемая версия.
     *
     * @return array<string, mixed> Базовый итог.
     *
     * @throws GameInvalidException Если строка удара невалидна.
     * @throws GameNotFoundException Если правило не найдено.
     */
    private function closeResult(
        GameRecord $game,
        GameBattleRecord $battle,
        array $open,
        string $hitCode,
        DimensionalNumber $damage,
        int $strikeId,
        int $expectedBattleVersion,
        array $attackerRoll,
        ?array $defenderRoll,
        string $reaction,
    ): array {
        $success = $this->resolution->successOf($attackerRoll, $defenderRoll, $reaction);
        $effectiveDamage = $success === 0 ? $this->amounts->zero() : $damage;

        $result = [
            'battleId' => $battle->getId(),
            'strikeId' => $strikeId,
            'version' => $expectedBattleVersion + 1,
            'attackerRoll' => $attackerRoll,
            'success' => $success,
            'damage' => $this->amounts->view($effectiveDamage),
            'resistance' => $this->amounts->view($this->amounts->zero()),
            'sheetVersion' => null,
        ];
        if ($defenderRoll !== null) {
            $result['defenderRoll'] = $defenderRoll;
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $operations Операции.
     */
    private function writePrepared(
        GameRecord $game,
        array $open,
        int $expectedSheetVersion,
        array $operations,
        int $actorUserId,
        bool $viewAll,
        array $spends = [],
    ): int {
        $kind = $open['defender_kind'] ?? null;
        $defenderId = $open['defender_id'] ?? null;
        if (($kind !== 'character' && $kind !== 'npc') || !is_int($defenderId)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        return $this->sheetWrites->write(
            $game,
            $kind,
            $defenderId,
            $expectedSheetVersion,
            $operations,
            ['type' => $kind, 'id' => $defenderId],
            fn (CharacterConflictException|GameEconomyConflictException $exception): array => $this->sheets->projectWriteConflict(
                $game,
                ['type' => $kind, 'id' => $defenderId],
                $exception,
                $actorUserId,
                $viewAll,
            ),
            $spends,
        );
    }

    /**
     * @param array<string, mixed> $result Итог close.
     */
    private function postStrikeChat(
        GameRecord $game,
        GameBattleRecord $battle,
        array $open,
        int $actorUserId,
        int $strikeId,
        string $reaction,
        array $result,
    ): void {
        $threadId = 'attack-' . $strikeId;
        $parentId = GameCombatThread::buildAttack($battle, $threadId)['parentId'];
        $defender = $this->chat->getEntityToken($open['defender_kind'] ?? 'character', $open['defender_id'] ?? 0);
        $attacker = $this->chat->getEntityToken($open['attacker_kind'] ?? 'character', $open['attacker_id'] ?? 0);
        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $actionCode = is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '';
        $action = $actionCode !== '' ? '[[rule:' . $actionCode . ']]' : 'действие';
        $actionAp = $this->spend->getActionAp($slice, $actionCode);
        $weapon = is_string($open['item_rule_code'] ?? null) ? '[[rule:' . $open['item_rule_code'] . ']]' : 'оружием';
        $kind = match ($open['profile_type'] ?? 'strike') {
            'throw' => 'бросок',
            'shoot' => 'выстрел',
            default => 'удар',
        };
        $typeBit = $this->chat->getDamageTypeBit(
            $game,
            $open['item_rule_code'] ?? null,
            $open['profile_type'] ?? null,
            $open['profile_index'] ?? null,
            $slice,
        );
        $narrative = $attacker . ' наносит ' . $typeBit . ' ' . $kind . ' оружием ' . $weapon . ' по ' . $defender . '.';
        if ($reaction === 'ignore') {
            $narrative .= ' Тот не реагирует.';
        } else {
            $reactionCode = $this->reactionRuleCode($slice, $reaction);
            $narrative .= ' ' . $this->chat->getReactionBit(
                'Тот',
                $reaction,
                $reactionCode,
                $this->spend->getActionAp($slice, $reactionCode ?? ''),
            ) . '!';
        }

        $exhaustion = $result['exhaustionDelta'] ?? 0;
        $wound = $result['wound'] ?? 0;
        $success = $result['success'] ?? 0;
        $bits = [$exhaustion . ' истощения'];
        if ($wound > 0) {
            $bits[] = $wound . ' рану';
        }

        $will = isset($result['will']) ? [
            'roll' => $result['will'],
            'text' => $this->chat->getExhaustionText($defender, $result['will']['rating'] ?? 0, $result['willOutcome'] ?? 'clear'),
        ] : null;
        $maim = isset($result['maim']) ? $this->chat->getMaimPayload($defender, $result['maim'], $typeBit) : null;
        $this->consequences->post(
            $game,
            $actorUserId,
            $threadId,
            true,
            $attacker . ' совершает действие ' . $action . ' за ' . $actionAp . 'ОД.',
            $narrative,
            $result['attackerRoll'],
            $success > 0
                ? $attacker . ' попадает по ' . $defender . ' с ' . $success . ' РУ и наносит ' . implode(' и ', $bits) . '!'
                : $attacker . ' промахивается по ' . $defender . '!',
            [
                'raw' => is_array($result['injury'] ?? null) ? ($result['injury']['base'] ?? 0) : 0,
                'damage' => is_array($result['damage'] ?? null) ? $result['damage'] : ['base' => 0, 'size' => 0],
                'damageTypeName' => $typeBit,
                'resistance' => is_array($result['resistance'] ?? null) ? ($result['resistance']['base'] ?? 0) : 0,
                'endurance' => is_array($result['endurance'] ?? null) ? $result['endurance'] : ['base' => 0, 'size' => 0],
                'defenseIgnored' => false,
                'attackSrLabel' => (string) $success,
                'appliedSr' => $success,
                'srCap' => null,
                'heading' => null,
                'stun' => null,
                'shock' => null,
                'exhaustion' => $exhaustion,
                'remainingHpDamage' => 0,
                'wound' => $wound,
                'knockout' => ($result['unconscious'] ?? false) === true,
                'cuttingWound' => null,
                'layers' => [],
            ],
            $will,
            $maim,
            $this->chat->getSpeakerOf($open['attacker_kind'] ?? 'character', $open['attacker_id'] ?? 0),
            is_array($result['defenderRoll'] ?? null) ? $result['defenderRoll'] : null,
            $parentId,
        );
    }

    private function reactionRuleCode(CharacterRuleSlice $slice, string $reaction): ?string
    {
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->getSpec();
            if ($spec instanceof AbilityActionSpec && $spec->getBase()->getCombatAction() === $reaction) {
                return $rule->getCode();
            }
        }

        return null;
    }

    /**
     * Реакция и предмет блока.
     *
     * @param GameRecord $game Игра.
     * @param array{reaction: string, blockItemRuleCode: string|null} $choice Защита.
     *
     * @return void
     *
     * @throws GameInvalidException Если реакция чужая.
     */
    private function assertReaction(GameRecord $game, array $open, array $choice): void
    {
        if (!in_array($choice['reaction'], ['ignore', 'dodge', 'block'], true)) {
            throw new GameInvalidException('Game strike reaction is invalid');
        }

        if (
            $choice['reaction'] === 'block'
            && (!is_int($choice['blockItemInventoryId'])
                || !is_int($choice['blockItemProfileIndex']))
        ) {
            throw new GameInvalidException('Game strike block item is invalid');
        }

        if ($choice['reaction'] === 'block') {
            $kind = $open['defender_kind'] ?? null;
            $id = $open['defender_id'] ?? null;
            if (!is_string($kind) || !is_int($id)) {
                throw new GameInvalidException('Game strike defender is invalid');
            }

            $this->rules->assertBlockSelection(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $this->defenderChoices($game, $kind, $id),
                $choice['blockItemInventoryId'],
                $choice['blockItemProfileIndex'],
                $choice['blockItemRuleCode'],
            );
        }
    }

    /**
     * Выборы атакующего из строки удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Строка удара.
     *
     * @return array<string, mixed> Выборы.
     *
     * @throws GameInvalidException Если строка битая.
     * @throws GameNotFoundException Если документа нет.
     */
    private function attackerChoices(GameRecord $game, array $open): array
    {
        $kind = $open['attacker_kind'] ?? null;
        $id = $open['attacker_id'] ?? null;
        if (!is_string($kind) || !is_int($id)) {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        return $kind === 'character'
            ? $this->rules->characterSnapshot($id)['choices']
            : $this->npcChoices($game->getId(), $id);
    }

    /**
     * Документ choices NPC этой игры.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return array<string, mixed> Документ.
     *
     * @throws GameInvalidException Если строка чужая или документа нет.
     * @throws GameNotFoundException Если строки нет.
     */
    private function npcChoices(int $gameId, int $npcId): array
    {
        $npc = $this->npcs->getById($npcId);
        $choices = $npc->getGameId() === $gameId ? ($npc->getVersion()['choices'] ?? null) : null;
        if (!is_array($choices)) {
            throw new GameInvalidException('Game strike npc choices are missing');
        }

        return $choices;
    }

    /**
     * Бой этой сессии с ожидаемой версией.
     *
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param int $expectedBattleVersion Версия.
     *
     * @return GameBattleRecord Строка.
     *
     * @throws GameNotFoundException Если боя нет.
     * @throws GameBattleConflictException Если версия другая.
     */
    private function battleOf(int $sessionId, int $battleId, int $expectedBattleVersion): GameBattleRecord
    {
        $battle = $this->battles->find($battleId);
        if ($battle === null || $battle->getSessionId() !== $sessionId) {
            throw new GameNotFoundException('Game battle was not found');
        }

        if ($battle->getStateVersion() !== $expectedBattleVersion) {
            throw new GameBattleConflictException($battle->getStateVersion());
        }

        return $battle;
    }

    /**
     * Колонки открытого удара.
     *
     * @param GameBattleRecord $battle Бой.
     * @param int $sessionId Сессия.
     * @param array $choice Выбор.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameInvalidException Если вид профиля чужой.
     */
    private function openRow(GameBattleRecord $battle, int $sessionId, array $choice): array
    {
        if (!in_array($choice['profileType'], ['strike', 'throw', 'shoot'], true)) {
            throw new GameInvalidException('Game strike profile is invalid');
        }

        return [
            'action' => [
                'kind' => 'strike',
                'battle_id' => $battle->getId(),
                'session_id' => $sessionId,
                'attacker_kind' => $choice['attacker']['type'],
                'attacker_id' => $choice['attacker']['id'],
                'action_rule_code' => $choice['actionRuleCode'],
                'item_inventory_id' => $choice['itemInventoryId'],
                'item_rule_code' => $choice['itemRuleCode'],
                'profile_type' => $choice['profileType'],
                'profile_index' => $choice['profileIndex'],
                'open' => true,
            ],
            'targets' => [
                [
                    'target_type' => $choice['defender']['type'],
                    'target_id' => $choice['defender']['id'],
                    'reaction' => null,
                    'block_item_inventory_id' => null,
                    'block_item_profile_index' => null,
                    'block_item_rule_code' => null,
                ],
            ],
        ];
    }

    /**
     * Открытый удар для закрытия с проверкой актора.
     *
     * @param int $battleId Бой.
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     *
     * @return array<string, mixed> Строка удара.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameInvalidException Если удара нет или строка битая.
     */
    private function openForClose(int $battleId, GameRecord $game, int $actorUserId, bool $editAll): array
    {
        $open = $this->openRowOf($this->actions->findOpenWithTargets($battleId, 'strike'));
        if ($open !== null) {
            $this->access->assertTarget($game, $actorUserId, $editAll, $this->defenderOf($open));

            return $open;
        }

        if ($this->access->isWriter($game, $actorUserId, $editAll)) {
            throw new GameInvalidException('Game strike is not open');
        }

        $latest = $this->openRowOf($this->actions->findLatestWithTargets($battleId, 'strike'));
        if ($latest === null) {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }

        $this->access->assertTarget($game, $actorUserId, $editAll, $this->defenderOf($latest));
        throw new GameInvalidException('Game strike is not open');
    }

    /**
     * Строка удара из канонической формы действия.
     *
     * @param array<string, mixed>|null $row Действие с целями.
     *
     * @return array<string, mixed>|null Колонки в прежней форме `defender_*`.
     */
    private function openRowOf(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        $targets = is_array($row['targets'] ?? null) ? $row['targets'] : [];
        $target = is_array($targets[0] ?? null) ? $targets[0] : [];
        unset($row['targets']);

        return array_merge($row, [
            'defender_kind' => $target['target_type'] ?? null,
            'defender_id' => $target['target_id'] ?? null,
            'reaction' => $target['reaction'] ?? null,
            'block_item_inventory_id' => $target['block_item_inventory_id'] ?? null,
            'block_item_profile_index' => $target['block_item_profile_index'] ?? null,
            'block_item_rule_code' => $target['block_item_rule_code'] ?? null,
            'expected_sheet_version' => $target['expected_sheet_version'] ?? null,
        ]);
    }

    /**
     * Защитник из строки удара.
     *
     * @param array<string, mixed> $row Строка удара.
     *
     * @return array{type: string, id: int} Пара.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function defenderOf(array $row): array
    {
        $kind = $row['defender_kind'] ?? null;
        $id = $row['defender_id'] ?? null;
        if (!is_string($kind) || !is_int($id)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        return ['type' => $kind, 'id' => $id];
    }
}
