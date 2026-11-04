<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassTooLong -- три команды делят CAS, ключ и транзакцию.
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassComplexityTooHigh -- та же причина.

namespace Mifrial\Roleplay\Game\Service;

use JsonException;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Game\Dto\GameCheckThrowOverride;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGameChecks;
use Mifrial\Roleplay\Game\Interface\Service\IGameProcesses;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameCheckRepository;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameProcessRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckSpec;

/**
 * Соло и pairwise. Бросок считает порт, лист — только непустой список операций.
 */
final class GameChecks implements IGameChecks
{
    private readonly GameSessionRepository $sessions;

    private readonly GameCommandRepository $commands;

    private readonly GameCheckRepository $checks;

    private readonly GameNpcRepository $npcs;

    private readonly GameMemberRepository $members;

    private readonly GameBattleRepository $battles;

    private readonly GameSessionRoster $roster;

    private readonly GameProcessRepository $processRows;

    private readonly GameStrikeAccess $access;

    private readonly GameStrikeSheetWrites $sheetWrites;

    /**
     * Создаёт сценарий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     * @param IGames $games Игра.
     * @param GameCardAccess $cardAccess Карточка.
     * @param IGameProcesses $processes Process.
     * @param ICharacterActualMutations $mutations Порт листа.
     * @param GameCheckRoll $roll Расчёт.
     * @param GameCheckChat $chat Сообщения проверок.
     * @param GameConcentration $concentration Жетоны.
     * @param GameConcentrationCycle $concentrationCycle Цикл жетонов.
     * @param GameDeliverySignal $delivery Outbox события check.
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
        private readonly GameCheckChat $chat,
        private readonly GameConcentration $concentration,
        private readonly GameConcentrationCycle $concentrationCycle,
        private readonly GameDeliverySignal $delivery,
    ) {
        $this->sessions = new GameSessionRepository($smartTableGateway);
        $this->commands = new GameCommandRepository($smartTableGateway);
        $this->checks = new GameCheckRepository($smartTableGateway);
        $this->npcs = new GameNpcRepository($smartTableGateway);
        $this->members = new GameMemberRepository($smartTableGateway);
        $this->battles = new GameBattleRepository($smartTableGateway);
        $this->roster = new GameSessionRoster(
            new GameSessionRepository($smartTableGateway),
            new GameBattleRepository($smartTableGateway),
        );
        $this->processRows = new GameProcessRepository($smartTableGateway);
        $this->access = new GameStrikeAccess(
            $this->members,
            new GameCharacterRepository($smartTableGateway),
            $this->battles,
            $cardAccess,
        );
        $this->sheetWrites = new GameStrikeSheetWrites($mutations, $this->npcs);
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

        $this->assertSide($ready['game'], $actorUserId, $editAll, $choice['participant']);
        $this->roll->assertRule($ready['game']->getSpaceId(), $ready['game']->getRulesRevision(), $choice['ruleCode'], 'solo', $choice['asked']);
        $sheet = $this->sheet($ready['game']->getId(), $choice['participant']['type'], $choice['participant']['id'], $expectedSheetVersion);
        $result = $this->commitSolo($ready['game'], $battleId, $idempotencyKey, $expectedSheetVersion, $choice, $sheet, $actorUserId);
        $this->announce($ready['game']->getId(), $idempotencyKey);

        return $result;
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

        $this->assertSide($ready['game'], $actorUserId, $editAll, $choice['participant']);
        $this->roll->assertRule($ready['game']->getSpaceId(), $ready['game']->getRulesRevision(), $choice['ruleCode'], 'joint', $choice['asked']);
        $this->assertTarget($ready['game']->getId(), $battleId, $choice['participant'], $choice['target']);
        $this->sheet($ready['game']->getId(), $choice['participant']['type'], $choice['participant']['id'], $expectedSheetVersion);
        $result = $this->commitProposal($ready['game'], $battleId, $idempotencyKey, $expectedSheetVersion, $choice, $actorUserId);
        $this->announce($ready['game']->getId(), $idempotencyKey);

        return $result;
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
            'proposal' => $answer['proposal'] ?? null,
            'expectedSheetVersion' => $expectedSheetVersion,
        ]);
        if ($ready['replay'] !== null) {
            return $ready['replay'];
        }

        $open = $this->stored($ready['game']->getId(), $processId);
        $proposal = is_array($open['proposal']) ? $open['proposal'] : null;
        if ($proposal === null || $open['mode'] !== 'pairwise') {
            throw new GameInvalidException('Game check proposal is invalid');
        }

        $side = $this->answerSide($ready['game'], $actorUserId, $editAll, $proposal, $answer);
        if (($proposal['waitingOn'] ?? null) !== $side) {
            throw new GameInvalidException('Game check is not waiting for this side');
        }

        $proposal = $this->withAnswerProposal($proposal, $answer['proposal'] ?? null, $side, $expectedSheetVersion);
        $open['proposal'] = $proposal;
        $versions = $this->answerVersions($proposal, $side, $expectedSheetVersion);
        $initiatorSheet = $this->sheet(
            $ready['game']->getId(),
            $proposal['initiator']['type'],
            $proposal['initiator']['id'],
            $versions['initiator'],
        );
        $opponentSheet = $this->sheet(
            $ready['game']->getId(),
            $proposal['opponent']['type'],
            $proposal['opponent']['id'],
            $versions['opponent'],
        );

        $result = $this->commitAnswer(
            $ready['game'],
            $processId,
            $idempotencyKey,
            $expectedSheetVersion,
            $decision,
            $open,
            $initiatorSheet,
            $opponentSheet,
            $versions,
            $side,
            $actorUserId,
        );
        $this->announce($ready['game']->getId(), $idempotencyKey);

        return $result;
    }

    /**
     * Сторона, которая отвечает на предложение.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param array<string, mixed> $proposal Предложение.
     * @param array $answer Ответ.
     *
     * @return string initiator или opponent.
     *
     * @throws GameInvalidException Если сторона чужая.
     */
    private function answerSide(
        GameRecord $game,
        int $actorUserId,
        bool $editAll,
        array $proposal,
        array $answer,
    ): string {
        $actor = $answer['actor'] ?? null;
        if (is_array($actor)) {
            $party = $this->party($actor);
            if ($this->sameParty($party, $proposal['initiator']) || $this->sameParty($party, $proposal['opponent'])) {
                $side = $this->sameParty($party, $proposal['initiator']) ? 'initiator' : 'opponent';
                $this->assertSide($game, $actorUserId, $editAll, $party);

                return $side;
            }

            throw new GameInvalidException('Game check actor is invalid');
        }

        $waiting = $proposal['waitingOn'] ?? null;
        if ($waiting === 'initiator') {
            $this->assertSide($game, $actorUserId, $editAll, $proposal['initiator']);

            return 'initiator';
        }

        if ($waiting === 'opponent') {
            $this->assertSide($game, $actorUserId, $editAll, $proposal['opponent']);

            return 'opponent';
        }

        throw new GameInvalidException('Game check waiting side is invalid');
    }

