<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassTooLong -- три команды делят CAS, ключ и транзакцию.
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassComplexityTooHigh -- та же причина.

namespace Mifrial\Roleplay\Game\Service;

use JsonException;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGameChecks;
use Mifrial\Roleplay\Game\Interface\Service\IGameProcesses;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCheckCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameCheckRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameProcessRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * Соло и pairwise. Бросок считает порт, лист — только непустой список операций.
 */
final class GameChecks implements IGameChecks
{
    private readonly GameSessionRepository $sessions;

    private readonly GameCheckCommandRepository $commands;

    private readonly GameCheckRepository $checks;

    private readonly GameNpcRepository $npcs;

    private readonly GameMemberRepository $members;

    private readonly GameBattleRepository $battles;

    private readonly GameSessionRoster $roster;

    private readonly GameProcessRepository $processRows;

    /**
     * Создаёт сценарий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     * @param IGames $games Игра.
     * @param GameCardAccess $cardAccess Карточка.
     * @param IGameProcesses $processes Process.
     * @param ICharacterActualMutations $mutations Порт листа.
     * @param GameCheckRoll $roll Расчёт.
     *
     * @return void
     */
    public function __construct(
        private readonly ISmartTableGateway $smartTableGateway,
        private readonly IGames $games,
        private readonly GameCardAccess $cardAccess,
        private readonly IGameProcesses $processes,
        private readonly ICharacterActualMutations $mutations,
        private readonly GameCheckRoll $roll,
    ) {
        $this->sessions = new GameSessionRepository($smartTableGateway);
        $this->commands = new GameCheckCommandRepository($smartTableGateway);
        $this->checks = new GameCheckRepository($smartTableGateway);
        $this->npcs = new GameNpcRepository($smartTableGateway);
        $this->members = new GameMemberRepository($smartTableGateway);
        $this->battles = new GameBattleRepository($smartTableGateway);
        $this->roster = new GameSessionRoster(new GameSessionRepository($smartTableGateway));
        $this->processRows = new GameProcessRepository($smartTableGateway);
    }

    /**
     * Считает соло и закрывает process.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Ключ.
     * @param int|null $battleId Бой или null.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedSheetVersion Версия листа.
     * @param array $check Решение.
     *
     * @return array<string, mixed> Итог.
     */
    public function declareCheck(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        ?int $battleId,
        string $idempotencyKey,
        int $expectedSheetVersion,
        array $check,
    ): array {
        $choice = $this->choice($check, false);
        $ready = $this->ready($gameId, $actorUserId, $editAll, $viewAll, $idempotencyKey, [
            'battleId' => $battleId,
            'check' => $choice,
            'expectedSheetVersion' => $expectedSheetVersion,
        ]);
        if ($ready['replay'] !== null) {
            return $ready['replay'];
        }

        $this->roll->assertRule($ready['game']->getSpaceId(), $ready['game']->getRulesRevision(), $choice['ruleCode'], 'solo', $choice['asked']);
        $sheet = $this->sheet($ready['game']->getId(), $choice['participant']['type'], $choice['participant']['id'], $expectedSheetVersion);

        return $this->commitSolo($ready['game'], $battleId, $idempotencyKey, $expectedSheetVersion, $choice, $sheet);
    }

    /**
     * Открывает предложение без броска.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Ключ.
     * @param int|null $battleId Бой или null.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedSheetVersion Версия листа.
     * @param array $proposal Решение.
     *
     * @return array<string, mixed> Итог.
     */
    public function proposeCheck(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        ?int $battleId,
        string $idempotencyKey,
        int $expectedSheetVersion,
        array $proposal,
    ): array {
        $choice = $this->choice($proposal, true);
        $ready = $this->ready($gameId, $actorUserId, $editAll, $viewAll, $idempotencyKey, [
            'battleId' => $battleId,
            'proposal' => $choice,
            'expectedSheetVersion' => $expectedSheetVersion,
        ]);
        if ($ready['replay'] !== null) {
            return $ready['replay'];
        }

        $this->roll->assertRule($ready['game']->getSpaceId(), $ready['game']->getRulesRevision(), $choice['ruleCode'], 'joint', $choice['asked']);
        $this->assertTarget($ready['game']->getId(), $battleId, $choice['participant'], $choice['target']);
        $this->sheet($ready['game']->getId(), $choice['participant']['type'], $choice['participant']['id'], $expectedSheetVersion);

        return $this->commitProposal($ready['game'], $battleId, $idempotencyKey, $expectedSheetVersion, $choice);
    }

