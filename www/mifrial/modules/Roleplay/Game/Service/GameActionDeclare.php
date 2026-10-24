<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassTooLong

use Closure;
use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * HTTP-команда не-атакующего действия.
 */
final class GameActionDeclare
{
    private readonly GameBattleRepository $battles;

    private readonly GameActionRepository $actions;

    private readonly GameCommandRepository $commands;

    private readonly GameSessionRepository $sessions;

    private readonly GameStrikeAccess $access;

    private readonly GameReplayTransaction $replay;

    private readonly GameDeliverySignal $delivery;

    /**
     * Создаёт сценарий.
     *
     * @param ISmartTableGateway $gateway Шлюз таблиц.
     * @param IGames $games Игры.
     * @param GameCardAccess $cardAccess Видимость карточки.
     * @param GameActionPerform $perform Общий сценарий действия.
     * @param GamePreparedDefense $prepared Подготовка.
     * @param GameActionChat $chat Сообщение чата.
     * @param GameStrikeSheets $sheets Проекция конфликта листа.
     * @param IEventManager $events События доставки.
     *
     * @return void
     */
    public function __construct(
        ISmartTableGateway $gateway,
        private readonly IGames $games,
        private readonly GameCardAccess $cardAccess,
        private readonly GameActionPerform $perform,
        private readonly GamePreparedDefense $prepared,
        private readonly GameActionChat $chat,
        private readonly GameStrikeSheets $sheets,
        IEventManager $events,
    ) {
        $this->battles = new GameBattleRepository($gateway);
        $this->actions = new GameActionRepository($gateway);
        $this->commands = new GameCommandRepository($gateway);
        $this->sessions = new GameSessionRepository($gateway);
        $characters = new GameCharacterRepository($gateway);
        $members = new GameMemberRepository($gateway);
        $this->access = new GameStrikeAccess($members, $characters, $this->battles, $cardAccess);
        $this->replay = new GameReplayTransaction($gateway);
        $this->delivery = new GameDeliverySignal($events);
    }

    /**
     * Выполняет команду.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Тело команды.
     *
     * @return array<string, mixed> Узкий итог.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameNotFoundException Если игры или боя нет.
     * @throws GameInvalidException Если команда отвергнута.
     * @throws GameBattleConflictException Если версия или ключ чужие.
     */
    public function act(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        $game = $this->games->get($gameId);
        $this->access->assertVisible($game, $actorUserId, $viewAll);
        $key = $body['idempotencyKey'] ?? null;
        $sessionId = $this->sessions->findSessionId($gameId);
        if ($sessionId === null || !is_string($key) || $key === '') {
            throw new GameInvalidException('Game action is invalid');
        }

        $execution = $this->replay->execute(
            fn (): ?array => $this->commands->findByKey($gameId, 'action', $key),
            fn (): int => $this->commands->reserve($gameId, $sessionId, 'action', $key, $body),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            fn (): array => $this->doAct($game, $actorUserId, $editAll, $viewAll, $body),
            $body,
        );
        $this->announce($gameId, $key, $execution['replay']);

        return $execution['result'];
    }

    /**
     * Пишет лист, ожидание, подготовку и версию боя.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameInvalidException Если команда отвергнута.
     * @throws GameNotFoundException Если актора, цели или боя нет.
     * @throws GameBattleConflictException Если версия боя или листа другая.
     */
    private function doAct(
        GameRecord $game,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        $input = $this->input($body);
        $followUp = $this->perform->requiresFollowUp($game, $input['actionRuleCode']);
        if ($followUp && $input['targetKey'] === null) {
            throw new GameInvalidException('Game action target is missing');
        }

        $battle = $this->battleFor($game, $actorUserId, $editAll, $input, $followUp);
        $applied = $this->apply(
            $game,
            $input,
            $battle->getPendingActionEffects(),
            $actorUserId,
            $viewAll,
            $followUp,
        );
        $this->postChat($game, $actorUserId, $input, $applied, $battle);

        return $this->result($battle, $input, $applied, $followUp);
    }

    /**
     * Пишет сообщение о действии в тред текущего хода.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param array<string, mixed> $input Вход.
     * @param array<string, mixed> $applied Итог действия.
     * @param GameBattleRecord $battle Бой.
     *
     * @return void
     *
     * @throws GameInvalidException Если чат недоступен.
     */
    private function postChat(
        GameRecord $game,
        int $actorUserId,
        array $input,
        array $applied,
        GameBattleRecord $battle,
    ): void {
        $this->chat->post(
            $game,
            $actorUserId,
            $input['actor']['type'],
            $input['actor']['id'],
            $input['actionRuleCode'],
            $applied['performed']['spent'],
            $battle->getId(),
            $battle->getRound(),
            $battle->getActiveIndex(),
        );
    }