    /**
     * Версии листов обеих сторон для принятия.
     *
     * @param array<string, mixed> $proposal Предложение.
     * @param string $side Отвечающая сторона.
     * @param int $expectedSheetVersion Версия отвечающей стороны.
     *
     * @return array{initiator: int, opponent: int} Версии.
     *
     * @throws GameInvalidException Если версия второй стороны неизвестна.
     */
    private function answerVersions(array $proposal, string $side, int $expectedSheetVersion): array
    {
        $initiator = $side === 'initiator'
            ? $expectedSheetVersion
            : ($proposal['initiatorSheetVersion'] ?? null);
        $opponent = $side === 'opponent'
            ? $expectedSheetVersion
            : ($proposal['opponentSheetVersion'] ?? null);
        if (!is_int($initiator) || !is_int($opponent)) {
            throw new GameInvalidException('Game check sheet version is unknown');
        }

        return ['initiator' => $initiator, 'opponent' => $opponent];
    }

    /**
     * Правит блок своей стороны в предложении.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Ключ.
     * @param int $processId Process.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedSheetVersion Версия листа.
     * @param array $proposal Предложение.
     *
     * @return array<string, mixed> Итог.
     */
    public function reviseCheck(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $processId,
        string $idempotencyKey,
        int $expectedSheetVersion,
        array $proposal,
    ): array {
        $ready = $this->ready($gameId, $actorUserId, $editAll, $viewAll, $idempotencyKey, [
            'processId' => $processId,
            'proposal' => $proposal,
            'expectedSheetVersion' => $expectedSheetVersion,
        ]);
        if ($ready['replay'] !== null) {
            return $ready['replay'];
        }

        $open = $this->stored($ready['game']->getId(), $processId);
        $stored = is_array($open['proposal']) ? $open['proposal'] : null;
        if ($stored === null) {
            throw new GameInvalidException('Game check proposal is invalid');
        }

        $actor = $this->party($proposal['actor'] ?? null);
        $side = $this->sideOf($stored, $actor);
        $this->assertSide($ready['game'], $actorUserId, $editAll, $actor);
        $this->sheet($ready['game']->getId(), $actor['type'], $actor['id'], $expectedSheetVersion);

        $flat = is_array($proposal['proposal'] ?? null) ? $proposal['proposal'] : $proposal;
        $result = $this->smartTableGateway->transaction(function () use (
            $ready,
            $processId,
            $idempotencyKey,
            $expectedSheetVersion,
            $proposal,
            $side,
            $flat,
            $stored,
            $open,
        ): array {
            $blocks = $this->proposalBlocks($flat);
            $stored[$side] = array_merge($stored[$side], $blocks[$side]);
            $stored['waitingOn'] = $side === 'initiator' ? 'opponent' : 'initiator';
            $stored['revision'] = (int) ($stored['revision'] ?? 0) + 1;
            $stored[$side . 'SheetVersion'] = $expectedSheetVersion;
            $this->checks->storeProposal($processId, $stored);

            $result = [
                'processId' => $processId,
                'status' => 'pending',
                'ruleCode' => $open['ruleCode'],
                'proposal' => $this->flatProposal($stored),
                'waitingOn' => $stored['waitingOn'],
                'initiator' => $stored['initiator']['type'] . ':' . $stored['initiator']['id'],
                'opponent' => $stored['opponent']['type'] . ':' . $stored['opponent']['id'],
            ];
            $this->commands->add($ready['game']->getId(), $open['sessionId'], 'check', $idempotencyKey, [
                'processId' => $processId,
                'proposal' => $proposal,
                'expectedSheetVersion' => $expectedSheetVersion,
            ], $result);

            return $result;
        });
        $this->announce($ready['game']->getId(), $idempotencyKey);

        return $result;
    }