    /**
     * Принимает или отклоняет предложение.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Ключ.
     * @param int $processId Process.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedSheetVersion Версия листа.
     * @param array $answer Решение.
     *
     * @return array<string, mixed> Итог.
     */
    public function answerCheck(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $processId,
        string $idempotencyKey,
        int $expectedSheetVersion,
        array $answer,
    ): array {
        $decision = $this->decision($answer);
        $ready = $this->ready($gameId, $actorUserId, $editAll, $viewAll, $idempotencyKey, [
            'processId' => $processId,
            'answer' => $decision,
            'expectedSheetVersion' => $expectedSheetVersion,
        ]);
        if ($ready['replay'] !== null) {
            return $ready['replay'];
        }

        $open = $this->stored($ready['game']->getId(), $processId);
        $sheet = $this->sheet($ready['game']->getId(), $open['participantType'], $open['participantId'], $expectedSheetVersion);

        return $this->commitAnswer($ready['game'], $processId, $idempotencyKey, $expectedSheetVersion, $decision, $open, $sheet);
    }

    /**
     * Видимость, повтор, право и сессия.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Ключ.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $body Тело.
     *
     * @return array{game: GameRecord, replay: array<string, mixed>|null} Ход.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameNotFoundException Если карточки нет.
     * @throws GameInvalidException Если ключ, статус или сессия.
     * @throws GameBattleConflictException Если тело другое.
     */
    private function ready(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        string $idempotencyKey,
        array $body,
    ): array {
        $game = $this->games->get($gameId);
        if (!$this->cardAccess->isVisible($game, $actorUserId, $viewAll)) {
            throw new GameNotFoundException('Game was not found');
        }

        $replay = $this->replay($gameId, $idempotencyKey, $body);
        if ($replay !== null) {
            return ['game' => $game, 'replay' => $replay];
        }

        if ($idempotencyKey === '' || $game->isCompleted() || $this->sessions->findSessionId($gameId) === null) {
            throw new GameInvalidException('Game check is invalid');
        }

        $this->assertWriter($game, $actorUserId, $editAll);

        return ['game' => $game, 'replay' => null];
    }

    /**
     * Соло в одной транзакции.
     *
     * @param GameRecord $game Игра.
     * @param int|null $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedSheetVersion Версия.
     * @param array $choice Решение.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array<string, mixed> Итог.
     */
    private function commitSolo(
        GameRecord $game,
        ?int $battleId,
        string $idempotencyKey,
        int $expectedSheetVersion,
        array $choice,
        array $sheet,
    ): array {
        $result = $this->view(0, 'resolved', null, null);
        $this->smartTableGateway->transaction(function () use (
            $game,
            $battleId,
            $idempotencyKey,
            $expectedSheetVersion,
            $choice,
            $sheet,
            &$result,
        ): void {
            $result = $this->applySolo($game, $battleId, $idempotencyKey, $expectedSheetVersion, $choice, $sheet);
        });

        return $result;
    }

    /**
     * Пишет соло внутри уже открытой транзакции.
     *
     * @param GameRecord $game Игра.
     * @param int|null $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedSheetVersion Версия.
     * @param array $choice Решение.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array<string, mixed> Итог.
     */
    private function applySolo(
        GameRecord $game,
        ?int $battleId,
        string $idempotencyKey,
        int $expectedSheetVersion,
        array $choice,
        array $sheet,
    ): array {
        $opened = $this->processes->open($game->getId(), $battleId, $choice['participant']['type'], $choice['participant']['id']);
        $thrown = $this->roll->throwCheck($game->getSpaceId(), $game->getRulesRevision(), $choice['ruleCode'], 'solo', $sheet, $choice['asked']);
        $sheetVersion = $this->writeSheet($game, $choice['participant']['type'], $choice['participant']['id'], $expectedSheetVersion);
        $this->processes->resolve($opened['processId']);
        $result = $this->view($opened['processId'], 'resolved', $thrown, $sheetVersion);
        $this->checks->add($this->row($opened, 'solo', $choice, 'accepted', $thrown));
        $this->commands->add($game->getId(), $opened['sessionId'], $idempotencyKey, [
            'battleId' => $battleId,
            'check' => $choice,
            'expectedSheetVersion' => $expectedSheetVersion,
        ], $result);

        return $result;
    }

