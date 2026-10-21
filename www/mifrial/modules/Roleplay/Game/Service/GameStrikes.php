<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
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
use Mifrial\Roleplay\Game\Repository\GameStrikeRepository;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\ResourceComponent;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Атака и защита одного удара.
 */
final class GameStrikes implements IGameStrikes
{
    private readonly GameBattleRepository $battles;

    private readonly GameStrikeRepository $strikes;

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

    /**
     * Карта ожидания после списания удара.
     *
     * @var array<string, mixed>|null
     */
    private ?array $strikePending = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $debtHold = null;

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
        $this->strikes = new GameStrikeRepository($smartTableGateway);
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
        if (!$this->cardAccess->isVisible($game, $actorUserId, $viewAll)) {
            throw new GameNotFoundException('Game was not found');
        }

        $replay = $this->findReplay($gameId, $idempotencyKey, $requestBody);
        if ($replay !== null) {
            return ['game' => $game, 'sessionId' => 0, 'replay' => $replay];
        }

        if ($idempotencyKey === '' || $game->getStatus() === 'completed') {
            throw new GameInvalidException('Game strike is invalid');
        }

        if ($actingEntity !== null) {
            $this->assertStrikeActor($game, $actorUserId, $editAll, $actingEntity);
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
        $this->assertPair($battleId, $choice['attacker'], $choice['defender']);
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

        $this->assertSheet($game, $open, $expectedSheetVersion, $actorUserId, $viewAll);
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
        $this->debtHold = null;
        $this->spendAttacker($game, $choice, $battle);
        $result = [
            'battleId' => $battle->getId(),
            'strikeId' => 0,
            'version' => $expectedBattleVersion + 1,
            'sheetVersion' => null,
        ];
        if ($this->debtHold !== null) {
            $saved = $this->battles->replaceTracked(
                $battle->getId(),
                $expectedBattleVersion,
                $this->strikePending ?? $battle->getPendingActionEffects(),
                $this->debtHold,
                null,
                null,
            );
            $result['version'] = $saved->getStateVersion();
            $result['debt'] = true;

            return $result;
        }

        $result['strikeId'] = $this->strikes->add($this->openRow($battle, $sessionId, $choice));
        $this->battles->replacePending(
            $battle->getId(),
            $expectedBattleVersion,
            $this->strikePending ?? $battle->getPendingActionEffects(),
        );

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
            ? $this->characterSheetSnapshot($id)
            : $this->npcSheetSnapshot($game->getId(), $id);
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

        $debt = $battle->getActionDebt();
        unset($debt[(new GameActionDebt())->key($kind, $id)]);
        $saved = $this->battles->replaceTracked(
            $battle->getId(),
            $expectedBattleVersion,
            $battle->getPendingActionEffects(),
            $debt,
            null,
            null,
        );

        return [
            'battleId' => $battle->getId(),
            'strikeId' => $this->strikes->add($this->openRow($battle, $sessionId, $choice)),
            'version' => $saved->getStateVersion(),
        ];
    }