    /**
     * Отменяет предложение.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Ключ.
     * @param int $processId Process.
     * @param string $idempotencyKey Ключ.
     * @param array $proposal Тело.
     *
     * @return array<string, mixed> Итог.
     */
    public function cancelCheck(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $processId,
        string $idempotencyKey,
        array $proposal,
    ): array {
        $ready = $this->ready($gameId, $actorUserId, $editAll, $viewAll, $idempotencyKey, [
            'processId' => $processId,
            'cancel' => true,
        ]);
        if ($ready['replay'] !== null) {
            return $ready['replay'];
        }

        $open = $this->stored($ready['game']->getId(), $processId);
        $stored = is_array($open['proposal']) ? $open['proposal'] : null;
        if ($stored === null) {
            throw new GameInvalidException('Game check proposal is invalid');
        }

        $actor = $this->party($proposal['actor'] ?? null);
        $side = $this->sideOf($stored, $actor);
        $this->assertSide($ready['game'], $actorUserId, $editAll, $actor);
        $result = $this->smartTableGateway->transaction(function () use (
            $ready,
            $processId,
            $idempotencyKey,
            $actor,
            $actorUserId,
            $open,
        ): array {
            $this->processes->cancel($processId);
            $this->checks->storeOutcome($processId, 'cancelled', null);
            $this->chat->postCancelled(
                $ready['game'],
                $actorUserId,
                $this->checkThread($processId, $open['battleId'], $open['sessionId']),
                $actor,
                $open['ruleCode'],
            );
            $result = ['processId' => $processId, 'status' => 'cancelled'];
            $this->commands->add($ready['game']->getId(), $open['sessionId'], 'check', $idempotencyKey, [
                'processId' => $processId,
                'cancel' => true,
            ], $result);

            return $result;
        });
        $this->announce($ready['game']->getId(), $idempotencyKey);

        return $result;
    }

    /**
     * Ожидающие предложения игры или участника.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ.
     * @param array{type: string, id: int}|null $entity Участник или null.
     *
     * @return array<int, array<string, mixed>> Предложения.
     */
    public function checkOffers(int $gameId, int $actorUserId, bool $viewAll, ?array $entity = null): array
    {
        $game = $this->games->get($gameId);
        if (!$this->cardAccess->isVisible($game, $actorUserId, $viewAll)) {
            throw new GameNotFoundException('Game was not found');
        }

        $sessionId = $this->sessions->findSessionId($gameId);
        if ($sessionId === null) {
            return [];
        }

        $party = $entity === null ? null : $this->party($entity);
        $offers = [];
        foreach ($this->checks->findPending($sessionId) as $row) {
            $proposal = is_array($row['proposal'] ?? null) ? $row['proposal'] : null;
            $initiator = is_array($proposal['initiator'] ?? null) ? $proposal['initiator'] : null;
            $opponent = is_array($proposal['opponent'] ?? null) ? $proposal['opponent'] : null;
            if ($proposal === null || $initiator === null || $opponent === null) {
                continue;
            }

            if (
                $party !== null
                && !$this->sameParty($party, $initiator)
                && !$this->sameParty($party, $opponent)
            ) {
                continue;
            }

            $offers[] = $this->offerView($gameId, $row, $proposal);
        }

        return $offers;
    }

    /**
     * Предложение в форме CheckOffer.
     *
     * @param int $gameId Игра.
     * @param array<string, mixed> $row Строка.
     * @param array<string, mixed> $proposal Предложение.
     *
     * @return array<string, mixed> Предложение.
     */
    private function offerView(int $gameId, array $row, array $proposal): array
    {
        $initiator = $proposal['initiator'];
        $opponent = $proposal['opponent'];

        return [
            'id' => $row['processId'],
            'gameId' => $gameId,
            'checkCode' => $row['ruleCode'],
            'initiator' => $initiator['type'] . ':' . $initiator['id'],
            'opponent' => $opponent['type'] . ':' . $opponent['id'],
            'proposal' => $this->flatProposal($proposal),
            'waitingOn' => $proposal['waitingOn'] ?? 'opponent',
            'status' => 'pending',
            'updatedAt' => '',
        ];
    }