    /**
     * Предложение в одной транзакции.
     *
     * @param GameRecord $game Игра.
     * @param int|null $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedSheetVersion Версия.
     * @param array $choice Решение.
     *
     * @return array<string, mixed> Итог.
     */
    private function commitProposal(
        GameRecord $game,
        ?int $battleId,
        string $idempotencyKey,
        int $expectedSheetVersion,
        array $choice,
    ): array {
        $result = $this->view(0, 'open', null, null);
        $this->smartTableGateway->transaction(function () use (
            $game,
            $battleId,
            $idempotencyKey,
            $expectedSheetVersion,
            $choice,
            &$result,
        ): void {
            $opened = $this->processes->open($game->getId(), $battleId, $choice['participant']['type'], $choice['participant']['id']);
            $result = $this->view($opened['processId'], 'open', null, null);
            $this->checks->add($this->row($opened, 'pairwise', $choice, 'pending', null));
            $this->commands->add($game->getId(), $opened['sessionId'], $idempotencyKey, [
                'battleId' => $battleId,
                'proposal' => $choice,
                'expectedSheetVersion' => $expectedSheetVersion,
            ], $result);
        });

        return $result;
    }

    /**
     * Ответ в одной транзакции.
     *
     * @param GameRecord $game Игра.
     * @param int $processId Process.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedSheetVersion Версия.
     * @param string $decision accept или decline.
     * @param array $open Сохранённое предложение.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array<string, mixed> Итог.
     */
    private function commitAnswer(
        GameRecord $game,
        int $processId,
        string $idempotencyKey,
        int $expectedSheetVersion,
        string $decision,
        array $open,
        array $sheet,
    ): array {
        $result = $this->view($processId, 'cancelled', null, null);
        $this->smartTableGateway->transaction(function () use (
            $game,
            $processId,
            $idempotencyKey,
            $expectedSheetVersion,
            $decision,
            $open,
            $sheet,
            &$result,
        ): void {
            $result = $this->applyAnswer($game, $processId, $decision, $open, $sheet, $expectedSheetVersion);
            $this->commands->add($game->getId(), $open['sessionId'], $idempotencyKey, [
                'processId' => $processId,
                'answer' => $decision,
                'expectedSheetVersion' => $expectedSheetVersion,
            ], $result);
        });

        return $result;
    }

    /**
     * Принимает или отклоняет внутри транзакции.
     *
     * @param GameRecord $game Игра.
     * @param int $processId Process.
     * @param string $decision accept или decline.
     * @param array $open Сохранённое предложение.
     * @param array<string, mixed> $sheet Лист.
     * @param int $expectedSheetVersion Версия.
     *
     * @return array<string, mixed> Итог.
     */
    private function applyAnswer(
        GameRecord $game,
        int $processId,
        string $decision,
        array $open,
        array $sheet,
        int $expectedSheetVersion,
    ): array {
        if ($decision !== 'accept') {
            $this->processes->cancel($processId);
            $this->checks->storeOutcome($processId, 'declined', null);

            return $this->view($processId, 'cancelled', null, null);
        }

        $thrown = $this->roll->throwCheck($game->getSpaceId(), $game->getRulesRevision(), $open['ruleCode'], 'joint', $sheet, $open['asked']);
        $sheetVersion = $this->writeSheet($game, $open['participantType'], $open['participantId'], $expectedSheetVersion);
        $this->processes->resolve($processId);
        $this->checks->storeOutcome($processId, 'accepted', $thrown);

        return $this->view($processId, 'resolved', $thrown, $sheetVersion);
    }

