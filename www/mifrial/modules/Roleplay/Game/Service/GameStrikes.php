<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
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

    private readonly GameStrikeClose $closeService;

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
     * @param GameCardAvailability $availability Принадлежность и требования карточки.
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
        private readonly GameCardAvailability $availability,
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
        $this->open = new GameStrikeOpen(
            $this->battles,
            $this->actions,
            $this->spend,
            $rules,
            $this->sheets,
            $this->sheetWrites,
        );
        $this->resolution = new GameStrikeResolution(
            $rules,
            $this->sheets,
            $this->npcs,
            $consequences,
            $this->amounts,
        );
        $this->closeService = new GameStrikeClose(
            $rules,
            $this->battles,
            $this->actions,
            $this->commands,
            $this->access,
            $this->sheets,
            $this->sheetWrites,
            $this->resolution,
            $this->spend,
            $this->chat,
            $consequences,
            $this->amounts,
            $this->replayTransaction,
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
     * Открывает удар из нескольких экземпляров одного оружия.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param array $choice Разобранный выбор со strikes.
     *
     * @return array<string, mixed> Итог.
     */
    public function declareMultiStrike(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $choice,
    ): array {
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

        $npcChoices = $choice['attacker']['type'] === 'npc'
            ? $this->npcChoices($game->getId(), $choice['attacker']['id'])
            : null;
        $strikes = is_array($choice['strikes'] ?? null) ? $choice['strikes'] : [$choice];
        foreach ($strikes as $strike) {
            $this->rules->assertAttack(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $choice['actionRuleCode'],
                $strike['itemInventoryId'],
                $strike['itemRuleCode'],
                $strike['profileType'],
                $strike['profileIndex'],
                $choice['attacker']['type'],
                $choice['attacker']['id'],
                $npcChoices,
                $strike['instanceIndex'] ?? 0,
            );
        }

        $this->rules->assertAttackTargets(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['actionRuleCode'],
            1,
        );
        $this->assertWeaponShape($game, $choice, $npcChoices);
        $this->availability->assertAvailable(
            $game,
            $choice['attacker']['type'],
            $choice['attacker']['id'],
            $choice['actionRuleCode'],
        );
        $this->assertModifiers($game, $choice);
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
     * @param array<string, mixed> $choice Защита.
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
        $result = $this->closeService->closeSingle(
            $game,
            $sessionId,
            $battleId,
            $idempotencyKey,
            $expectedBattleVersion,
            $expectedSheetVersion,
            $choice,
            $actorUserId,
            $editAll,
            $viewAll,
        );
        $this->announce($game->getId(), $idempotencyKey);

        return $result;
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
        return $this->open->openAction(
            $game,
            $sessionId,
            $battle,
            $expectedBattleVersion,
            $choice,
            'strike',
            '1-1',
            'Game strike',
        );
    }

    /**
     * Модификаторы действия объявлены, принадлежат атакующему и применимы к удару.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор удара.
     *
     * @return void
     *
     * @throws GameInvalidException Если код чужой, объявлен не одиночным ударом или вне области.
     */
    private function assertModifiers(GameRecord $game, array $choice): void
    {
        $codes = $choice['modifiers'] ?? [];
        if (!is_array($codes) || $codes === []) {
            return;
        }

        if (is_array($choice['strikes'] ?? null) && $choice['strikes'] !== []) {
            throw new GameInvalidException('Game action modifier is not supported for multi-strike');
        }

        $attacker = $choice['attacker'] ?? null;
        $actionRuleCode = $choice['actionRuleCode'] ?? null;
        $profileType = $choice['profileType'] ?? null;
        if (
            !is_array($attacker)
            || (($attacker['type'] ?? null) !== 'character' && ($attacker['type'] ?? null) !== 'npc')
            || !is_int($attacker['id'] ?? null)
            || !is_string($actionRuleCode)
            || !is_string($profileType)
            || !array_is_list($codes)
        ) {
            throw new GameInvalidException('Game action modifier is invalid');
        }

        $listed = [];
        foreach ($codes as $code) {
            if (!is_string($code) || $code === '') {
                throw new GameInvalidException('Game action modifier is invalid');
            }

            $listed[] = $code;
        }

        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $modifiers = new GameActionModifiers();
        $levels = $this->sheets->abilityLevels($game, $attacker['type'], $attacker['id']);
        foreach ($listed as $code) {
            $modifier = $modifiers->find($slice, $actionRuleCode, $levels, $code);
            if (!$modifier['effect']->appliesTo($profileType, 1)) {
                throw new GameInvalidException('Game action modifier scope is invalid');
            }
        }
    }

    /**
     * Форма нескольких экземпляров одного оружия.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     * @param array<string, mixed>|null $npcChoices Choices NPC.
     *
     * @return void
     *
     * @throws GameInvalidException Если форма нарушена.
     */
    private function assertWeaponShape(GameRecord $game, array $choice, ?array $npcChoices): void
    {
        $strikes = is_array($choice['strikes'] ?? null) ? $choice['strikes'] : [];
        if ($strikes === []) {
            return;
        }

        $codes = [];
        $instances = [];
        foreach ($strikes as $strike) {
            $codes[$strike['itemRuleCode']] = true;
            $instances[$strike['itemInventoryId'] . ':' . $strike['instanceIndex']] = true;
        }

        $base = $this->rules->actionBaseOf(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['actionRuleCode'],
        );
        if ($base === null) {
            throw new GameInvalidException('Game strike action is invalid');
        }

        $count = count($strikes);
        if ($base->isSameWeapon() && count($codes) !== 1) {
            throw new GameInvalidException('Game strike same weapon is invalid');
        }

        if ($base->isDistinctWeapons() && count($codes) !== $count) {
            throw new GameInvalidException('Game strike distinct weapons are invalid');
        }

        $strikeCount = $base->getStrikeCount();
        if (is_int($strikeCount) && $strikeCount > 0 && $count !== $strikeCount) {
            throw new GameInvalidException('Game strike count is invalid');
        }

        $min = $base->getMinWeapons();
        $max = $base->getMaxWeapons();
        if (is_int($min) && $count < $min) {
            throw new GameInvalidException('Game strike weapon count is invalid');
        }

        if (is_int($max) && $count > $max) {
            $lifted = $this->rules->liftsMaxWeapons(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $choice['actionRuleCode'],
                $choice['attacker']['type'],
                $choice['attacker']['id'],
                $npcChoices,
            );
            if (!$lifted) {
                throw new GameInvalidException('Game strike weapon count is invalid');
            }
        }

        if (count($instances) !== $count) {
            throw new GameInvalidException('Game strike weapons repeat');
        }
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
}