    /**
     * Узкий итог команды.
     *
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $input Вход.
     * @param array<string, mixed> $applied Итог действия.
     * @param bool $followUp Нужна ли подготовка после прошлого удара.
     *
     * @return array<string, mixed> Итог.
     */
    private function result(
        GameBattleRecord $battle,
        array $input,
        array $applied,
        bool $followUp,
    ): array {
        return [
            'battleId' => $battle->getId(),
            'version' => $applied['version'],
            'spent' => $applied['performed']['spent'],
            'sheetVersion' => $applied['performed']['sheetVersion'],
            'targetKey' => $followUp ? $input['targetKey'] : null,
            'reaction' => $applied['reaction'],
            'pendingActionEffects' => $applied['pending'],
        ];
    }

    /**
     * Выполняет действие и пишет подготовку.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $input Вход.
     * @param array<string, mixed> $pending Ожидание.
     * @param int $actorUserId Пользователь.
     * @param bool $viewAll Полный просмотр.
     * @param bool $followUp Нужна ли подготовка после прошлого удара.
     *
     * @return array{
     *     pending: array<string, mixed>,
     *     performed: array<string, mixed>,
     *     version: int,
     *     reaction: string|null
     * } Итог.
     *
     * @throws GameInvalidException Если действие отвергнуто.
     * @throws GameNotFoundException Если листа нет.
     * @throws GameBattleConflictException Если версия листа или боя другая.
     */
    private function apply(
        GameRecord $game,
        array $input,
        array $pending,
        int $actorUserId,
        bool $viewAll,
        bool $followUp,
    ): array {
        $reaction = $followUp
            ? $this->perform->followUpReaction(
                $game,
                $input['actionRuleCode'],
                $pending,
                $input['actor']['type'],
                $input['actor']['id'],
                (string) $input['targetKey'],
            )
            : null;
        $performed = $this->performAction($game, $input, $pending, $actorUserId, $viewAll, !$followUp);
        $stored = $this->store($input, $performed['pending'], $reaction);

        return [
            'pending' => $stored['pending'],
            'performed' => $performed,
            'version' => $stored['version'],
            'reaction' => $reaction,
        ];
    }

    /**
     * Сохраняет ожидание и, если нужно, подготовку.
     *
     * @param array<string, mixed> $input Вход.
     * @param array<string, mixed> $pending Ожидание.
     * @param string|null $reaction Реакция цели или null для обычного действия.
     *
     * @return array{pending: array<string, mixed>, version: int} Итог.
     *
     * @throws GameBattleConflictException Если версия боя другая.
     */
    private function store(array $input, array $pending, ?string $reaction): array
    {
        if ($reaction !== null) {
            $pending = $this->prepared->replace(
                $pending,
                $input['actor']['type'],
                $input['actor']['id'],
                (string) $input['targetKey'],
                $reaction,
                $input['actionRuleCode'],
            );
        }

        $battle = $this->battles->replacePending($input['battleId'], $input['expectedBattle'], $pending);

        return ['pending' => $pending, 'version' => $battle->getStateVersion()];
    }

    /**
     * Выполняет общий сценарий действия.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $input Вход.
     * @param array<string, mixed> $pending Ожидание.
     * @param int $actorUserId Пользователь.
     * @param bool $viewAll Полный просмотр.
     * @param bool $allowZeroChosen Разрешить chosen 0.
     *
     * @return array<string, mixed> Итог сценария.
     *
     * @throws GameInvalidException Если действие отвергнуто.
     * @throws GameNotFoundException Если листа нет.
     * @throws GameBattleConflictException Если версия листа другая.
     */
    private function performAction(
        GameRecord $game,
        array $input,
        array $pending,
        int $actorUserId,
        bool $viewAll,
        bool $allowZeroChosen,
    ): array {
        return $this->perform->perform(
            $game,
            $input['actor']['type'],
            $input['actor']['id'],
            $input['actionRuleCode'],
            $input['expectedSheet'],
            $pending,
            $allowZeroChosen,
            $this->conflictProjection(
                $game,
                ['type' => $input['actor']['type'], 'id' => $input['actor']['id']],
                $actorUserId,
                $viewAll,
            ),
        );
    }