    /**
     * Версия листа совпала. Возвращает sheet.
     *
     * @param int $gameId Игра.
     * @param string $type Вид.
     * @param int $id Участник.
     * @param int $expected Ожидание.
     *
     * @return array<string, mixed> Лист.
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameNotFoundException Если строки нет.
     */
    private function sheet(int $gameId, string $type, int $id, int $expected): array
    {
        if ($type === 'character') {
            $pair = $this->roll->characterSheet($id);
            if ($pair['version'] !== $expected) {
                throw new GameBattleConflictException($pair['version'], 'Game check sheet version conflict');
            }

            return $pair['sheet'];
        }

        $npc = $this->npcs->getById($id);
        if ($npc->getGameId() !== $gameId) {
            throw new GameNotFoundException('Game NPC was not found');
        }

        if ($npc->getActualVersion() !== $expected) {
            throw new GameBattleConflictException($npc->getActualVersion(), 'Game check sheet version conflict');
        }

        $sheet = $npc->getVersion()['sheet'] ?? null;
        if (!is_array($sheet)) {
            throw new GameInvalidException('Game check sheet is invalid');
        }

        return $sheet;
    }

    /**
     * Пишет лист, если список операций не пуст. Пустой список версию не меняет.
     *
     * @param GameRecord $game Игра.
     * @param string $type Вид.
     * @param int $id Участник.
     * @param int $expected Версия.
     *
     * @return int|null Новая версия или null.
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameInvalidException Если операция чужая.
     * @throws GameNotFoundException Если строки нет.
     */
    private function writeSheet(GameRecord $game, string $type, int $id, int $expected): ?int
    {
        $operations = $this->roll->operations();
        if ($operations === []) {
            return null;
        }

        try {
            return $type === 'character'
                ? $this->writeCharacter($id, $expected, $operations)
                : $this->writeNpc($game, $id, $expected, $operations);
        } catch (CharacterConflictException $exception) {
            throw new GameBattleConflictException($exception->getCurrentVersion(), 'Game check sheet version conflict', $exception);
        } catch (CharacterInvalidException $exception) {
            throw new GameInvalidException('Game check sheet is invalid', $exception);
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game check sheet was not found', $exception);
        }
    }

    /**
     * Персонаж через порт G10.
     *
     * @param int $characterId Персонаж.
     * @param int $expected Версия.
     * @param array<int, array<string, mixed>> $operations Список.
     *
     * @return int Версия.
     */
    private function writeCharacter(int $characterId, int $expected, array $operations): int
    {
        return $this->mutations->apply($characterId, $expected, $operations)->getActualVersion();
    }

    /**
     * NPC через документ и replaceVersion уже лежащей строки.
     *
     * @param GameRecord $game Игра.
     * @param int $npcId NPC.
     * @param int $expected Версия.
     * @param array<int, array<string, mixed>> $operations Список.
     *
     * @return int Версия.
     *
     * @throws GameInvalidException Если документ без ревизии.
     * @throws GameNotFoundException Если строки нет.
     */
    private function writeNpc(GameRecord $game, int $npcId, int $expected, array $operations): int
    {
        $npc = $this->npcs->getById($npcId);
        if ($npc->getGameId() !== $game->getId()) {
            throw new GameNotFoundException('Game NPC was not found');
        }

        $document = $npc->getVersion();
        $choices = $document['choices'] ?? null;
        $sheet = $document['sheet'] ?? null;
        $spaceId = $document['spaceId'] ?? null;
        $revision = $document['rulesRevision'] ?? null;
        if (!is_array($choices) || !is_array($sheet) || !is_int($spaceId) || !is_int($revision)) {
            throw new GameInvalidException('Game check sheet is invalid');
        }

        $patched = $this->mutations->applyToDocument($spaceId, $revision, $choices, $sheet, $operations);
        $document['choices'] = $patched['choices'];
        $document['sheet'] = $patched['sheet'];

        return $this->npcs->replaceVersion($npcId, $document, $expected)->getActualVersion();
    }

    /**
     * Цель в той же границе и не совпадает с проверяемым.
     *
     * @param int $gameId Игра.
     * @param int|null $battleId Бой.
     * @param array{type: string, id: int} $participant Проверяемый.
     * @param array{type: string, id: int}|null $target Цель.
     *
     * @return void
     *
     * @throws GameNotFoundException Если цели нет.
     * @throws GameInvalidException Если цель совпала.
     */
    private function assertTarget(int $gameId, ?int $battleId, array $participant, ?array $target): void
    {
        if ($target === null || $this->sameParty($participant, $target)) {
            throw new GameInvalidException('Game check target is invalid');
        }

        if ($battleId === null) {
            $this->assertSessionTarget($gameId, $target);

            return;
        }

        $this->assertBattleTarget($gameId, $battleId, $target);
    }