    /**
     * Плоская форма предложения для фронта.
     *
     * @param array<string, mixed> $proposal Предложение.
     *
     * @return array<string, mixed> Плоская форма.
     */
    private function flatProposal(array $proposal): array
    {
        $initiator = is_array($proposal['initiator'] ?? null) ? $proposal['initiator'] : [];
        $opponent = is_array($proposal['opponent'] ?? null) ? $proposal['opponent'] : [];

        return [
            'initiatorCharacteristic' => $initiator['characteristic'] ?? null,
            'opponentCharacteristic' => $opponent['characteristic'] ?? null,
            'initiatorAdv' => $initiator['advantage'] ?? 0,
            'opponentAdv' => $opponent['advantage'] ?? 0,
            'initiatorFree' => $initiator['free'] ?? null,
            'opponentFree' => $opponent['free'] ?? null,
            'initiatorSpendConcentration' => $initiator['spendConcentration'] ?? 0,
            'opponentSpendConcentration' => $opponent['spendConcentration'] ?? 0,
        ];
    }

    /**
     * Сторона участника в предложении.
     *
     * @param array<string, mixed> $proposal Предложение.
     * @param array{type: string, id: int} $party Участник.
     *
     * @return string initiator или opponent.
     *
     * @throws GameInvalidException Если участника нет в предложении.
     */
    private function sideOf(array $proposal, array $party): string
    {
        if ($this->sameParty($party, $proposal['initiator'])) {
            return 'initiator';
        }

        if ($this->sameParty($party, $proposal['opponent'])) {
            return 'opponent';
        }

        throw new GameInvalidException('Game check actor is invalid');
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
        int $_actorUserId,
        bool $_editAll,
        bool $viewAll,
        string $idempotencyKey,
        array $body,
    ): array {
        $game = $this->games->get($gameId);
        if (!$this->cardAccess->isVisible($game, $_actorUserId, $viewAll)) {
            throw new GameNotFoundException('Game was not found');
        }

        $replay = $this->replay($gameId, $idempotencyKey, $body);
        if ($replay !== null) {
            return ['game' => $game, 'replay' => $replay];
        }

        if ($idempotencyKey === '' || $game->isCompleted() || $this->sessions->findSessionId($gameId) === null) {
            throw new GameInvalidException('Game check is invalid');
        }

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
     * @param int $actorUserId Автор сообщения.
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
        int $actorUserId,
    ): array {
        $result = $this->view(0, 'resolved', null, null);
        $this->smartTableGateway->transaction(function () use (
            $game,
            $battleId,
            $idempotencyKey,
            $expectedSheetVersion,
            $choice,
            $sheet,
            $actorUserId,
            &$result,
        ): void {
            $result = $this->applySolo($game, $battleId, $idempotencyKey, $expectedSheetVersion, $choice, $sheet, $actorUserId);
        });

        return $result;
    }

    /**
     * Трата жетонов и отметка цикла.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $participant Участник.
     * @param array<string, mixed> $sheet Лист.
     * @param int $version Версия листа.
     * @param int|null $battleId Бой или null.
     * @param string $checkCode Карточка проверки.
     * @param int $amount Сколько жетонов.
     * @param string|null $characteristic Характеристика стороны или null.
     *
     * @return array{version: int, battleVersion: int|null, resource: string|null, amount: int} Итог.
     *
     * @throws GameInvalidException Если жетон недоступен.
     */
    private function spendConcentration(
        GameRecord $game,
        array $participant,
        array $sheet,
        int $version,
        ?int $battleId,
        string $checkCode,
        int $amount,
        ?string $characteristic = null,
    ): array {
        if ($amount < 1) {
            return ['version' => $version, 'battleVersion' => null, 'resource' => null, 'amount' => 0];
        }

        $slice = $this->roll->slice($game->getSpaceId(), $game->getRulesRevision());
        $resource = $this->concentration->resourceCode($slice, $sheet);
        if ($resource === null) {
            throw new GameInvalidException('Game concentration resource is missing');
        }

        $levels = is_array($sheet['abilityLevels'] ?? null) ? $sheet['abilityLevels'] : [];
        $characteristic ??= $this->characteristicOf($slice, $checkCode);
        if (!$this->concentration->allows($slice, $checkCode, $characteristic, $levels)) {
            throw new GameInvalidException('Game concentration is not allowed');
        }

        $this->concentration->assertAmount($slice, $sheet, $levels, $resource, $amount);
        $battle = $battleId === null ? null : $this->battles->find($battleId);
        $actorKey = $participant['type'] . ':' . $participant['id'];
        if (
            $battle !== null
            && !$this->concentrationCycle->canSpend($battle->getConcentrationCycle(), $actorKey)
        ) {
            throw new GameInvalidException('Game concentration is blocked');
        }

        $written = $this->sheetWrites->writeResources(
            $game,
            $participant['type'],
            $participant['id'],
            $version,
            [new ResourceSpend($resource, new ScalarResourceValue($amount))],
        );
        if ($battle === null) {
            return ['version' => $written, 'battleVersion' => null, 'resource' => $resource, 'amount' => $amount];
        }

        $cycle = $this->concentrationCycle->markUsed($battle->getConcentrationCycle(), $actorKey);
        $saved = $this->battles->replaceTracked(
            $battle->getId(),
            $battle->getStateVersion(),
            null,
            null,
            null,
            null,
            $cycle,
        );

        return [
            'version' => $written,
            'battleVersion' => $saved->getStateVersion(),
            'resource' => $resource,
            'amount' => $amount,
        ];
    }