    /**
     * Списывает стоимость declaration у authoritative атакующего.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор атаки.
     *
     * @return void
     *
     * @throws GameInvalidException Если ресурс или атакующий невалидны.
     * @throws GameNotFoundException Если revision или NPC отсутствует.
     * @throws GameBattleConflictException Если CAS атакующего устарел.
     */
    private function spendAttacker(GameRecord $game, array $choice, GameBattleRecord $battle): void
    {
        $kind = $choice['attacker']['type'] ?? null;
        $id = $choice['attacker']['id'] ?? null;
        if (!is_string($kind) || !is_int($id)) {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        $snapshot = $kind === 'character'
            ? $this->characterSheetSnapshot($id)
            : $this->npcSheetSnapshot($game->getId(), $id);
        $spends = $this->rules->resolveResourceSpends(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['actionRuleCode'],
            $choice['itemInventoryId'],
            $choice['itemRuleCode'],
            $snapshot['sheet']['choices'],
            $snapshot['sheet']['sheet'],
            $choice['chosenAmounts'],
        );
        $adjusted = (new GamePendingActionCosts())->adjustSpends(
            $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision()),
            $snapshot['sheet']['choices'],
            $choice['actionRuleCode'],
            $kind,
            $id,
            $battle->getPendingActionEffects(),
            $spends,
        );
        $spends = $adjusted['spends'];
        $this->strikePending = $adjusted['pending'];
        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $debt = new GameActionDebt();
        $actorKey = $debt->key($kind, $id);
        $debt->assertFree($battle->getActionDebt(), $actorKey);
        $planned = $debt->plan(
            $slice,
            $snapshot['sheet']['sheet'],
            $spends,
            $debt->refillCode($slice),
            $choice,
            '1-1',
        );
        $spends = $planned['spends'];
        if (!$planned['open']) {
            $held = $battle->getActionDebt();
            $held[$actorKey] = $planned['slot'];
            $this->debtHold = $held;
        }

        $inventorySpend = $planned['open'] ? $this->rules->resolveInventorySpend(
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
        ) : null;
        if ($spends === [] && $inventorySpend === null) {
            return;
        }

        if (
            $spends !== [] && !$this->rules->hasSufficientResources(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $snapshot['sheet']['sheet'],
                $spends,
            )
        ) {
            throw new GameInvalidException('Game strike attacker resource is insufficient');
        }

        $this->sheetWrites->write(
            $game,
            $kind,
            $id,
            $snapshot['actualVersion'],
            [],
            null,
            null,
            $spends,
            $inventorySpend,
        );
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
        $attackerRoll = $this->rollOf($game, $open, $hitCode);
        $defenderRoll = null;
        if ($choice['reaction'] !== 'ignore' && $this->rules->isUnconscious($game->getSpaceId(), $game->getRulesRevision(), $this->defenderSheet($game, $open))) {
            throw new GameInvalidException('Game strike defender is unconscious');
        }
        if ($choice['reaction'] === 'dodge') {
            $defenderRoll = $this->dodgeRollOf($game, $open, $hitCode, $attackerRoll);
            $attackerRoll = $this->rules->rerateHit($attackerRoll, $defenderRoll);
        }
        if ($choice['reaction'] === 'block') {
            $defenderRoll = $this->blockRollOf($game, $open, $choice, $hitCode, $attackerRoll);
        }

        $spends = $choice['reaction'] === 'ignore'
            ? []
            : $this->defenderResourceSpends($game, $open, $choice);
        if (
            $choice['reaction'] !== 'ignore'
            && $spends !== [] && !$this->rules->hasSufficientResources(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $this->defenderSheet($game, $open),
                $spends,
            )
        ) {
            return $this->resourceIgnoredResult($battle, $strikeId, $expectedBattleVersion, $attackerRoll, $defenderRoll);
        }

        $autoFail = $this->rules->isHitAutoFail($attackerRoll['roll']);
        $damage = $autoFail ? $this->amounts->zero() : $this->damageOf($game, $open);
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
            : $this->resistanceOf(
                $game,
                $open,
                $this->resistanceChoice($choice, $result['defenderRoll'] ?? null),
                $result['success'],
                $this->penetrationOf($game, $open),
            );
        $soak = $choice['reaction'] === 'dodge' ? $this->soakOf($game, $open) : null;
        $result['resistance'] = $this->amounts->view($resistance);
        if ($soak !== null) {
            $result['S'] = $this->amounts->view($soak);
        }

        $effectiveDamage = $result['success'] === 0 ? $this->amounts->zero() : $damage;
        $injury = $this->amounts->injury($effectiveDamage, $resistance, $result['success'], $soak);
        $result['injury'] = $this->amounts->view($injury);
        $prepared = $autoFail ? null : $this->prepareSheet(
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
        $this->strikes->close(
            $strikeId,
            $choice['reaction'],
            $choice['blockItemInventoryId'],
            $choice['blockItemProfileIndex'],
            $choice['blockItemRuleCode'],
        );
        $this->battles->advanceVersion($battle->getId(), $expectedBattleVersion);

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
            fn (CharacterConflictException|GameEconomyConflictException $exception): array => $this->projectWriteConflict(
                $game,
                $kind,
                $defenderId,
                $exception,
                $actorUserId,
                $viewAll,
            ),
        );
    }