    /**
     * Цель и проверяемый — одна строка.
     *
     * @param array{type: string, id: int} $participant Проверяемый.
     * @param array{type: string, id: int} $target Цель.
     *
     * @return bool Совпали.
     */
    private function sameParty(array $participant, array $target): bool
    {
        return $target['type'] === $participant['type'] && $target['id'] === $participant['id'];
    }

    /**
     * Цель входит в состав этого боя.
     *
     * @param int $gameId Игра.
     * @param int $battleId Бой.
     * @param array{type: string, id: int} $target Цель.
     *
     * @return void
     *
     * @throws GameNotFoundException Если цели нет.
     */
    private function assertBattleTarget(int $gameId, int $battleId, array $target): void
    {
        $battle = $this->battles->find($battleId);
        $sessionId = $this->sessions->findSessionId($gameId);
        if ($battle === null || $sessionId === null || $battle->getSessionId() !== $sessionId) {
            throw new GameNotFoundException('Game battle was not found');
        }

        foreach ($this->battles->findParticipants($battleId) as $row) {
            if ($row['type'] === $target['type'] && $row['id'] === $target['id']) {
                return;
            }
        }

        throw new GameNotFoundException('Game battle participant was not found');
    }

    /**
     * Цель сессии.
     *
     * @param int $gameId Игра.
     * @param array{type: string, id: int} $target Цель.
     *
     * @return void
     *
     * @throws GameNotFoundException Если цели нет.
     */
    private function assertSessionTarget(int $gameId, array $target): void
    {
        if ($target['type'] === 'character' && !$this->roster->isParticipant($gameId, $target['id'])) {
            throw new GameNotFoundException('Game character was not found');
        }

        if ($target['type'] === 'npc' && $this->npcs->getById($target['id'])->getGameId() !== $gameId) {
            throw new GameNotFoundException('Game NPC was not found');
        }
    }

    /**
     * Открытый process этой сессии и сохранённое предложение.
     *
     * @param int $gameId Игра.
     * @param int $processId Process.
     *
     * @return array{sessionId: int, participantType: string, participantId: int, ruleCode: string, asked: array{base: int, size: int}|null} Пара.
     *
     * @throws GameNotFoundException Если process чужой.
     * @throws GameInvalidException Если статус не open.
     */
    private function stored(int $gameId, int $processId): array
    {
        $sessionId = $this->sessions->findSessionId($gameId);
        $row = $this->processRows->getById($processId);
        if ($sessionId === null || $row->getSessionId() !== $sessionId) {
            throw new GameNotFoundException('Game process was not found');
        }

        if ($row->getStatus() !== 'open') {
            throw new GameInvalidException('Game process is not open');
        }

        $check = $this->checks->findByProcess($processId);
        if ($check === null) {
            throw new GameNotFoundException('Game check was not found');
        }

        return [
            'sessionId' => $sessionId,
            'participantType' => $row->getParticipantType(),
            'participantId' => $row->getParticipantId(),
            'ruleCode' => $check['ruleCode'],
            'asked' => $check['asked'],
        ];
    }

    /**
     * Право писать.
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
        if ($game->getOwnerId() === $actorUserId || $editAll) {
            return;
        }

        try {
            $gm = $this->members->getByPair($game->getId(), $actorUserId)->getRole() === 'gm';
        } catch (GameNotFoundException) {
            $gm = false;
        }

        if (!$gm) {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }
    }

    /**
     * Решение соло или предложения.
     *
     * @param array $body Тело.
     * @param bool $withTarget Нужна цель.
     *
     * @return array{participant: array{type: string, id: int}, ruleCode: string, asked: array{base: int, size: int}|null, target: array{type: string, id: int}|null} Выбор.
     *
     * @throws GameInvalidException Если поле чужое.
     */
    private function choice(array $body, bool $withTarget): array
    {
        foreach (['difficulty', 'roll', 'success', 'damage', 'operations', 'sheet', 'targets'] as $key) {
            if (array_key_exists($key, $body)) {
                throw new ActionException('INVALID_PARAMS', 'Game check field is invalid');
            }
        }

        $participant = $this->party($body['participant'] ?? null);
        $ruleCode = $body['ruleCode'] ?? null;
        if (!is_string($ruleCode) || $ruleCode === '') {
            throw new GameInvalidException('Game check rule is invalid');
        }

        return [
            'participant' => $participant,
            'ruleCode' => $ruleCode,
            'asked' => $this->asked($body['askedDifficulty'] ?? null),
            'target' => $withTarget ? $this->party($body['target'] ?? null) : null,
        ];
    }