    /**
     * Характеристика проверки.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $checkCode Карточка.
     *
     * @return string|null Код.
     */
    private function characteristicOf(CharacterRuleSlice $slice, string $checkCode): ?string
    {
        $rule = $slice->findLive($checkCode);
        $spec = $rule?->getSpec();

        return $spec instanceof CheckSpec ? $spec->getCharacteristicCode() : null;
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
     * @param int $actorUserId Автор сообщения.
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
        int $actorUserId,
    ): array {
        $opened = $this->processes->open($game->getId(), $battleId, $choice['participant']['type'], $choice['participant']['id']);
        $spent = $this->spendConcentration(
            $game,
            $choice['participant'],
            $sheet,
            $expectedSheetVersion,
            $battleId,
            $choice['ruleCode'],
            is_int($choice['concentration'] ?? null) ? $choice['concentration'] : 0,
        );
        $override = $spent['amount'] > 0
            ? new GameCheckThrowOverride(null, null, $spent['amount'], null, null, $spent['resource'])
            : null;
        $thrown = $this->roll->throwCheck(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['ruleCode'],
            'solo',
            $sheet,
            $choice['asked'],
            $override,
        );
        $thrown['actorKey'] = $choice['participant']['type'] . ':' . $choice['participant']['id'];
        $written = $this->writeSheet($game, $choice['participant']['type'], $choice['participant']['id'], $spent['version']);
        $sheetVersion = $spent['resource'] === null
            ? $written
            : ($written ?? $spent['version']);
        $this->processes->resolve($opened['processId']);
        $result = $this->view($opened['processId'], 'resolved', $thrown, $sheetVersion);
        if ($spent['battleVersion'] !== null) {
            $result['battleVersion'] = $spent['battleVersion'];
        }

        $this->checks->add($this->row($opened, 'solo', $choice, 'accepted', $thrown));
        $this->commands->add($game->getId(), $opened['sessionId'], 'check', $idempotencyKey, [
            'battleId' => $battleId,
            'check' => $choice,
            'expectedSheetVersion' => $expectedSheetVersion,
        ], $result);
        $this->chat->postSolo(
            $game,
            $actorUserId,
            $this->checkThread($opened['processId'], $battleId, $opened['sessionId']),
            $choice['participant'],
            $choice['ruleCode'],
            $thrown,
            $spent['amount'],
        );

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
     * @param int $actorUserId Автор сообщения.
     *
     * @return array<string, mixed> Итог.
     */
    private function commitProposal(
        GameRecord $game,
        ?int $battleId,
        string $idempotencyKey,
        int $expectedSheetVersion,
        array $choice,
        int $actorUserId,
    ): array {
        $result = $this->view(0, 'pending', null, null);
        $this->smartTableGateway->transaction(function () use (
            $game,
            $battleId,
            $idempotencyKey,
            $expectedSheetVersion,
            $choice,
            $actorUserId,
            &$result,
        ): void {
            $opened = $this->processes->open($game->getId(), $battleId, $choice['participant']['type'], $choice['participant']['id']);
            $proposal = is_array($choice['proposal'] ?? null) ? $choice['proposal'] : [];
            $proposal['initiator']['type'] = $choice['participant']['type'];
            $proposal['initiator']['id'] = $choice['participant']['id'];
            $proposal['opponent']['type'] = $choice['target']['type'];
            $proposal['opponent']['id'] = $choice['target']['id'];
            $proposal['waitingOn'] = 'opponent';
            $proposal['revision'] = 0;
            $proposal['initiatorSheetVersion'] = $expectedSheetVersion;
            $proposal['opponentSheetVersion'] = null;
            $choice['proposal'] = $proposal;
            $result = $this->view($opened['processId'], 'pending', null, null);
            $result['ruleCode'] = $choice['ruleCode'];
            $result['target'] = $choice['target'];
            $result['proposal'] = $this->flatProposal($proposal);
            $result['waitingOn'] = 'opponent';
            $result['initiator'] = $choice['participant']['type'] . ':' . $choice['participant']['id'];
            $result['opponent'] = $choice['target']['type'] . ':' . $choice['target']['id'];
            $this->checks->add($this->row($opened, 'pairwise', $choice, 'pending', null));
            $this->commands->add($game->getId(), $opened['sessionId'], 'check', $idempotencyKey, [
                'battleId' => $battleId,
                'proposal' => $choice,
                'expectedSheetVersion' => $expectedSheetVersion,
            ], $result);
            $this->chat->postProposal(
                $game,
                $actorUserId,
                $this->checkThread($opened['processId'], $battleId, $opened['sessionId']),
                $choice['participant'],
                $choice['target'],
                $choice['ruleCode'],
                is_int($proposal['initiator']['spendConcentration'] ?? null)
                    ? $proposal['initiator']['spendConcentration']
                    : 0,
            );
        });

        return $result;
    }

    /**
     * Ответ в одной транзакции.
     *
     * @param GameRecord $game Игра.
     * @param int $processId Process.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedSheetVersion Версия отвечающей стороны.
     * @param string $decision accept или decline.
     * @param array $open Сохранённое предложение.
     * @param array<string, mixed> $initiatorSheet Лист инициатора.
     * @param array<string, mixed> $opponentSheet Лист цели.
     * @param array{initiator: int, opponent: int} $versions Версии сторон.
     * @param string $side Отвечающая сторона.
     * @param int $actorUserId Автор сообщения.
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
        array $initiatorSheet,
        array $opponentSheet,
        array $versions,
        string $side,
        int $actorUserId,
    ): array {
        $result = $this->view($processId, 'cancelled', null, null);
        $this->smartTableGateway->transaction(function () use (
            $game,
            $processId,
            $idempotencyKey,
            $expectedSheetVersion,
            $decision,
            $open,
            $initiatorSheet,
            $opponentSheet,
            $versions,
            $side,
            $actorUserId,
            &$result,
        ): void {
            $result = $this->applyAnswer(
                $game,
                $processId,
                $decision,
                $open,
                $initiatorSheet,
                $opponentSheet,
                $versions,
                $side,
                $actorUserId,
            );
            $this->commands->add($game->getId(), $open['sessionId'], 'check', $idempotencyKey, [
                'processId' => $processId,
                'answer' => $decision,
                'side' => $side,
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
     * @param array<string, mixed> $initiatorSheet Лист инициатора.
     * @param array<string, mixed> $opponentSheet Лист цели.
     * @param array{initiator: int, opponent: int} $versions Версии сторон.
     * @param string $side Отвечающая сторона.
     * @param int $actorUserId Автор сообщения.
     *
     * @return array<string, mixed> Итог.
     */
    private function applyAnswer(
        GameRecord $game,
        int $processId,
        string $decision,
        array $open,
        array $initiatorSheet,
        array $opponentSheet,
        array $versions,
        string $side,
        int $actorUserId,
    ): array {
        $proposal = is_array($open['proposal']) ? $open['proposal'] : null;
        if ($proposal === null) {
            throw new GameInvalidException('Game check proposal is invalid');
        }

        if ($decision !== 'accept') {
            $this->processes->cancel($processId);
            $this->checks->storeOutcome($processId, 'declined', null);
            $this->chat->postDeclined(
                $game,
                $actorUserId,
                $this->checkThread($processId, $open['battleId'], $open['sessionId']),
                $proposal[$side],
                $open['ruleCode'],
            );

            return $this->view($processId, 'cancelled', null, null);
        }

        $initiatorSpent = $this->spendConcentration(
            $game,
            $proposal['initiator'],
            $initiatorSheet,
            $versions['initiator'],
            $open['battleId'],
            $open['ruleCode'],
            is_int($proposal['initiator']['spendConcentration'] ?? null)
                ? $proposal['initiator']['spendConcentration']
                : 0,
            is_string($proposal['initiator']['characteristic'] ?? null)
                ? $proposal['initiator']['characteristic']
                : null,
        );
        $opponentSpent = $this->spendConcentration(
            $game,
            $proposal['opponent'],
            $opponentSheet,
            $versions['opponent'],
            $open['battleId'],
            $open['ruleCode'],
            is_int($proposal['opponent']['spendConcentration'] ?? null)
                ? $proposal['opponent']['spendConcentration']
                : 0,
            is_string($proposal['opponent']['characteristic'] ?? null)
                ? $proposal['opponent']['characteristic']
                : null,
        );
        $versions['initiator'] = $initiatorSpent['version'];
        $versions['opponent'] = $opponentSpent['version'];
        $rolls = $this->roll->throwJointCheck(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $open['ruleCode'],
            $initiatorSheet,
            $this->throwOverride($proposal['initiator'], $initiatorSpent['amount'], $initiatorSpent['resource']),
            $opponentSheet,
            $this->throwOverride($proposal['opponent'], $opponentSpent['amount'], $opponentSpent['resource']),
        );
        $rolls['initiator']['actorKey'] = $proposal['initiator']['type'] . ':' . $proposal['initiator']['id'];
        $rolls['opponent']['actorKey'] = $proposal['opponent']['type'] . ':' . $proposal['opponent']['id'];
        $this->writeSheet($game, $proposal['initiator']['type'], $proposal['initiator']['id'], $versions['initiator']);
        $this->writeSheet($game, $proposal['opponent']['type'], $proposal['opponent']['id'], $versions['opponent']);
        $this->processes->resolve($processId);
        $this->checks->storeOutcome($processId, 'accepted', $rolls['initiator']);
        $this->checks->storeRolls($processId, $rolls);
        $result = $this->view($processId, 'resolved', $rolls['initiator'], $versions['initiator']);
        $result['rolls'] = $rolls;
        $result['proposal'] = $this->flatProposal($proposal);
        $result['status'] = 'resolved';
        $result['initiator'] = $proposal['initiator']['type'] . ':' . $proposal['initiator']['id'];
        $result['opponent'] = $proposal['opponent']['type'] . ':' . $proposal['opponent']['id'];
        $result['sheetVersions'] = $versions;
        $this->chat->postResolution(
            $game,
            $actorUserId,
            $this->checkThread($processId, $open['battleId'], $open['sessionId']),
            $proposal,
            $open['ruleCode'],
            $rolls,
            $initiatorSpent['amount'],
            $opponentSpent['amount'],
        );

        return $result;
    }

    /**
     * Смена пула из блока предложения.
     *
     * @param array<string, mixed> $block Блок стороны.
     *
     * @return GameCheckThrowOverride Смена.
     */
    private function throwOverride(
        array $block,
        int $tokenAdvantage = 0,
        ?string $tokenSource = null,
    ): GameCheckThrowOverride {
        $advantage = (is_int($block['advantage'] ?? null) ? $block['advantage'] : 0) + $tokenAdvantage;
        $source = $tokenAdvantage > 0 ? $tokenSource : null;
        $free = is_array($block['free'] ?? null) ? $block['free'] : null;
        if ($free !== null) {
            return new GameCheckThrowOverride(
                null,
                $free['diceCount'],
                $advantage,
                $free['dieSize'],
                $free['efficiency'],
                $source,
            );
        }

        return new GameCheckThrowOverride(
            is_string($block['characteristic'] ?? null) ? $block['characteristic'] : null,
            null,
            $advantage,
            null,
            null,
            $source,
        );
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
     * @return array{sessionId: int, battleId: int|null, participantType: string, participantId: int, ruleCode: string, asked: array{base: int, size: int}|null, targetType: string|null, targetId: int|null, proposal: array<string, mixed>|null, rolls: array<string, mixed>|null, mode: string, offer: string} Пара.
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
            'battleId' => $row->getBattleId(),
            'participantType' => $row->getParticipantType(),
            'participantId' => $row->getParticipantId(),
            'ruleCode' => $check['ruleCode'],
            'asked' => $check['asked'],
            'targetType' => $check['targetType'],
            'targetId' => $check['targetId'],
            'proposal' => $check['proposal'] ?? null,
            'rolls' => $check['rolls'] ?? null,
            'mode' => $check['mode'] ?? 'solo',
            'offer' => $check['offer'] ?? 'accepted',
        ];
    }