    /**
     * Разрешает spends accepted defender reaction.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Open strike.
     * @param array{reaction: string, blockItemRuleCode: string|null} $choice Reaction.
     *
     * @return array<int, ResourceSpend> Native spends.
     *
     * @throws GameInvalidException If target document is invalid.
     * @throws GameNotFoundException If target is absent.
     */
    private function defenderResourceSpends(GameRecord $game, array $open, array $choice): array
    {
        if ($choice['reaction'] === 'ignore') {
            return [];
        }

        $kind = $open['defender_kind'] ?? null;
        $id = $open['defender_id'] ?? null;
        if (!is_string($kind) || !is_int($id)) {
            throw new GameInvalidException('Game strike defender is invalid');
        }

        $blockRuleCode = $choice['reaction'] === 'block'
            ? $this->rules->getBlockItemRuleCode(
                $this->defenderChoices($game, $kind, $id),
                $choice['blockItemInventoryId'],
            )
            : ($choice['blockItemRuleCode'] ?? '');

        return $this->rules->resolveReactionResourceSpends(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['reaction'],
            $choice['reaction'] === 'block' ? $choice['blockItemInventoryId'] : null,
            $blockRuleCode,
            $this->defenderChoices($game, $kind, $id),
            $this->defenderSheet($game, $open),
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
     * Возвращает authoritative sheet защитника.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Open strike.
     *
     * @return array<string, mixed> Sheet.
     *
     * @throws GameInvalidException If row or NPC is invalid.
     * @throws GameNotFoundException If target is absent.
     */
    private function defenderSheet(GameRecord $game, array $open): array
    {
        $kind = $open['defender_kind'] ?? null;
        $id = $open['defender_id'] ?? null;
        if (!is_string($kind) || !is_int($id)) {
            throw new GameInvalidException('Game strike defender is invalid');
        }

        if ($kind === 'character') {
            return $this->rules->characterSnapshot($id)['sheet'];
        }

        $wrapped = $this->npcSheetSnapshot($game->getId(), $id)['sheet'];
        $inner = is_array($wrapped) ? ($wrapped['sheet'] ?? null) : null;

        return is_array($inner) ? $inner : $wrapped;
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
        $rating = $attackerRoll['rating'] ?? null;
        if (!is_int($rating)) {
            throw new GameInvalidException('Game strike attacker roll is invalid');
        }

        $autoFail = $this->rules->isHitAutoFail($attackerRoll['roll']);
        $defenderWon = $defenderRoll !== null && ($defenderRoll['success'] ?? false) === true;
        $success = $reaction === 'dodge'
            ? (($autoFail || $defenderWon) ? 0 : max(0, $rating))
            : ($autoFail || $rating <= 0 ? 0 : ($defenderWon ? 1 : $rating));
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
     * Пишет деление в лист защитника.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Строка удара.
     * @param int $expectedSheetVersion Версия листа.
     * @param DimensionalNumber $injury Повреждение.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return int Версия после записи.
     *
     * @throws GameInvalidException Если строка, стойкость или лист битые.
     * @throws GameNotFoundException Если листа нет.
     * @throws GameBattleConflictException Если версия листа другая.
     */

    /**
     * @return array{operations: list<array<string, mixed>>, consequence: array<string, mixed>}
     */
    private function prepareSheet(GameRecord $game, array $open, DimensionalNumber $injury, int $success): array
    {
        $kind = $open['defender_kind'] ?? null;
        $defenderId = $open['defender_id'] ?? null;
        $itemRuleCode = $open['item_rule_code'] ?? null;
        $profileType = $open['profile_type'] ?? null;
        $profileIndex = $open['profile_index'] ?? null;
        if (($kind !== 'character' && $kind !== 'npc') || !is_int($defenderId) || !is_string($itemRuleCode) || !is_string($profileType) || !is_int($profileIndex)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        $npcSnapshot = $kind === 'npc' ? $this->npcSheetSnapshot($game->getId(), $defenderId) : null;
        $sheet = $npcSnapshot !== null
            ? $npcSnapshot['sheet']['sheet']
            : $this->rules->characterSnapshot($defenderId)['sheet'];
        $npcChoices = $npcSnapshot !== null ? $npcSnapshot['sheet']['choices'] : null;
        $sheetVersion = $npcSnapshot['actualVersion'] ?? null;
        $split = $this->rules->splitInjury(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $kind,
            $defenderId,
            $kind === 'npc' ? $sheet : null,
            $injury,
            is_array($npcChoices) ? $npcChoices : null,
            is_int($sheetVersion) ? $sheetVersion : null,
        );

        return $this->consequences->effects(
            $game,
            $sheet,
            $injury,
            $success,
            $split,
            $this->rules->damageTypeCode($game->getSpaceId(), $game->getRulesRevision(), $itemRuleCode, $profileType, $profileIndex),
            true,
            $this->rules->enduranceOf(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $kind,
                $defenderId,
                $kind === 'npc' ? $sheet : null,
                is_array($npcChoices) ? $npcChoices : null,
                is_int($sheetVersion) ? $sheetVersion : null,
            ),
        );
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
            fn (CharacterConflictException|GameEconomyConflictException $exception): array => $this->projectWriteConflict(
                $game,
                $kind,
                $defenderId,
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
        $defender = $this->entityToken($open['defender_kind'] ?? 'character', $open['defender_id'] ?? 0);
        $attacker = $this->entityToken($open['attacker_kind'] ?? 'character', $open['attacker_id'] ?? 0);
        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $actionCode = is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '';
        $action = $actionCode !== '' ? '[[rule:' . $actionCode . ']]' : 'действие';
        $actionAp = $this->actionAp($slice, $actionCode);
        $weapon = is_string($open['item_rule_code'] ?? null) ? '[[rule:' . $open['item_rule_code'] . ']]' : 'оружием';
        $kind = match ($open['profile_type'] ?? 'strike') {
            'throw' => 'бросок',
            'shoot' => 'выстрел',
            default => 'удар',
        };
        $typeBit = $this->damageTypeBit($game, $open, $slice);
        $narrative = $attacker . ' наносит ' . $typeBit . ' ' . $kind . ' оружием ' . $weapon . ' по ' . $defender . '.';
        if ($reaction === 'ignore') {
            $narrative .= ' Тот не реагирует.';
        } else {
            $reactionCode = $this->reactionRuleCode($slice, $reaction);
            $reactionToken = $reactionCode !== null ? '[[rule:' . $reactionCode . ']]' : $reaction;
            $narrative .= ' Тот пытается совершить ' . $reactionToken . ' за ' . $this->actionAp($slice, $reactionCode ?? '') . 'ОД!';
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
            'text' => $this->exhaustionText($defender, $result['will']['rating'] ?? 0, $result['willOutcome'] ?? 'clear'),
        ] : null;
        $maimRating = is_int($result['maim']['rating'] ?? null) ? $result['maim']['rating'] : 0;
        $maim = isset($result['maim']) ? [
            'roll' => $result['maim'],
            'text' => $this->maimText($defender, $maimRating),
            'details' => [
                'injury' => [
                    'strength' => $maimRating < 0 ? -$maimRating : 0,
                    'permanent' => false,
                    'temporary' => true,
                    'lethal' => false,
                    'disfiguring' => false,
                    'rating' => $maimRating,
                    'difficulty' => is_array($result['maim']['difficulty'] ?? null)
                        ? ($result['maim']['difficulty']['base'] ?? null)
                        : null,
                ],
                'damageTypeName' => $typeBit,
            ],
        ] : null;
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
            $this->speakerOf($open['attacker_kind'] ?? 'character', $open['attacker_id'] ?? 0),
            is_array($result['defenderRoll'] ?? null) ? $result['defenderRoll'] : null,
            $parentId,
        );
    }

    private function entityToken(mixed $kind, mixed $id): string
    {
        $prefix = $kind === 'npc' ? 'npc' : 'character';
        $numericId = is_int($id) ? $id : (is_string($id) && ctype_digit($id) ? (int) $id : 0);
        $name = $this->entityName(is_string($kind) ? $kind : 'character', $numericId);

        return '[[' . $prefix . ':' . $numericId . ',' . $name . ']]';
    }

    /**
     * @return array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string}
     */
    private function speakerOf(mixed $kind, mixed $id): array
    {
        $numericId = is_int($id) ? $id : 0;
        $name = $this->entityName(is_string($kind) ? $kind : 'character', $numericId);
        if ($kind === 'npc') {
            return ['kind' => 'npc', 'npcId' => $numericId, 'npcName' => $name];
        }

        return ['kind' => 'character', 'characterId' => $numericId, 'characterName' => $name];
    }

    private function entityName(string $kind, int $id): string
    {
        if ($id <= 0) {
            return '';
        }

        try {
            if ($kind === 'npc') {
                return $this->npcs->getById($id)->getName();
            }

            return $this->rules->characterName($id);
        } catch (GameNotFoundException | CharacterNotFoundException) {
            return (string) $id;
        }
    }

    private function actionAp(CharacterRuleSlice $slice, string $ruleCode): int
    {
        if ($ruleCode === '') {
            return 0;
        }

        $spec = $slice->findLive($ruleCode)?->getSpec();
        if (!$spec instanceof AbilityActionSpec) {
            return 0;
        }

        $turnCode = $this->turnResourceCode($slice);
        $sum = 0;
        foreach ($spec->getComponents() as $component) {
            if (!$component instanceof ResourceComponent) {
                continue;
            }

            if ($turnCode !== null && $component->getResourceCode() !== $turnCode) {
                continue;
            }

            $amount = $component->getAmount();
            if (is_int($amount)) {
                $sum += $amount;
            } elseif ($amount instanceof DimensionalNumber) {
                $sum += $amount->getBase();
            }
        }

        return $sum;
    }

    private function turnResourceCode(CharacterRuleSlice $slice): ?string
    {
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->getSpec();
            if ($spec instanceof ResourceSpec && $spec->isTurnRefill()) {
                return $rule->getCode();
            }
        }

        return null;
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
     * @param array<string, mixed> $open Открытый удар.
     */
    private function damageTypeBit(GameRecord $game, array $open, CharacterRuleSlice $slice): string
    {
        $item = $open['item_rule_code'] ?? null;
        $profile = $open['profile_type'] ?? null;
        $index = $open['profile_index'] ?? null;
        if (!is_string($item) || !is_string($profile) || !is_int($index)) {
            return 'безымянный';
        }

        $code = $this->rules->damageTypeCode($game->getSpaceId(), $game->getRulesRevision(), $item, $profile, $index);
        if ($code === null) {
            return 'безымянный';
        }

        $name = $slice->findLive($code)?->getName();

        return is_string($name) && $name !== '' ? mb_strtolower($name) : 'безымянный';
    }

    private function exhaustionText(string $target, int $rating, string $outcome): string
    {
        $label = match ($outcome) {
            'weakness' => 'Слабость',
            'disabled' => 'Обессилен',
            'unconscious' => 'Потеря сознания',
            default => 'без Упадка сил',
        };
        if ($outcome === 'clear') {
            $shown = ($rating >= 0 ? '+' : '') . $rating;

            return $target . ' выдерживает истощение (РУ ' . $shown . ') — ' . $label . '.';
        }

        return $target . ' не выдерживает истощение (РУ ' . $rating . ') — ' . $label . '.';
    }

    private function maimText(string $target, int $strength): string
    {
        if ($strength <= 0) {
            return $target . ' не получает увечье.';
        }

        return $target . ' получает временное увечье с силой ' . $strength . '.';
    }

    /**
     * Проецирует race-конфликт записи листа.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид цели.
     * @param int $id Идентификатор цели.
     * @param CharacterConflictException|GameEconomyConflictException $exception Исходный конфликт.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array{choices: array<string, mixed>, sheet: array<string, mixed>} Проекция.
     *
     * @throws GameInvalidException Если снимок листа битый.
     * @throws GameNotFoundException Если цель или membership отсутствуют.
     */
    private function projectWriteConflict(
        GameRecord $game,
        string $kind,
        int $id,
        CharacterConflictException|GameEconomyConflictException $exception,
        int $actorUserId,
        bool $viewAll,
    ): array {
        $current = $exception->getCurrentSheet();
        if ($current === null) {
            throw new GameInvalidException('Game strike conflict sheet is missing');
        }

        return $kind === 'character'
            ? $this->projectCharacterConflict($game, $id, $current, $actorUserId, $viewAll)
            : $this->projectNpcConflict($game, $id, $actorUserId, $viewAll, $current);
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
     * Версия листа защитника.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param int $expectedSheetVersion Ожидание.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return void
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameInvalidException Если вид чужой.
     * @throws GameNotFoundException Если листа нет.
     */
    private function assertSheet(
        GameRecord $game,
        array $open,
        int $expectedSheetVersion,
        int $actorUserId,
        bool $viewAll,
    ): void {
        $kind = $open['defender_kind'] ?? null;
        $defenderId = $open['defender_id'] ?? null;
        if (($kind !== 'character' && $kind !== 'npc') || !is_int($defenderId)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        $snapshot = $kind === 'character'
            ? $this->characterSheetSnapshot($defenderId)
            : $this->npcSheetSnapshot($game->getId(), $defenderId);

        if ($snapshot['actualVersion'] !== $expectedSheetVersion) {
            $sheet = $kind === 'character'
                ? $this->projectCharacterConflict($game, $defenderId, $snapshot['sheet'], $actorUserId, $viewAll)
                : $this->projectNpcConflict($game, $defenderId, $actorUserId, $viewAll);
            throw new GameBattleConflictException(
                $snapshot['actualVersion'],
                currentSheet: $sheet,
            );
        }
    }

    /**
     * Маскирует конфликтный лист Character.
     *
     * @param GameRecord $game Игра.
     * @param int $characterId Персонаж.
     * @param array<string, mixed> $sheet Снимок.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array{choices: array<string, mixed>, sheet: array<string, mixed>} Маска.
     *
     * @throws GameNotFoundException Если membership отсутствует.
     */
    private function projectCharacterConflict(
        GameRecord $game,
        int $characterId,
        array $sheet,
        int $actorUserId,
        bool $viewAll,
    ): array {
        $membership = $this->characters->getByPair($game->getId(), $characterId);
        $full = $viewAll
            || $game->getOwnerId() === $actorUserId
            || $membership->getCharacterOwnerId() === $actorUserId
            || $this->isGm($game->getId(), $actorUserId);
        $choices = $sheet['choices'] ?? [];
        $sheetBody = $sheet['sheet'] ?? [];
        if (!is_array($choices) || !is_array($sheetBody)) {
            throw new GameInvalidException('Game character sheet is invalid');
        }

        return $this->conflictProjection->projectGameCharacter(
            $choices,
            $sheetBody,
            $membership->getSectionVisibility(),
            $full,
        );
    }

    /**
     * Маскирует конфликтный лист NPC.
     *
     * @param GameRecord $game Игра.
     * @param int $npcId NPC.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     * @param array{choices: array<string, mixed>, sheet: array<string, mixed>}|null $currentSheet Снимок из race-конфликта.
     *
     * @return array{choices: array<string, mixed>, sheet: array<string, mixed>} Маска.
     *
     * @throws GameNotFoundException Если NPC отсутствует.
     */
    private function projectNpcConflict(
        GameRecord $game,
        int $npcId,
        int $actorUserId,
        bool $viewAll,
        ?array $currentSheet = null,
    ): array {
        $npc = $this->npcs->getById($npcId);
        if ($npc->getGameId() !== $game->getId()) {
            throw new GameNotFoundException('Game strike npc was not found');
        }

        $full = $viewAll
            || $game->getOwnerId() === $actorUserId
            || $this->isGm($game->getId(), $actorUserId);
        $version = $this->conflictProjection->projectNpc(
            $currentSheet ?? $npc->getVersion(),
            $npc->getVisibility(),
            $full,
        );
        $choices = $version['choices'] ?? [];
        $sheet = $version['sheet'] ?? [];
        if (!is_array($choices) || !is_array($sheet)) {
            throw new GameInvalidException('Game NPC sheet is invalid');
        }

        return ['choices' => $choices, 'sheet' => $sheet];
    }

    /**
     * Читает свежий snapshot листа персонажа.
     *
     * @param int $characterId Персонаж.
     *
     * @return array<string, mixed> Snapshot.
     *
     * @throws GameNotFoundException Если персонажа нет.
     */
    private function characterSheetSnapshot(int $characterId): array
    {
        $snapshot = $this->rules->characterSnapshot($characterId);

        return [
            'actualVersion' => $snapshot['actualVersion'],
            'sheet' => [
                'choices' => $snapshot['choices'],
                'sheet' => $snapshot['sheet'],
            ],
        ];
    }

    /**
     * Читает свежий snapshot листа NPC этой игры.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return array<string, mixed> Snapshot.
     *
     * @throws GameNotFoundException Если NPC чужой или отсутствует.
     */
    private function npcSheetSnapshot(int $gameId, int $npcId): array
    {
        $npc = $this->npcs->getById($npcId);
        if ($npc->getGameId() !== $gameId) {
            throw new GameNotFoundException('Game strike npc was not found');
        }

        $actualVersion = $npc->getActualVersion();
        $document = $npc->getVersion();
        $choices = $document['choices'] ?? [];
        $sheet = $document['sheet'] ?? [];

        return [
            'actualVersion' => $actualVersion,
            'sheet' => [
                'choices' => is_array($choices) ? $choices : [],
                'sheet' => is_array($sheet) ? $sheet : [],
            ],
        ];
    }

    /**
     * Рейтинг попадания атакующего со строки удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Строка удара.
     * @param string $hitCode Код карточки.
     *
     * @return int Рейтинг.
     *
     * @throws GameInvalidException Если строка, карточка или пул битые.
     * @throws GameNotFoundException Если ревизии или персонажа нет.
     */
    private function rateOf(GameRecord $game, array $open, string $hitCode): int
    {
        $kind = $open['attacker_kind'] ?? null;
        $attackerId = $open['attacker_id'] ?? null;
        if (!is_string($kind) || !is_int($attackerId)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        return $this->rules->rateHit(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $hitCode,
            $kind,
            $attackerId,
            $kind === 'npc' ? $this->attackerSheet($game->getId(), $attackerId) : null,
        );
    }

    /**
     * Выполняет и возвращает authoritative roll атакующего.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param string $hitCode Код hit-check.
     *
     * @return array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int} Roll.
     *
     * @throws GameInvalidException Если строка или документ битые.
     * @throws GameNotFoundException Если лист отсутствует.
     */
    private function rollOf(GameRecord $game, array $open, string $hitCode): array
    {
        $kind = $open['attacker_kind'] ?? null;
        $attackerId = $open['attacker_id'] ?? null;
        $itemRuleCode = $open['item_rule_code'] ?? null;
        $profileType = $open['profile_type'] ?? null;
        $profileIndex = $open['profile_index'] ?? null;
        $itemInventoryId = $open['item_inventory_id'] ?? null;
        if (
            !is_string($kind)
            || !is_int($attackerId)
            || !is_string($itemRuleCode)
            || !is_string($profileType)
            || !is_int($profileIndex)
            || !is_int($itemInventoryId)
        ) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        return $this->rules->rollHit(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $hitCode,
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $itemInventoryId,
            $kind,
            $attackerId,
            $kind === 'npc' ? $this->attackerSheet($game->getId(), $attackerId) : null,
            $kind === 'npc' ? $this->npcChoices($game->getId(), $attackerId) : null,
            is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '',
            $kind === 'npc' ? $this->npcSheetVersion($game->getId(), $attackerId) : null,
        );
    }

    /**
     * Выполняет roll защитника для выбранного block instance.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param string $hitCode Код hit-check.
     * @param array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int} $attackerRoll Roll атаки.
     *
     * @return array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int} Roll.
     *
     * @throws GameInvalidException Если строка или выбор битые.
     * @throws GameNotFoundException Если лист отсутствует.
     */
    private function dodgeRollOf(GameRecord $game, array $open, string $hitCode, array $attackerRoll): array
    {
        $kind = $open['defender_kind'] ?? null;
        $id = $open['defender_id'] ?? null;
        $profileType = $open['profile_type'] ?? null;
        if (!is_string($kind) || !is_int($id) || !is_string($profileType)) {
            throw new GameInvalidException('Game strike defender is invalid');
        }

        return $this->rules->rollDodge(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $hitCode,
            $this->defenderSheet($game, $open),
            $this->defenderChoices($game, $kind, $id),
            $profileType,
            $attackerRoll,
            $this->participantKey($game, $kind, $id),
        );
    }

    /**
     * @param GameRecord $game
     * @param array<string, mixed> $open
     * @param array<string, mixed> $choice
     * @param array<string, mixed> $attackerRoll
     *
     * @return array<string, mixed>
     */
    private function blockRollOf(
        GameRecord $game,
        array $open,
        array $choice,
        string $hitCode,
        array $attackerRoll,
    ): array {
        $kind = $open['defender_kind'] ?? null;
        $id = $open['defender_id'] ?? null;
        $inventoryId = $choice['blockItemInventoryId'] ?? null;
        $profileIndex = $choice['blockItemProfileIndex'] ?? null;
        if (!is_string($kind) || !is_int($id) || !is_int($inventoryId) || !is_int($profileIndex)) {
            throw new GameInvalidException('Game strike block item is invalid');
        }

        return $this->rules->rollBlock(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $hitCode,
            $this->defenderSheet($game, $open),
            $this->defenderChoices($game, $kind, $id),
            $inventoryId,
            $profileIndex,
            $attackerRoll,
            $this->participantKey($game, $kind, $id),
        );
    }

    /**
     * Убирает block layers после проигранного defender roll.
     *
     * @param array<string, mixed> $choice Реакция.
     * @param array<string, mixed>|null $defenderRoll Roll защитника.
     *
     * @return array<string, mixed> Выбор для projection.
     */
    private function resistanceChoice(array $choice, ?array $defenderRoll): array
    {
        if (
            $choice['reaction'] === 'block'
            && ($defenderRoll === null || ($defenderRoll['success'] ?? false) !== true)
        ) {
            $choice['blockItemInventoryId'] = null;
        }

        return $choice;
    }

    /**
     * Вычисляет penetration принятого удара по документам атакующего.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     *
     * @return DimensionalNumber Проникновение.
     *
     * @throws GameInvalidException Если строка или формула битые.
     * @throws GameNotFoundException Если атакующий отсутствует.
     */
    private function penetrationOf(GameRecord $game, array $open): DimensionalNumber
    {
        $kind = $open['attacker_kind'] ?? null;
        $attackerId = $open['attacker_id'] ?? null;
        $itemRuleCode = $open['item_rule_code'] ?? null;
        $profileType = $open['profile_type'] ?? null;
        $profileIndex = $open['profile_index'] ?? null;
        $inventoryId = $open['item_inventory_id'] ?? null;
        if (
            !is_string($kind)
            || !is_int($attackerId)
            || !is_string($itemRuleCode)
            || !is_string($profileType)
            || !is_int($profileIndex)
            || !is_int($inventoryId)
        ) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        return $this->rules->evaluatePenetration(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $inventoryId,
            $kind,
            $attackerId,
            $kind === 'npc' ? $this->attackerSheet($game->getId(), $attackerId) : null,
            $kind === 'npc' ? $this->npcChoices($game->getId(), $attackerId) : null,
            $kind === 'npc' ? $this->npcSheetVersion($game->getId(), $attackerId) : null,
        );
    }

    /**
     * Сопротивление брони защитника по профилю удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Строка удара.
     * @param array{reaction: string, blockItemRuleCode: string|null} $choice Защита.
     * @param int $rating Рейтинг.
     * @param DimensionalNumber $penetration Проникновение.
     *
     * @return DimensionalNumber Сумма слоёв или ноль.
     *
     * @throws GameInvalidException Если строка, профиль или лист битые.
     * @throws GameNotFoundException Если ревизии или листа нет.
     */
    private function resistanceOf(
        GameRecord $game,
        array $open,
        array $choice,
        int $rating,
        DimensionalNumber $penetration,
    ): DimensionalNumber {
        $kind = $open['defender_kind'] ?? null;
        $defenderId = $open['defender_id'] ?? null;
        $itemRuleCode = $open['item_rule_code'] ?? null;
        $profileType = $open['profile_type'] ?? null;
        $profileIndex = $open['profile_index'] ?? null;
        if (!$this->isResistanceRow($kind, $defenderId, $itemRuleCode, $profileType, $profileIndex)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        return $this->rules->evaluateResistance(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $kind,
            $defenderId,
            $kind === 'npc' ? $this->attackerSheet($game->getId(), $defenderId) : null,
            $kind === 'npc' ? $this->npcChoices($game->getId(), $defenderId) : null,
            $choice['reaction'],
            $choice['blockItemInventoryId'],
            $rating,
            $penetration,
        );
    }

    /**
     * Проверяет поля строки сопротивления.
     *
     * @param mixed $kind Вид защитника.
     * @param mixed $defenderId Идентификатор защитника.
     * @param mixed $itemRuleCode Код предмета.
     * @param mixed $profileType Вид профиля.
     * @param mixed $profileIndex Индекс профиля.
     *
     * @return bool true, если строка корректна.
     */
    private function isResistanceRow(
        mixed $kind,
        mixed $defenderId,
        mixed $itemRuleCode,
        mixed $profileType,
        mixed $profileIndex,
    ): bool {
        return is_string($kind)
            && is_int($defenderId)
            && is_string($itemRuleCode)
            && is_string($profileType)
            && is_int($profileIndex);
    }

    /**
     * Смягчение уклонения защитника по профилю удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Строка удара.
     *
     * @return DimensionalNumber Пара.
     *
     * @throws GameInvalidException Если строка, карточка или закупка битые.
     * @throws GameNotFoundException Если ревизии или листа нет.
     */
    private function soakOf(GameRecord $game, array $open): DimensionalNumber
    {
        $kind = $open['defender_kind'] ?? null;
        $defenderId = $open['defender_id'] ?? null;
        $itemRuleCode = $open['item_rule_code'] ?? null;
        $profileType = $open['profile_type'] ?? null;
        $profileIndex = $open['profile_index'] ?? null;
        if (!is_string($kind) || !is_int($defenderId) || !is_string($itemRuleCode)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        if (!is_string($profileType) || !is_int($profileIndex)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        return $this->rules->evaluateSoak(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $kind,
            $defenderId,
            $kind === 'npc' ? $this->attackerSheet($game->getId(), $defenderId) : null,
            $kind === 'npc' ? $this->npcChoices($game->getId(), $defenderId) : null,
            $kind === 'npc' ? $this->npcSheetVersion($game->getId(), $defenderId) : null,
        );
    }

    /**
     * Число формулы урона по строке открытого удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Строка удара.
     *
     * @return DimensionalNumber Пара.
     *
     * @throws GameInvalidException Если строка или формула битые.
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function damageOf(GameRecord $game, array $open): DimensionalNumber
    {
        $kind = $open['attacker_kind'] ?? null;
        $attackerId = $open['attacker_id'] ?? null;
        $itemRuleCode = $open['item_rule_code'] ?? null;
        $profileType = $open['profile_type'] ?? null;
        $profileIndex = $open['profile_index'] ?? null;
        $itemInventoryId = $open['item_inventory_id'] ?? null;
        if (
            !is_string($kind)
            || !is_int($attackerId)
            || !is_string($itemRuleCode)
            || !is_string($profileType)
            || !is_int($profileIndex)
            || !is_int($itemInventoryId)
        ) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        return $this->rules->evaluateDamage(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $itemInventoryId,
            $kind,
            $attackerId,
            $kind === 'npc' ? $this->attackerSheet($game->getId(), $attackerId) : null,
            $kind === 'npc' ? $this->npcChoices($game->getId(), $attackerId) : null,
            $kind === 'npc' ? $this->npcSheetVersion($game->getId(), $attackerId) : null,
        );
    }

    /**
     * Снимок sheet атакующего NPC.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return array<string, mixed> Документ sheet.
     *
     * @throws GameInvalidException Если строка чужая или снимка нет.
     * @throws GameNotFoundException Если строки нет.
     */
    private function attackerSheet(int $gameId, int $npcId): array
    {
        $npc = $this->npcs->getById($npcId);
        $sheet = $npc->getGameId() === $gameId ? ($npc->getVersion()['sheet'] ?? null) : null;
        if (!is_array($sheet)) {
            throw new GameInvalidException('Game strike npc sheet is missing');
        }

        return $sheet;
    }

    /**
     * Версия листа NPC этой игры.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return int actualVersion.
     *
     * @throws GameNotFoundException Если строки нет или она чужая.
     */
    private function npcSheetVersion(int $gameId, int $npcId): int
    {
        $npc = $this->npcs->getById($npcId);
        if ($npc->getGameId() !== $gameId) {
            throw new GameNotFoundException('Game strike npc was not found');
        }

        return $npc->getActualVersion();
    }

    /**
     * Ключ кэша листа участника удара.
     *
     * @param GameRecord $game Игра.
     * @param string $kind character или npc.
     * @param int $id Идентификатор.
     *
     * @return string Ключ.
     *
     * @throws GameNotFoundException Если NPC чужой.
     */
    private function participantKey(GameRecord $game, string $kind, int $id): string
    {
        if ($kind === 'character') {
            return $this->rules->makeParticipantKey(
                'character',
                $id,
                $this->rules->characterSnapshot($id)['actualVersion'],
            );
        }

        return $this->rules->makeParticipantKey('npc', $id, $this->npcSheetVersion($game->getId(), $id));
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
     * Две разные пары состава.
     *
     * @param int $battleId Бой.
     * @param array{type: string, id: int} $attacker Атакующий.
     * @param array{type: string, id: int} $defender Защитник.
     *
     * @return void
     *
     * @throws GameInvalidException Если пара совпала или вид чужой.
     * @throws GameNotFoundException Если пары нет в составе.
     */
    private function assertPair(int $battleId, array $attacker, array $defender): void
    {
        if ($attacker === $defender) {
            throw new GameInvalidException('Game strike target is invalid');
        }

        $roster = $this->battles->findParticipants($battleId);
        if (!in_array($attacker, $roster, true) || !in_array($defender, $roster, true)) {
            throw new GameNotFoundException('Game battle participant was not found');
        }

        $kinds = [$attacker['type'], $defender['type']];
        if (!in_array($kinds[0], ['character', 'npc'], true) || !in_array($kinds[1], ['character', 'npc'], true)) {
            throw new GameInvalidException('Game strike participant is invalid');
        }
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
            'battle_id' => $battle->getId(),
            'session_id' => $sessionId,
            'attacker_kind' => $choice['attacker']['type'],
            'attacker_id' => $choice['attacker']['id'],
            'defender_kind' => $choice['defender']['type'],
            'defender_id' => $choice['defender']['id'],
            'action_rule_code' => $choice['actionRuleCode'],
            'item_inventory_id' => $choice['itemInventoryId'],
            'item_rule_code' => $choice['itemRuleCode'],
            'profile_type' => $choice['profileType'],
            'profile_index' => $choice['profileIndex'],
            'reaction' => null,
            'block_item_inventory_id' => null,
            'block_item_profile_index' => null,
            'block_item_rule_code' => null,
            'open' => true,
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
        $open = $this->strikes->findOpen($battleId);
        if ($open !== null) {
            $this->assertStrikeActor($game, $actorUserId, $editAll, $this->defenderOf($open));

            return $open;
        }

        if ($this->isWriter($game, $actorUserId, $editAll)) {
            throw new GameInvalidException('Game strike is not open');
        }

        $latest = $this->strikes->findLatest($battleId);
        if ($latest === null) {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }

        $this->assertStrikeActor($game, $actorUserId, $editAll, $this->defenderOf($latest));
        throw new GameInvalidException('Game strike is not open');
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

    /**
     * Писатель игры или владелец активного персонажа-актора.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param array<string, mixed> $entity Действующая пара.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     */
    private function assertStrikeActor(GameRecord $game, int $actorUserId, bool $editAll, array $entity): void
    {
        if ($this->isWriter($game, $actorUserId, $editAll)) {
            return;
        }

        $kind = $entity['type'] ?? null;
        $id = $entity['id'] ?? null;
        if (!is_string($kind) || !is_int($id) || $kind !== 'character') {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }

        try {
            $row = $this->characters->getByPair($game->getId(), $id);
        } catch (GameNotFoundException) {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }

        if ($row->getCharacterOwnerId() !== $actorUserId || $row->getStatus() !== 'active') {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }
    }

    /**
     * Владелец игры, gm или edit_all.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     *
     * @return bool Да, если писатель.
     */
    private function isWriter(GameRecord $game, int $actorUserId, bool $editAll): bool
    {
        return $game->getOwnerId() === $actorUserId || $editAll || $this->isGm($game->getId(), $actorUserId);
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
}
