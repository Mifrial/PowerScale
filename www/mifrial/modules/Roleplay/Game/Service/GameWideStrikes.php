<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Character\Service\Read\CharacterSectionMask;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Interface\Service\IGameWideStrikes;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;
use Mifrial\Roleplay\Game\Repository\GameWideStrikeCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameWideStrikeRepository;
use Mifrial\Roleplay\Game\Repository\GameWideStrikeTargetRepository;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Атака и защита широкого удара. Числа успеха и урона оставляет пустыми.
 */
final class GameWideStrikes implements IGameWideStrikes
{
    private readonly GameBattleRepository $battles;

    private readonly GameWideStrikeRepository $strikes;

    private readonly GameWideStrikeTargetRepository $targets;

    private readonly GameWideStrikeCommandRepository $commands;

    private readonly GameNpcRepository $npcs;

    private readonly GameCharacterRepository $characters;

    private readonly GameMemberRepository $members;

    private readonly GameWideStrikeBody $body;

    private readonly GameWideStrikeResults $results;

    private readonly GameWideStrikeCommit $commit;

    private readonly GameReplayTransaction $replayTransaction;

    private readonly GameDeliverySignal $deliverySignal;

    /**
     * Создаёт фасад.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     * @param IGames $games Строка игры.
     * @param GameCardAccess $cardAccess Фильтр карточки.
     * @param GameSessionRepository $sessions Сессия.
     * @param ICharacterActualMutations $mutations Порт листа.
     * @param GameStrikeRules $rules Срез и версия персонажа.
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
        IEventManager $events,
    ) {
        $this->battles = new GameBattleRepository($smartTableGateway);
        $this->strikes = new GameWideStrikeRepository($smartTableGateway);
        $this->targets = new GameWideStrikeTargetRepository($smartTableGateway);
        $this->commands = new GameWideStrikeCommandRepository($smartTableGateway);
        $this->npcs = new GameNpcRepository($smartTableGateway);
        $this->characters = new GameCharacterRepository($smartTableGateway);
        $this->members = new GameMemberRepository($smartTableGateway);
        $this->body = new GameWideStrikeBody();
        $this->results = $this->createResults($rules, $mutations);
        $this->replayTransaction = new GameReplayTransaction($smartTableGateway);
        $this->commit = $this->createCommit();
        $this->deliverySignal = new GameDeliverySignal($events);
    }

    /**
     * Открывает широкий удар.
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
    public function declareWideStrike(
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
     * Закрывает широкий удар.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param array $defense Выбор.
     *
     * @return array<string, mixed> Итог.
     */
    public function resolveWideStrike(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $defense,
    ): array {
        $choices = $this->body->defense($defense);
        $ready = $this->ready($gameId, $actorUserId, $editAll, $viewAll, $idempotencyKey, [
            'battleId' => $battleId,
            'defense' => $choices,
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
            $choices,
            $actorUserId,
            $viewAll,
        );
    }

    /**
     * Создаёт расчёт wide strike.
     *
     * @param GameStrikeRules $rules Правила.
     * @param ICharacterActualMutations $mutations Записи листа.
     *
     * @return GameWideStrikeResults Расчёт результатов.
     */
    private function createResults(
        GameStrikeRules $rules,
        ICharacterActualMutations $mutations,
    ): GameWideStrikeResults {
        return new GameWideStrikeResults(
            $rules,
            $this->battles,
            $this->characters,
            $this->members,
            $this->npcs,
            new GameStrikeSheetWrites($mutations, $this->npcs),
            new ConflictSheetProjection(
                new CharacterSectionMask(),
                new GameCharacterProjectionMask(),
                new GameNpcVisibility(),
            ),
        );
    }

    /**
     * Создаёт координатор записи wide strike.
     *
     * @return GameWideStrikeCommit Координатор.
     */
    private function createCommit(): GameWideStrikeCommit
    {
        return new GameWideStrikeCommit(
            $this->battles,
            $this->strikes,
            $this->targets,
            $this->commands,
            $this->results,
            $this->replayTransaction,
            $this->rules,
            $this->npcs,
            new GameStrikeSheetWrites($this->mutations, $this->npcs),
        );
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
            throw new GameInvalidException('Game wide strike is invalid');
        }

        (new GameWideStrikeWriter($this->members))->assert($game, $actorUserId, $editAll);
        $sessionId = $this->sessions->findSessionId($gameId);
        if ($sessionId === null) {
            throw new GameInvalidException('Game session is not running');
        }

        return ['game' => $game, 'sessionId' => $sessionId, 'replay' => null];
    }

