<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Interface\Service\IGameWideStrikes;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
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

    private readonly GameMemberRepository $members;

    private readonly GameWideStrikeBody $body;

    private readonly GameWideStrikeResults $results;

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
        private readonly ISmartTableGateway $smartTableGateway,
        private readonly IGames $games,
        private readonly GameCardAccess $cardAccess,
        private readonly GameSessionRepository $sessions,
        private readonly ICharacterActualMutations $mutations,
        private readonly GameStrikeRules $rules,
    ) {
        $this->battles = new GameBattleRepository($smartTableGateway);
        $this->strikes = new GameWideStrikeRepository($smartTableGateway);
        $this->targets = new GameWideStrikeTargetRepository($smartTableGateway);
        $this->commands = new GameWideStrikeCommandRepository($smartTableGateway);
        $this->npcs = new GameNpcRepository($smartTableGateway);
        $this->members = new GameMemberRepository($smartTableGateway);
        $this->body = new GameWideStrikeBody();
        $this->results = new GameWideStrikeResults(
            $rules,
            $this->battles,
            $this->npcs,
            new GameStrikeSheetWrites($mutations, $this->npcs),
        );
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

        $replay = (new GameWideStrikeReplay($this->commands))->find($gameId, $idempotencyKey, $requestBody);
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
        $opening = new GameWideStrikeOpening($this->battles, $this->targets);
        $opening->assertRoster($battleId, $choice);
        if ($this->strikes->findOpen($battleId) !== null) {
            throw new GameInvalidException('Game wide strike is already open');
        }

        $this->rules->assertAttack(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['actionRuleCode'],
            $choice['itemRuleCode'],
            $choice['profileType'],
            $choice['profileIndex'],
        );
        return $this->commitOpen($game, $sessionId, $battle, $idempotencyKey, $expectedBattleVersion, $choice, $opening);
    }

    /**
     * Commit открытого удара.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия.
     * @param array $choice Выбор.
     * @param GameWideStrikeOpening $opening Строки.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если поле.
     * @throws GameBattleConflictException Если версия боя другая.
     */
    private function commitOpen(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $choice,
        GameWideStrikeOpening $opening,
    ): array {
        $result = [
            'battleId' => $battle->getId(),
            'strikeId' => 0,
            'version' => $expectedBattleVersion + 1,
            'targetResults' => [],
        ];
        $this->smartTableGateway->transaction(function () use ($game, $sessionId, $battle, $idempotencyKey, $expectedBattleVersion, $choice, $opening, &$result): void {
            $result['strikeId'] = $this->strikes->add($opening->row($battle, $sessionId, $choice));
            $opening->addTargets($result['strikeId'], $choice['targets']);
            $this->battles->advanceVersion($battle->getId(), $expectedBattleVersion);
            $this->remember($game->getId(), $sessionId, $idempotencyKey, [
                'attack' => $choice,
                'battleId' => $battle->getId(),
                'expectedVersion' => $expectedBattleVersion,
            ], $result);
        });

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
    ): array {
        $battle = $this->battleOf($sessionId, $battleId, $expectedBattleVersion);
        $open = $this->requireOpen($battleId);
        $rows = $this->targets->getList($open['id']);
        if (count($rows) !== count($choices)) {
            throw new GameInvalidException('Game wide strike defense count is invalid');
        }

        $hitCode = $this->rules->findHitCode($game->getSpaceId(), $game->getRulesRevision());

        return $this->commitClose(
            $game,
            $sessionId,
            $battle,
            $idempotencyKey,
            $expectedBattleVersion,
            $open['id'],
            $choices,
            $rows,
            $this->damageOf($game, $open),
            $this->rateOf($game, $open, $hitCode),
            $this->profileOf($open),
        );
    }

    /**
     * Commit закрытия. Версии целей читаются в той же транзакции.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия.
     * @param int $strikeId Удар.
     * @param list<array{reaction: string, blockItemRuleCode: string|null, expectedSheetVersion: int}> $choices Защиты.
     * @param list<array<string, mixed>> $rows Цели.
     * @param DimensionalNumber $damage Пара формулы атакующего.
     * @param int $rating Рейтинг попадания.
     * @param array{itemRuleCode: string, profileType: string, profileIndex: int} $profile Профиль удара.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если поле или список операций не пуст.
     * @throws GameBattleConflictException Если версия боя другая.
     */
    private function commitClose(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        string $idempotencyKey,
        int $expectedBattleVersion,
        int $strikeId,
        array $choices,
        array $rows,
        DimensionalNumber $damage,
        int $rating,
        array $profile,
    ): array {
        $result = [
            'battleId' => $battle->getId(),
            'strikeId' => $strikeId,
            'version' => $expectedBattleVersion + 1,
            'targetResults' => [],
        ];
        $this->smartTableGateway->transaction(function () use (
            $game,
            $sessionId,
            $battle,
            $idempotencyKey,
            $expectedBattleVersion,
            $choices,
            $rows,
            $damage,
            $rating,
            $profile,
            &$result,
        ): void {
            $result['targetResults'] = $this->results->build(
                $game,
                $battle->getId(),
                $rows,
                $choices,
                $damage,
                $rating,
                $profile['itemRuleCode'],
                $profile['profileType'],
                $profile['profileIndex'],
            );
            $this->storeReactions($rows, $choices, $result['targetResults']);
            $this->strikes->close($result['strikeId']);
            $this->battles->advanceVersion($battle->getId(), $expectedBattleVersion);
            $this->remember($game->getId(), $sessionId, $idempotencyKey, [
                'battleId' => $battle->getId(), 'defense' => $choices, 'expectedVersion' => $expectedBattleVersion,
            ], $result);
        });

        return $result;
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
        if (
            !is_string($kind)
            || !is_int($attackerId)
            || !is_string($itemRuleCode)
            || !is_string($profileType)
            || !is_int($profileIndex)
        ) {
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
     * Пишет команду.
     *
     * @param int $gameId Игра.
     * @param int $sessionId Сессия.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $body Тело.
     * @param array<string, mixed> $result Итог.
     *
     * @return void
     *
     * @throws GameBattleConflictException Если ключ уже есть.
     * @throws GameNotFoundException Если сессии нет.
     * @throws GameInvalidException Если поле.
     */
    private function remember(int $gameId, int $sessionId, string $idempotencyKey, array $body, array $result): void
    {
        $this->commands->add($gameId, $sessionId, $idempotencyKey, $body, $result);
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
     * Пишет реакцию только принятой цели.
     *
     * @param list<array<string, mixed>> $rows Цели.
     * @param list<array{reaction: string, blockItemRuleCode: string|null, expectedSheetVersion: int}> $choices Защиты.
     * @param list<array<string, mixed>> $results Уже посчитанные итоги.
     *
     * @return void
     *
     * @throws GameInvalidException Если id битый.
     */
    private function storeReactions(array $rows, array $choices, array $results): void
    {
        foreach ($results as $index => $result) {
            if (($result['code'] ?? null) !== null) {
                continue;
            }

            $targetId = $rows[$index]['id'] ?? null;
            if (!is_int($targetId)) {
                throw new GameInvalidException('Game wide strike target row is invalid');
            }

            $this->targets->close($targetId, $choices[$index]['reaction'], $choices[$index]['blockItemRuleCode']);
        }
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