    /**
     * Ответ цели.
     *
     * @param array $body Тело.
     *
     * @return string accept или decline.
     *
     * @throws GameInvalidException Если решение чужое.
     */
    private function decision(array $body): string
    {
        $decision = $body['decision'] ?? null;
        if ($decision !== 'accept' && $decision !== 'decline') {
            throw new GameInvalidException('Game check answer is invalid');
        }

        return $decision;
    }

    /**
     * Участник.
     *
     * @param mixed $value Тело.
     *
     * @return array{type: string, id: int} Пара.
     *
     * @throws GameInvalidException Если вид чужой.
     */
    private function party(mixed $value): array
    {
        if (!is_array($value) || !in_array($value['type'] ?? null, ['character', 'npc'], true) || !is_int($value['id'] ?? null) || $value['id'] < 1) {
            throw new GameInvalidException('Game check participant is invalid');
        }

        return ['type' => $value['type'], 'id' => $value['id']];
    }

    /**
     * Трудность ask или null.
     *
     * @param mixed $value Тело.
     *
     * @return array{base: int, size: int}|null Пара.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private function asked(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value) || !is_int($value['base'] ?? null) || !is_int($value['size'] ?? null)) {
            throw new GameInvalidException('Game check difficulty is invalid');
        }

        return ['base' => $value['base'], 'size' => $value['size']];
    }

    /**
     * Ответ HTTP.
     *
     * @param int $processId Process.
     * @param string $status Статус.
     * @param array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int}|null $thrown Итог или null.
     * @param int|null $sheetVersion Версия листа.
     *
     * @return array<string, mixed> Тело.
     */
    private function view(int $processId, string $status, ?array $thrown, ?int $sheetVersion): array
    {
        return [
            'processId' => $processId,
            'status' => $status,
            'difficulty' => $thrown['difficulty'] ?? null,
            'roll' => $thrown['roll'] ?? null,
            'success' => $thrown['success'] ?? null,
            'rating' => $thrown['rating'] ?? null,
            'sheetVersion' => $sheetVersion,
        ];
    }

    /**
     * Колонки проверки.
     *
     * @param array<string, mixed> $opened Строка process.
     * @param string $mode solo или pairwise.
     * @param array{participant: array{type: string, id: int}, ruleCode: string, asked: array{base: int, size: int}|null, target: array{type: string, id: int}|null} $choice Выбор.
     * @param string $offer Статус предложения.
     * @param array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int}|null $thrown Итог.
     *
     * @return array<string, mixed> Колонки.
     */
    private function row(array $opened, string $mode, array $choice, string $offer, ?array $thrown): array
    {
        $fields = [
            'process_id' => $opened['processId'],
            'session_id' => $opened['sessionId'],
            'mode' => $mode,
            'rule_code' => $choice['ruleCode'],
            'offer' => $offer,
            'asked_base' => $choice['asked']['base'] ?? null,
            'asked_size' => $choice['asked']['size'] ?? null,
        ];
        if ($choice['target'] !== null) {
            $fields['target_type'] = $choice['target']['type'];
            $fields['target_id'] = $choice['target']['id'];
        }

        if ($thrown !== null) {
            $fields['difficulty_base'] = $thrown['difficulty']['base'];
            $fields['difficulty_size'] = $thrown['difficulty']['size'];
            $fields['success_base'] = $thrown['roll']['base'];
            $fields['success_size'] = $thrown['roll']['size'];
            $fields['passed'] = $thrown['success'];
            $fields['rating'] = $thrown['rating'];
        }

        return $fields;
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
            throw new GameInvalidException('Game check body is invalid', $exception);
        }

        if (!$same) {
            throw new GameBattleConflictException(null, 'Game check key body conflict');
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
}