    /**
     * Пишет открытый удар, цели и версию боя.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия.
     * @param array $choice Выбор.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameNotFoundException Если боя или участника нет.
     * @throws GameInvalidException Если удар уже открыт или профиль чужой.
     * @throws GameBattleConflictException Если версия боя другая.
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
        $opening = $this->openingFor($game, $battleId, $choice);
        $result = $this->commit->open(
            $game,
            $sessionId,
            $battle,
            $idempotencyKey,
            $expectedBattleVersion,
            $choice,
            $opening,
        );
        $this->announce($game->getId(), $idempotencyKey);

        return $result;
    }

    /**
     * Считает записи целей и закрывает удар.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param list<array{reaction: string, blockItemRuleCode: string|null, expectedSheetVersion: int}> $choices Защиты.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameNotFoundException Если боя нет.
     * @throws GameInvalidException Если удара нет или число защит чужое.
     * @throws GameBattleConflictException Если версия боя другая.
     */
    private function close(
        GameRecord $game,
        int $sessionId,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $choices,
        int $actorUserId,
        bool $viewAll,
    ): array {
        $battle = $this->battleOf($sessionId, $battleId, $expectedBattleVersion);
        $open = $this->requireOpen($battleId);
        $rows = $this->targets->getList($open['id']);
        if (count($rows) !== count($choices)) {
            throw new GameInvalidException('Game wide strike defense count is invalid');
        }

        $context = [
            'game' => $game,
            'sessionId' => $sessionId,
            'battleId' => $battleId,
            'battle' => $battle,
            'idempotencyKey' => $idempotencyKey,
            'expectedBattleVersion' => $expectedBattleVersion,
            'strikeId' => $open['id'],
            'choices' => $choices,
            'requestChoices' => $choices,
            'rows' => $rows,
            'open' => $open,
            'actorUserId' => $actorUserId,
            'viewAll' => $viewAll,
        ];
        $context['refusals'] = $this->results->assertSheets(
            $game,
            $battleId,
            $rows,
            $choices,
            $actorUserId,
            $viewAll,
        );
        $context['choices'] = $this->results->resolveChoices(
            $game,
            $rows,
            $choices,
            $context['refusals'],
        );
        $context['prepare'] = fn (array $prepared): array => $this->prepareCloseContext($prepared);
        $result = $this->commit->close($context);
        $this->announce($game->getId(), $idempotencyKey);

        return $result;
    }

    /**
     * Сигнализирует уже сохранённую wide-команду.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ команды.
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
     * Считает roll-данные внутри transaction/replay work.
     *
     * @param array<string, mixed> $context Контекст закрытия.
     *
     * @return array<string, mixed> Контекст с refusal, damage, rating и profile.
     *
     * @throws GameBattleConflictException Если stale-лист не совпал.
     * @throws GameInvalidException Если расчёт или строка невалидны.
     * @throws GameNotFoundException Если цель или правило не найдены.
     */
    private function prepareCloseContext(array $context): array
    {
        $context['attackerRoll'] = $this->rollOf($context['game'], $context['open']);
        $context['damage'] = $this->rules->isHitAutoFail($context['attackerRoll']['roll'])
            ? (new GameStrikeAmounts())->zero()
            : $this->damageOf($context['game'], $context['open']);
        $context['profile'] = $this->profileOf($context['open']);
        $context['penetration'] = fn (): DimensionalNumber => $this->penetrationOf(
            $context['game'],
            $context['open'],
        );

        return $context;
    }