    /**
     * Право действовать за сторону: писатель или владелец активного персонажа.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param array{type: string, id: int} $side Сторона.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     */
    private function assertSide(GameRecord $game, int $actorUserId, bool $editAll, array $side): void
    {
        if ($this->access->canAnswer($game, $actorUserId, $editAll, $side)) {
            return;
        }

        throw new ActionException('AUTH_DENIED', 'Permission denied');
    }

    /**
     * Решение соло или предложения.
     *
     * @param array $body Тело.
     * @param bool $withTarget Нужна цель.
     *
     * @return array{participant: array{type: string, id: int}, ruleCode: string, asked: array{base: int, size: int}|null, target: array{type: string, id: int}|null, proposal: array<string, mixed>|null} Выбор.
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

        $participant = $this->party($body['initiator'] ?? $body['participant'] ?? null);
        $ruleCode = $body['ruleCode'] ?? $body['checkCode'] ?? null;
        if (!is_string($ruleCode) || $ruleCode === '') {
            throw new GameInvalidException('Game check rule is invalid');
        }

        return [
            'participant' => $participant,
            'ruleCode' => $ruleCode,
            'asked' => $this->asked($body['askedDifficulty'] ?? null),
            'target' => $withTarget ? $this->party($body['opponent'] ?? $body['target'] ?? null) : null,
            'proposal' => $withTarget ? $this->proposalBlocks($body) : null,
            'concentration' => $withTarget
                ? 0
                : $this->concentrationAmount($body['concentration'] ?? $body['spendConcentration'] ?? 0),
        ];
    }

    /**
     * Принятие с правкой своей стороны: блок ждущей стороны заменяется.
     *
     * @param array<string, mixed> $proposal Сохранённое предложение.
     * @param mixed $flat Плоское предложение из ответа или null.
     * @param string $side initiator или opponent.
     * @param int $expectedSheetVersion Версия листа отвечающей стороны.
     *
     * @return array<string, mixed> Предложение.
     *
     * @throws GameInvalidException Если блок чужой.
     */
    private function withAnswerProposal(array $proposal, mixed $flat, string $side, int $expectedSheetVersion): array
    {
        if (!is_array($flat)) {
            return $proposal;
        }

        $keys = [
            'initiatorCharacteristic',
            'opponentCharacteristic',
            'initiatorAdv',
            'opponentAdv',
            'initiatorFree',
            'opponentFree',
            'initiatorSpendConcentration',
            'opponentSpendConcentration',
        ];
        $hasCheckFields = false;
        foreach ($keys as $key) {
            if (array_key_exists($key, $flat)) {
                $hasCheckFields = true;
                break;
            }
        }

        if (!$hasCheckFields) {
            return $proposal;
        }

        $blocks = $this->proposalBlocks($flat);
        $proposal[$side] = array_merge($proposal[$side], $blocks[$side]);
        $proposal[$side . 'SheetVersion'] = $expectedSheetVersion;

        return $proposal;
    }