    /**
     * Разбирает тело команды.
     *
     * @param array<string, mixed> $body Тело.
     *
     * @return array{
     *     battleId: int,
     *     expectedBattle: int,
     *     expectedSheet: int,
     *     actionRuleCode: string,
     *     targetKey: string|null,
     *     actor: array{type: string, id: int}
     * } Вход.
     *
     * @throws GameInvalidException Если поле чужое.
     */
    private function input(array $body): array
    {
        $battleId = $body['battleId'] ?? null;
        $expectedBattle = $body['expectedVersion'] ?? null;
        $expectedSheet = $body['expectedSheetVersion'] ?? null;
        $actionRuleCode = $body['actionRuleCode'] ?? null;
        $targetKey = $body['followUpTargetKey'] ?? null;
        $actor = $this->pair($body['actor'] ?? null);
        if (!is_int($battleId) || !is_int($expectedBattle) || !is_int($expectedSheet)) {
            throw new GameInvalidException('Game action is invalid');
        }

        if (!is_string($actionRuleCode) || ($targetKey !== null && !is_string($targetKey))) {
            throw new GameInvalidException('Game action is invalid');
        }

        return [
            'battleId' => $battleId,
            'expectedBattle' => $expectedBattle,
            'expectedSheet' => $expectedSheet,
            'actionRuleCode' => $actionRuleCode,
            'targetKey' => $targetKey,
            'actor' => $actor,
        ];
    }

    /**
     * Проверяет доступ, бой, цель и отсутствие открытого действия.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param array<string, mixed> $input Вход.
     * @param bool $followUp Нужна ли цель прошлого удара.
     *
     * @return GameBattleRecord Бой.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameInvalidException Если действие уже открыто.
     * @throws GameNotFoundException Если боя или цели нет.
     * @throws GameBattleConflictException Если версия боя другая.
     */
    private function battleFor(
        GameRecord $game,
        int $actorUserId,
        bool $editAll,
        array $input,
        bool $followUp,
    ): GameBattleRecord {
        $this->access->assertAttacker($game, $actorUserId, $editAll, $input['actor']);
        $sessionId = $this->sessions->findSessionId($game->getId());
        $battle = $this->battleOf($sessionId, $input['battleId'], $input['expectedBattle']);
        if ($this->actions->findOpenAny($input['battleId']) !== null) {
            throw new GameInvalidException('Game action is already open');
        }

        $targets = $followUp ? [$this->targetOfKey((string) $input['targetKey'])] : [];
        $this->access->assertRoster($input['battleId'], $input['actor'], $targets);

        return $battle;
    }

    /**
     * Бой текущей сессии с проверкой версии.
     *
     * @param int|null $sessionId Сессия.
     * @param int $battleId Бой.
     * @param int $expectedVersion Ожидаемая версия.
     *
     * @return GameBattleRecord Строка боя.
     *
     * @throws GameNotFoundException Если боя нет.
     * @throws GameBattleConflictException Если версия другая.
     */
    private function battleOf(?int $sessionId, int $battleId, int $expectedVersion): GameBattleRecord
    {
        $battle = $this->battles->find($battleId);
        if ($battle === null || $sessionId === null || $battle->getSessionId() !== $sessionId) {
            throw new GameNotFoundException('Game action battle was not found');
        }

        if ($battle->getStateVersion() !== $expectedVersion) {
            throw new GameBattleConflictException($battle->getStateVersion());
        }

        return $battle;
    }

    /**
     * Пара type/id из JSON.
     *
     * @param mixed $pair Значение.
     *
     * @return array{type: string, id: int} Пара.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private function pair(mixed $pair): array
    {
        $type = is_array($pair) ? ($pair['type'] ?? null) : null;
        $id = is_array($pair) ? ($pair['id'] ?? null) : null;
        if (($type !== 'character' && $type !== 'npc') || !is_int($id) || $id < 1) {
            throw new GameInvalidException('Game action actor is invalid');
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * Пара type/id из ключа цели.
     *
     * @param string $key Ключ character:N или npc:N.
     *
     * @return array{type: string, id: int} Пара.
     *
     * @throws GameInvalidException Если ключ чужой.
     */
    private function targetOfKey(string $key): array
    {
        $parts = explode(':', $key, 2);
        $type = $parts[0] ?? null;
        $rawId = $parts[1] ?? null;
        $id = is_string($rawId) && ctype_digit($rawId) ? (int) $rawId : 0;
        if (($type !== 'character' && $type !== 'npc') || $id < 1) {
            throw new GameInvalidException('Game action target is invalid');
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * Проектор конфликта листа актора.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $actor Актор.
     * @param int $actorUserId Пользователь.
     * @param bool $viewAll Полный просмотр.
     *
     * @return Closure(
     *     CharacterConflictException|GameEconomyConflictException
     * ): array{choices: array<string, mixed>, sheet: array<string, mixed>} Проектор.
     */
    private function conflictProjection(
        GameRecord $game,
        array $actor,
        int $actorUserId,
        bool $viewAll,
    ): Closure {
        return fn (CharacterConflictException|GameEconomyConflictException $exception): array => $this->sheets
            ->projectWriteConflict($game, $actor, $exception, $actorUserId, $viewAll);
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

        $stored = $this->commands->findByKey($gameId, 'action', $idempotencyKey);
        if ($stored !== null) {
            $this->delivery->recorded($gameId, 'action', $stored['id'], []);
        }
    }
}
