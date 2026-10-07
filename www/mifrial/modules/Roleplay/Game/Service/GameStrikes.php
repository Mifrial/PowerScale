<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use JsonException;
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
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
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

    private readonly GameStrikeBody $body;

    private readonly GameDeliverySignal $deliverySignal;

    private readonly GameStrikeAmounts $amounts;

    private readonly GameStrikeSheetWrites $sheetWrites;

    /**
     * Создаёт фасад.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     * @param IGames $games Строка игры.
     * @param GameCardAccess $cardAccess Фильтр карточки.
     * @param GameSessionRepository $sessions Сессия.
     * @param ICharacterActualMutations $mutations Порт листа.
     * @param GameStrikeRules $rules Срез и версия персонажа.
     * @param IEventManager $events Сигнал доставки.
     *
     * @return void
     */
    public function __construct(
        private readonly ISmartTableGateway $smartTableGateway,
        private readonly IGames $games,
        private readonly GameCardAccess $cardAccess,
        private readonly GameSessionRepository $sessions,
        private readonly ICharacterActualMutations $mutations,
        private readonly GameStrikeRules $rules,
        IEventManager $events,
    ) {
        $this->battles = new GameBattleRepository($smartTableGateway);
        $this->strikes = new GameStrikeRepository($smartTableGateway);
        $this->commands = new GameStrikeCommandRepository($smartTableGateway);
        $this->npcs = new GameNpcRepository($smartTableGateway);
        $this->members = new GameMemberRepository($smartTableGateway);
        $this->body = new GameStrikeBody();
        $this->deliverySignal = new GameDeliverySignal($events);
        $this->amounts = new GameStrikeAmounts();
        $this->sheetWrites = new GameStrikeSheetWrites($mutations, $this->npcs);
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

        $replay = $this->replay($gameId, $idempotencyKey, $requestBody);
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
            $choice['itemRuleCode'],
            $choice['profileType'],
            $choice['profileIndex'],
        );
        $result = [
            'battleId' => $battleId,
            'strikeId' => 0,
            'version' => $expectedBattleVersion + 1,
            'sheetVersion' => null,
        ];

        $this->smartTableGateway->transaction(function () use (
            $game,
            $sessionId,
            $battle,
            $idempotencyKey,
            $expectedBattleVersion,
            $choice,
            &$result,
        ): void {
            $result['strikeId'] = $this->strikes->add($this->openRow($battle, $sessionId, $choice));
            $this->battles->advanceVersion($battle->getId(), $expectedBattleVersion);
            $this->commands->add($game->getId(), $sessionId, $idempotencyKey, [
                'attack' => $choice,
                'battleId' => $battle->getId(),
                'expectedVersion' => $expectedBattleVersion,
            ], $result);
        });
        $this->announce($game->getId(), $idempotencyKey);

        return $result;
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
     * @param array{reaction: string, blockItemRuleCode: string|null} $choice Защита.
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
    ): array {
        $battle = $this->battleOf($sessionId, $battleId, $expectedBattleVersion);
        $open = $this->strikes->findOpen($battleId);
        if ($open === null) {
            throw new GameInvalidException('Game strike is not open');
        }

        $this->assertReaction($game, $choice);
        $strikeId = $open['id'] ?? null;
        if (!is_int($strikeId)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        $hitCode = $this->rules->findHitCode($game->getSpaceId(), $game->getRulesRevision());
        $damage = $this->damageOf($game, $open);
        $result = [
            'battleId' => $battleId,
            'strikeId' => $strikeId,
            'version' => $expectedBattleVersion + 1,
            'success' => 0,
            'damage' => $this->amounts->view($damage),
            'resistance' => $this->amounts->view($this->amounts->zero()),
            'sheetVersion' => null,
        ];
        $this->smartTableGateway->transaction(function () use (
            $game,
            $sessionId,
            $battle,
            $idempotencyKey,
            $expectedBattleVersion,
            $expectedSheetVersion,
            $choice,
            $strikeId,
            $open,
            $hitCode,
            $damage,
            &$result,
        ): void {
            $this->assertSheet($game->getId(), $open, $expectedSheetVersion);
            $result['success'] = $this->rateOf($game, $open, $hitCode);
            $resistance = $this->resistanceOf($game, $open, $choice, $result['success']);
            $soak = $choice['reaction'] === 'dodge' ? $this->soakOf($game, $open) : null;
            $result['resistance'] = $this->amounts->view($resistance);
            if ($soak !== null) {
                $result['S'] = $this->amounts->view($soak);
            }

            $injury = $this->amounts->injury(
                $damage,
                $resistance,
                $result['success'],
                $soak,
            );
            $result['injury'] = $this->amounts->view($injury);
            $result['sheetVersion'] = $this->writeSheet($game, $open, $expectedSheetVersion, $injury);
            $this->strikes->close($strikeId, $choice['reaction'], $choice['blockItemRuleCode']);
            $this->battles->advanceVersion($battle->getId(), $expectedBattleVersion);
            $this->commands->add($game->getId(), $sessionId, $idempotencyKey, [
                'battleId' => $battle->getId(),
                'defense' => $choice,
                'expectedSheetVersion' => $expectedSheetVersion,
                'expectedVersion' => $expectedBattleVersion,
            ], $result);
        });
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
        $stored = $this->commands->findByKey($gameId, $idempotencyKey);
        if ($stored === null) {
            return;
        }

        $this->deliverySignal->recorded($gameId, 'strike', $stored['id'], []);
    }

    /**
     * Пишет деление в лист защитника.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Строка удара.
     * @param int $expectedSheetVersion Версия листа.
     * @param DimensionalNumber $injury Повреждение.
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
                $kind === 'npc' ? $this->attackerSheet($game->getId(), $defenderId) : null,
                $injury,
            ),
        );
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
    private function assertReaction(GameRecord $game, array $choice): void
    {
        if (!in_array($choice['reaction'], ['ignore', 'dodge', 'block'], true)) {
            throw new GameInvalidException('Game strike reaction is invalid');
        }

        if ($choice['reaction'] === 'block' && $choice['blockItemRuleCode'] === null) {
            throw new GameInvalidException('Game strike block item is invalid');
        }

        if ($choice['blockItemRuleCode'] !== null) {
            $this->rules->assertBlock($game->getSpaceId(), $game->getRulesRevision(), $choice['blockItemRuleCode']);
        }
    }

    /**
     * Версия листа защитника.
     *
     * @param int $gameId Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param int $expectedSheetVersion Ожидание.
     *
     * @return void
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameInvalidException Если вид чужой.
     * @throws GameNotFoundException Если листа нет.
     */
    private function assertSheet(int $gameId, array $open, int $expectedSheetVersion): void
    {
        $kind = $open['defender_kind'] ?? null;
        $defenderId = $open['defender_id'] ?? null;
        if (($kind !== 'character' && $kind !== 'npc') || !is_int($defenderId)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        $current = $kind === 'character'
            ? $this->rules->characterVersion($defenderId)
            : $this->npcVersion($gameId, $defenderId);
        if ($current !== $expectedSheetVersion) {
            throw new GameBattleConflictException($current);
        }
    }

    /**
     * Версия NPC этой игры.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return int Счётчик.
     *
     * @throws GameNotFoundException Если строки нет или она чужая.
     */
    private function npcVersion(int $gameId, int $npcId): int
    {
        $npc = $this->npcs->getById($npcId);
        if ($npc->getGameId() !== $gameId) {
            throw new GameNotFoundException('Game strike npc was not found');
        }

        return $npc->getActualVersion();
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
     * Сопротивление брони защитника по профилю удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Строка удара.
     * @param array{reaction: string, blockItemRuleCode: string|null} $choice Защита.
     * @param int $rating Рейтинг.
     *
     * @return DimensionalNumber Сумма слоёв или ноль.
     *
     * @throws GameInvalidException Если строка, профиль или лист битые.
     * @throws GameNotFoundException Если ревизии или листа нет.
     */
    private function resistanceOf(GameRecord $game, array $open, array $choice, int $rating): DimensionalNumber
    {
        $kind = $open['defender_kind'] ?? null;
        $defenderId = $open['defender_id'] ?? null;
        $itemRuleCode = $open['item_rule_code'] ?? null;
        $profileType = $open['profile_type'] ?? null;
        $profileIndex = $open['profile_index'] ?? null;
        if (
            !is_string($kind)
            || !is_int($defenderId)
            || !is_string($itemRuleCode)
            || !is_string($profileType)
            || !is_int($profileIndex)
        ) {
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
            $choice['blockItemRuleCode'],
            $rating,
        );
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
            'item_rule_code' => $choice['itemRuleCode'],
            'profile_type' => $choice['profileType'],
            'profile_index' => $choice['profileIndex'],
            'reaction' => null,
            'block_item_rule_code' => null,
            'open' => true,
        ];
    }

    /**
     * Повтор ключа.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $requestBody Тело.
     *
     * @return array<string, mixed>|null Итог или null.
     *
     * @throws GameBattleConflictException Если тело другое.
     * @throws GameInvalidException Если JSON битый.
     */
    private function replay(int $gameId, string $idempotencyKey, array $requestBody): ?array
    {
        $stored = $this->commands->findByKey($gameId, $idempotencyKey);
        if ($stored === null) {
            return null;
        }

        try {
            $same = json_encode($this->sortKeys($stored['body']), JSON_THROW_ON_ERROR)
                === json_encode($this->sortKeys($requestBody), JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new GameInvalidException('Game strike body is invalid', $exception);
        }

        if (!$same) {
            throw new GameBattleConflictException(null);
        }

        return $stored['result'];
    }

    /**
     * Стабильный порядок ключей.
     *
     * @param mixed $value Документ.
     *
     * @return mixed Документ.
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