    /**
     * Блоки предложения: характеристики, преимущества, свободные пулы, жетоны.
     *
     * @param array $body Тело.
     *
     * @return array{initiator: array<string, mixed>, opponent: array<string, mixed>} Блоки.
     *
     * @throws GameInvalidException Если поле чужое.
     */
    private function proposalBlocks(array $body): array
    {
        return [
            'initiator' => [
                'characteristic' => $this->optionalString($body['initiatorCharacteristic'] ?? null),
                'advantage' => $this->optionalInt($body['initiatorAdv'] ?? 0, 'Game check advantage is invalid'),
                'free' => $this->freePool($body['initiatorFree'] ?? null),
                'spendConcentration' => $this->concentrationAmount($body['initiatorSpendConcentration'] ?? 0),
            ],
            'opponent' => [
                'characteristic' => $this->optionalString($body['opponentCharacteristic'] ?? null),
                'advantage' => $this->optionalInt($body['opponentAdv'] ?? 0, 'Game check advantage is invalid'),
                'free' => $this->freePool($body['opponentFree'] ?? null),
                'spendConcentration' => $this->concentrationAmount($body['opponentSpendConcentration'] ?? 0),
            ],
        ];
    }

    /**
     * Количество жетонов не меньше нуля.
     *
     * @param mixed $value Значение.
     *
     * @return int Количество.
     *
     * @throws GameInvalidException Если число отрицательное.
     */
    private function concentrationAmount(mixed $value): int
    {
        if (!is_int($value) || $value < 0) {
            throw new GameInvalidException('Game check concentration is invalid');
        }

        return $value;
    }

