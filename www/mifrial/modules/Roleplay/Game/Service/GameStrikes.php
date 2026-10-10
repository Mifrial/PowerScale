<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
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
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;
use Mifrial\Roleplay\Game\Repository\GameStrikeCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameStrikeRepository;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Атака и защита одного удара.
 */
final class GameStrikes implements IGameStrikes
{
    private readonly GameBattleRepository $battles;

    private readonly GameStrikeRepository $strikes;

    private readonly GameStrikeCommandRepository $commands;

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
    ) {
        $this->battles = new GameBattleRepository($smartTableGateway);
        $this->strikes = new GameStrikeRepository($smartTableGateway);
        $this->commands = new GameStrikeCommandRepository($smartTableGateway);
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
     * Открывает удар.
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
        ]);
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
     * Закрывает удар.
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

        $this->assertWriter($game, $actorUserId, $editAll);
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
        if ($this->strikes->findOpen($battleId) !== null) {
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
        $body = [
            'attack' => $choice,
            'battleId' => $battleId,
            'expectedVersion' => $expectedBattleVersion,
        ];

        $execution = $this->replayTransaction->execute(
            fn (): ?array => $this->commands->findByKey($game->getId(), $idempotencyKey),
            fn (): int => $this->commands->reserve(
                $game->getId(),
                $sessionId,
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
     * @param bool $viewAll Полный просмотр.
     *
     * @return array<string, mixed> Итог.
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
        bool $viewAll,
    ): array {
        $battle = $this->battleOf($sessionId, $battleId, $expectedBattleVersion);
        $open = $this->strikes->findOpen($battleId);
        if ($open === null) {
            throw new GameInvalidException('Game strike is not open');
        }

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
            fn (): ?array => $this->commands->findByKey($game->getId(), $idempotencyKey),
            fn (): int => $this->commands->reserve(
                $game->getId(),
                $sessionId,
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
        $stored = $this->commands->findByKey($gameId, $idempotencyKey);
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
            fn (): ?array => $this->commands->findByKey($gameId, $idempotencyKey),
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
        $this->spendAttacker($game, $choice);
        $result = [
            'battleId' => $battle->getId(),
            'strikeId' => 0,
            'version' => $expectedBattleVersion + 1,
            'sheetVersion' => null,
        ];
        $result['strikeId'] = $this->strikes->add($this->openRow($battle, $sessionId, $choice));
        $this->battles->advanceVersion($battle->getId(), $expectedBattleVersion);

        return $result;
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
    private function spendAttacker(GameRecord $game, array $choice): void
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
        if ($spends === []) {
            return;
        }

        if (!$this->rules->hasSufficientResources(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $snapshot['sheet']['sheet'],
            $spends,
        )) {
            throw new GameInvalidException('Game strike attacker resource is insufficient');
        }

        $this->sheetWrites->write(
            $game,
            $kind,
            $id,
            $snapshot['actualVersion'],
            ['kind' => 'spendResources'],
            null,
            null,
            $spends,
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
        $attackerRoll = $this->rollOf($game, $open, $hitCode);
        $defenderRoll = null;
        if ($choice['reaction'] === 'block') {
            $defenderRoll = $this->blockRollOf($game, $open, $choice, $hitCode, $attackerRoll);
        }
        if ($choice['reaction'] === 'ignore') {
            return $this->resourceIgnoredResult($battle, $strikeId, $expectedBattleVersion, $attackerRoll, null);
        }

        $spends = $this->defenderResourceSpends($game, $open, $choice);
        if ($spends !== [] && !$this->rules->hasSufficientResources(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $this->defenderSheet($game, $open),
            $spends,
        )) {
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
                : $this->writeSheet(
                    $game,
                    $open,
                    $expectedSheetVersion,
                    $injury,
                    $actorUserId,
                    $viewAll,
                    $spends,
                ));
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

        return $kind === 'character'
            ? $this->rules->characterSnapshot($id)['sheet']
            : $this->npcSheetSnapshot($game->getId(), $id)['sheet'];
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
    ): array {
        $rating = $attackerRoll['rating'] ?? null;
        if (!is_int($rating)) {
            throw new GameInvalidException('Game strike attacker roll is invalid');
        }
        $autoFail = $this->rules->isHitAutoFail($attackerRoll['roll']);
        $success = $autoFail || $rating <= 0
            ? 0
            : ($defenderRoll !== null && ($defenderRoll['success'] ?? false) === true ? 1 : $rating);
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
    private function writeSheet(
        GameRecord $game,
        array $open,
        int $expectedSheetVersion,
        DimensionalNumber $injury,
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
            $this->rules->splitInjury(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $kind,
                $defenderId,
                $kind === 'npc' ? $this->npcSheetSnapshot($game->getId(), $defenderId)['sheet']['sheet'] : null,
                $injury,
            ),
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

        if ($choice['reaction'] === 'block'
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
        if (!is_string($kind)
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
        if ($choice['reaction'] === 'block'
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
    ): DimensionalNumber
    {
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
        if (
            !is_string($kind)
            || !is_int($attackerId)
            || !is_string($itemRuleCode)
            || !is_string($profileType)
            || !is_int($profileIndex)
        ) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        return $this->rules->evaluateDamage(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $kind,
            $attackerId,
            $kind === 'npc' ? $this->attackerSheet($game->getId(), $attackerId) : null,
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
            return $this->members->getByPair($gameId, $actorUserId)->getRole() === 'gm';
        } catch (GameNotFoundException) {
            return false;
        }
    }
}
