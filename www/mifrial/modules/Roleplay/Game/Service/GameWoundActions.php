<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Собранные порты сценария раны: параметры конструктора — порты, не поля строки.

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
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
 * Зажать, отпустить и перевязать: лист, ресурс, карта ожидания и одно сообщение чата.
 */
final class GameWoundActions
{
    private readonly GameCommandRepository $commands;

    private readonly GameNpcRepository $npcs;

    private readonly GameCharacterRepository $characters;

    private readonly GameMemberRepository $members;

    private readonly GameDeliverySignal $delivery;

    private readonly GameSessionRepository $sessions;

    private readonly GameStrikeSheetWrites $sheetWrites;

    private readonly GameReplayTransaction $replayTransaction;

    private readonly GamePendingActionCosts $pendingCosts;

    private readonly GameWoundRows $rows;

    private readonly GameWoundPatch $patch;

    private readonly GameWoundPrice $price;

    private readonly GameWoundBattle $battle;

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
        $battles = new GameBattleRepository($smartTableGateway);
        $this->commands = new GameCommandRepository($smartTableGateway);
        $this->npcs = new GameNpcRepository($smartTableGateway);
        $this->characters = new GameCharacterRepository($smartTableGateway);
        $this->members = new GameMemberRepository($smartTableGateway);
        $this->sessions = new GameSessionRepository($smartTableGateway);
        $this->sheetWrites = new GameStrikeSheetWrites($mutations, $this->npcs);
        $this->replayTransaction = new GameReplayTransaction($smartTableGateway);
        $this->pendingCosts = new GamePendingActionCosts();
        $this->delivery = new GameDeliverySignal($events);
        $this->rows = new GameWoundRows($rules, $this->npcs, $battles);
        $grants = new GameWoundGrants();
        $this->patch = new GameWoundPatch(new GameWoundHands($rules, $this->characters, $this->npcs), $grants);
        $this->price = new GameWoundPrice($this->pendingCosts, $grants, $this->rows);
        $this->battle = new GameWoundBattle($this->rows, $battles, $this->sheetWrites);
    }

    /**
     * Выполняет зажим, отпускание или перевязку.
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

        $this->assertWriter($game, $actorUserId, $editAll, $body);
        $sessionId = $this->sessions->findSessionId($gameId);
        $key = $body['idempotencyKey'] ?? null;
        if ($sessionId === null || !is_string($key) || $key === '') {
            throw new GameInvalidException('Game wound action is invalid');
        }

        return $this->commit($game, $actorUserId, $sessionId, $key, $body);
    }

    /**
     * Исполняет рану после доплаты долга.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param array<string, mixed> $body Сохранённое объявление.
     * @param int $expectedBattle Версия боя.
     * @param int $expectedSheet Версия листа после доплаты.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если команда отвергнута.
     * @throws GameNotFoundException Если цели нет.
     * @throws GameBattleConflictException Если версия боя чужая.
     */
    public function resume(
        GameRecord $game,
        int $actorUserId,
        array $body,
        int $expectedBattle,
        int $expectedSheet,
    ): array {
        $body['expectedBattleVersion'] = $expectedBattle;
        $body['expectedSheetVersion'] = $expectedSheet;

        return $this->perform($game, $actorUserId, $body, true);
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
            fn (): ?array => $this->commands->findByKey($game->getId(), 'wound', $key),
            fn (): int => $this->commands->reserve($game->getId(), $sessionId, 'wound', $key, $body),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            fn (): array => $this->perform($game, $actorUserId, $body),
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

        $stored = $this->commands->findByKey($gameId, 'wound', $idempotencyKey);
        if ($stored !== null) {
            $this->delivery->recorded($gameId, 'wound', $stored['id'], []);
        }
    }

    /**
     * Пишет лист, карту и чат.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param array<string, mixed> $body Тело.
     * @param bool $paid Трата уже списана при доплате долга.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если команда отвергнута.
     * @throws GameNotFoundException Если цели нет.
     * @throws GameBattleConflictException Если версия боя чужая.
     */
    private function perform(GameRecord $game, int $actorUserId, array $body, bool $paid = false): array
    {
        $prepared = $this->prepare($game, $body);
        $opened = $this->battle->pay($game, $body, $prepared, $paid);
        if ($opened !== null) {
            return $opened;
        }

        $version = $this->writeSheets($game, $prepared, $paid);
        $pending = $this->pendingCosts->complete(
            $prepared['slice'],
            $prepared['actorSheet']['choices'],
            $prepared['actionRuleCode'],
            $prepared['actor']['kind'],
            $prepared['actor']['id'],
            $prepared['priced']['pending'],
            $this->rows->spentAmounts($prepared['priced']['spends']),
        );
        $battleVersion = $this->battle->store($body, $pending);
        $this->say($game, $actorUserId, $prepared['operation']);

        return [
            'operation' => $prepared['operation'],
            'target' => $prepared['target'],
            'stateId' => $prepared['stateId'],
            'sheetVersion' => $version,
            'battleVersion' => $battleVersion,
            'opened' => true,
        ];
    }

    /**
     * Собирает листы, строку, правку и цену команды.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Подготовка.
     *
     * @throws GameInvalidException Если команда отвергнута.
     * @throws GameNotFoundException Если цели нет.
     * @throws GameBattleConflictException Если версия боя чужая.
     */
    private function prepare(GameRecord $game, array $body): array
    {
        [$operation, $actor, $target, $stateId, $actionRuleCode, $expected] = $this->fields($body);
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $actorSheet = $this->rows->document($game, $actor['kind'], $actor['id']);
        $targetSheet = $this->rows->document($game, $target['kind'], $target['id']);
        $row = $this->rows->rowOf($targetSheet['sheet'], $stateId);

        return [
            'operation' => $operation,
            'actor' => $actor,
            'target' => $target,
            'stateId' => $stateId,
            'actionRuleCode' => $actionRuleCode,
            'expected' => $expected,
            'slice' => $slice,
            'actorSheet' => $actorSheet,
            'patch' => $this->patch->of($operation, $slice, $actorSheet, $row, $actor, $game),
            'priced' => $this->price->of($slice, $actorSheet['choices'], $actionRuleCode, $actor, $body),
        ];
    }

    /**
     * Разбирает обязательные поля тела.
     *
     * @param array<string, mixed> $body Тело.
     *
     * @return array{0: string, 1: array{kind: string, id: int}, 2: array{kind: string, id: int}, 3: int, 4: string, 5: int} Поля.
     *
     * @throws GameInvalidException Если поле чужое.
     */
    private function fields(array $body): array
    {
        $operation = $body['operation'] ?? null;
        $stateId = $body['stateId'] ?? null;
        $actionRuleCode = $body['actionRuleCode'] ?? null;
        $expected = $body['expectedSheetVersion'] ?? null;
        if (!is_string($operation) || !is_int($stateId) || !is_string($actionRuleCode) || !is_int($expected)) {
            throw new GameInvalidException('Game wound action is invalid');
        }

        return [
            $operation,
            $this->rows->pair($body['actor'] ?? null),
            $this->rows->pair($body['target'] ?? null),
            $stateId,
            $actionRuleCode,
            $expected,
        ];
    }







    /**
     * Пишет лист актора и цели.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $prepared Подготовка.
     * @param bool $paid Трата уже списана.
     *
     * @return int Новая версия цели.
     *
     * @throws GameInvalidException Если лист отвергнут.
     * @throws GameNotFoundException Если строки нет.
     * @throws GameBattleConflictException Если версия листа чужая.
     */
    private function writeSheets(GameRecord $game, array $prepared, bool $paid): int
    {
        $spends = $paid ? [] : $prepared['priced']['spends'];
        $sameActor = $prepared['actor']['kind'] === $prepared['target']['kind']
            && $prepared['actor']['id'] === $prepared['target']['id'];
        if (!$sameActor) {
            $this->sheetWrites->writeResources(
                $game,
                $prepared['actor']['kind'],
                $prepared['actor']['id'],
                $prepared['actorSheet']['actualVersion'],
                $spends,
            );
        }

        return $this->writeTarget($game, $prepared, $sameActor ? $spends : []);
    }

    /**
     * Пишет патч цели.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $prepared Подготовка.
     * @param array<int, \Mifrial\Roleplay\Character\Dto\ResourceSpend> $spends Траты.
     *
     * @return int Новая версия.
     *
     * @throws GameInvalidException Если операция отвергнута.
     * @throws GameNotFoundException Если листа нет.
     * @throws GameBattleConflictException Если версия другая.
     */
    private function writeTarget(GameRecord $game, array $prepared, array $spends): int
    {
        $target = $prepared['target'];

        return $this->sheetWrites->write(
            $game,
            $target['kind'],
            $target['id'],
            $prepared['expected'],
            [$prepared['patch']],
            null,
            null,
            $spends,
        );
    }



    /**
     * Одно текстовое сообщение в чат игры.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор.
     * @param string $operation Операция.
     *
     * @return void
     *
     * @throws GameInvalidException Если чата нет.
     */
    private function say(GameRecord $game, int $actorUserId, string $operation): void
    {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            throw new GameInvalidException('Game chat is missing');
        }

        $text = match ($operation) {
            'hold' => 'Зажимает рану.',
            'release' => 'Отпускает рану.',
            'bandage' => 'Перевязывает рану.',
            default => throw new GameInvalidException('Game wound action is invalid'),
        };
        $this->chats->send($chatId, $actorUserId, $text, []);
    }

    /**
     * Писатель игры или владелец персонажа-актора.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право.
     * @param array<string, mixed> $body Тело.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameNotFoundException Если персонажа нет в игре.
     */
    private function assertWriter(GameRecord $game, int $actorUserId, bool $editAll, array $body): void
    {
        if ($game->getOwnerId() === $actorUserId || $editAll || $this->isGm($game->getId(), $actorUserId)) {
            return;
        }

        $actor = $this->rows->pair($body['actor'] ?? null);
        if ($actor['kind'] !== 'character') {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }

        $row = $this->characters->getByPair($game->getId(), $actor['id']);
        if ($row->getCharacterOwnerId() !== $actorUserId) {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }
    }

    /**
     * Роль gm.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
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