    /**
     * Строка или null.
     *
     * @param mixed $value Значение.
     *
     * @return string|null Строка.
     *
     * @throws GameInvalidException Если не строка и не null.
     */
    private function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) || $value === '') {
            throw new GameInvalidException('Game check characteristic is invalid');
        }

        return $value;
    }

    /**
     * Целое или значение по умолчанию.
     *
     * @param mixed $value Значение.
     * @param string $error Текст ошибки.
     *
     * @return int Целое.
     *
     * @throws GameInvalidException Если не целое.
     */
    private function optionalInt(mixed $value, string $error): int
    {
        if (!is_int($value)) {
            throw new GameInvalidException($error);
        }

        return $value;
    }

    /**
     * Свободный пул или null.
     *
     * @param mixed $value Значение.
     *
     * @return array{diceCount: int, dieSize: int, efficiency: int}|null Пул.
     *
     * @throws GameInvalidException Если поле чужое.
     */
    private function freePool(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw new GameInvalidException('Game check free pool is invalid');
        }

        $diceCount = $value['diceCount'] ?? null;
        $dieSize = $value['dieSize'] ?? null;
        $efficiency = $value['efficiency'] ?? null;
        if (!is_int($diceCount) || $diceCount < 1 || !is_int($dieSize) || !is_int($efficiency)) {
            throw new GameInvalidException('Game check free pool is invalid');
        }

        return ['diceCount' => $diceCount, 'dieSize' => $dieSize, 'efficiency' => $efficiency];
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
        $result = [
            'processId' => $processId,
            'status' => $status,
            'difficulty' => $thrown['difficulty'] ?? null,
            'roll' => $thrown['roll'] ?? null,
            'success' => $thrown['success'] ?? null,
            'rating' => $thrown['rating'] ?? null,
            'sheetVersion' => $sheetVersion,
        ];
        $keys = [
            'label',
            'actorKey',
            'faces',
            'adjustedRolls',
            'successes',
            'droppedRolls',
            'dieFaces',
            'advantages',
            'min_success_size',
            'masteryAdjustments',
            'efficiencyAdjustments',
            'efficiency',
            'poolSize',
            'process_bonus',
        ];
        foreach ($keys as $key) {
            if (is_array($thrown) && array_key_exists($key, $thrown)) {
                $result[$key] = $thrown[$key];
            }
        }

        return $result;
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

        if (is_array($choice['proposal'] ?? null)) {
            $fields['proposal'] = $choice['proposal'];
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
     * Тред проверки: ход боя или сессия.
     *
     * @param int $processId Process.
     * @param int|null $battleId Бой или null.
     * @param int $sessionId Сессия.
     *
     * @return array{id: string, parentId: string, kind: string} Тред.
     */
    private function checkThread(int $processId, ?int $battleId, int $sessionId): array
    {
        $battle = $battleId === null ? null : $this->battles->find($battleId);
        if ($battle === null) {
            $ids = $this->battles->findIdsBySession($sessionId);
            $battle = $ids === [] ? null : $this->battles->find($ids[array_key_last($ids)]);
        }

        return GameCombatThread::buildCheck($processId, $battle, $sessionId);
    }

    /**
     * Пишет событие check в outbox после коммита. Ошибку транспорта глотает сигнал.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ команды журнала.
     *
     * @return void
     */
    private function announce(int $gameId, string $idempotencyKey): void
    {
        $stored = $this->commands->findByKey($gameId, 'check', $idempotencyKey);
        if ($stored !== null) {
            $this->delivery->recorded($gameId, 'check', $stored['id'], []);
        }
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
        $stored = $this->commands->findByKey($gameId, 'check', $idempotencyKey);
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