    /**
     * Создаёт lazy authoritative penetration resolver wide strike.
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
            throw new GameInvalidException('Game wide strike row is invalid');
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
            $kind === 'npc' ? $this->attackerChoices($game->getId(), $attackerId) : null,
        );
    }

    /**
     * Проверяет и подготавливает открытие wide strike.
     *
     * @param GameRecord $game Игра.
     * @param int $battleId Бой.
     * @param array $choice Выбор.
     *
     * @return GameWideStrikeOpening Построитель строк.
     *
     * @throws GameInvalidException Если удар уже открыт или профиль чужой.
     * @throws GameNotFoundException Если участник не найден.
     */
    private function openingFor(GameRecord $game, int $battleId, array $choice): GameWideStrikeOpening
    {
        $opening = new GameWideStrikeOpening($this->battles, $this->targets);
        $opening->assertRoster($battleId, $choice);
        if ($this->strikes->findOpen($battleId) !== null) {
            throw new GameInvalidException('Game wide strike is already open');
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
                ? $this->attackerChoices($game->getId(), $choice['attacker']['id'])
                : null,
        );

        return $opening;
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
            throw new GameInvalidException('Game wide strike row is invalid');
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
     * Рейтинг попадания по карточке игры.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Строка удара.
     *
     * @return int Рейтинг.
     *
     * @throws GameInvalidException Если строка, карточка или пул битые.
     * @throws GameNotFoundException Если ревизии или персонажа нет.
     */
    private function ratingOf(GameRecord $game, array $open): int
    {
        return $this->rateOf($game, $open, $this->hitCode($game));
    }

    /**
     * Выполняет authoritative roll атакующего wide strike.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Строка удара.
     *
     * @return array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int} Roll.
     *
     * @throws GameInvalidException Если строка или документ битые.
     * @throws GameNotFoundException Если лист отсутствует.
     */
    private function rollOf(GameRecord $game, array $open): array
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
            throw new GameInvalidException('Game wide strike row is invalid');
        }

        return $this->rules->rollHit(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $this->hitCode($game),
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $itemInventoryId,
            $kind,
            $attackerId,
            $kind === 'npc' ? $this->attackerSheet($game->getId(), $attackerId) : null,
            $kind === 'npc' ? $this->attackerChoices($game->getId(), $attackerId) : null,
        );
    }

    /**
     * Возвращает единственный код проверки попадания.
     *
     * @param GameRecord $game Игра.
     *
     * @return string Код карточки.
     *
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function hitCode(GameRecord $game): string
    {
        return $this->rules->findHitCode($game->getSpaceId(), $game->getRulesRevision());
    }

    /**
     * Предмет и профиль строки удара.
     *
     * @param array<string, mixed> $open Строка удара.
     *
     * @return array{itemRuleCode: string, profileType: string, profileIndex: int} Профиль.
     *
     * @throws GameInvalidException Если колонки битые.
     */
    private function profileOf(array $open): array
    {
        $itemRuleCode = $open['item_rule_code'] ?? null;
        $profileType = $open['profile_type'] ?? null;
        $profileIndex = $open['profile_index'] ?? null;
        if (!is_string($itemRuleCode) || !is_string($profileType) || !is_int($profileIndex)) {
            throw new GameInvalidException('Game wide strike row is invalid');
        }

        return [
            'itemRuleCode' => $itemRuleCode,
            'profileType' => $profileType,
            'profileIndex' => $profileIndex,
        ];
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
        if (!$this->isDamageRow($kind, $attackerId, $itemRuleCode, $profileType, $profileIndex)) {
            throw new GameInvalidException('Game wide strike row is invalid');
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
     * Проверяет поля строки расчёта урона.
     *
     * @param mixed $kind Вид атакующего.
     * @param mixed $attackerId Атакующий.
     * @param mixed $itemRuleCode Код предмета.
     * @param mixed $profileType Вид профиля.
     * @param mixed $profileIndex Индекс профиля.
     *
     * @return bool true, если строка пригодна.
     */
    private function isDamageRow(
        mixed $kind,
        mixed $attackerId,
        mixed $itemRuleCode,
        mixed $profileType,
        mixed $profileIndex,
    ): bool {
        return is_string($kind)
            && is_int($attackerId)
            && is_string($itemRuleCode)
            && is_string($profileType)
            && is_int($profileIndex);
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
            throw new GameInvalidException('Game wide strike npc sheet is missing');
        }

        return $sheet;
    }

    /**
     * Снимок choices атакующего NPC.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return array<string, mixed> Документ choices.
     *
     * @throws GameInvalidException Если строка чужая или документ повреждён.
     * @throws GameNotFoundException Если строки нет.
     */
    private function attackerChoices(int $gameId, int $npcId): array
    {
        $npc = $this->npcs->getById($npcId);
        $choices = $npc->getGameId() === $gameId ? ($npc->getVersion()['choices'] ?? null) : null;
        if (!is_array($choices)) {
            throw new GameInvalidException('Game wide strike npc choices are missing');
        }

        return $choices;
    }

    /**
     * Открытый удар с целым id.
     *
     * @param int $battleId Бой.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameInvalidException Если удара нет или id битый.
     */
    private function requireOpen(int $battleId): array
    {
        $open = $this->strikes->findOpen($battleId);
        $strikeId = is_array($open) ? ($open['id'] ?? null) : null;
        if (!is_array($open) || !is_int($strikeId)) {
            throw new GameInvalidException('Game wide strike is not open');
        }

        return $open;
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
