<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Event\Interface\Service\IEventManager;
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
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * Тонкий адаптер широкого удара. Расчёт и запись — в общем контуре.
 */
final class GameWideStrikes implements IGameWideStrikes
{
    private readonly GameBattleRepository $battles;

    private readonly GameActionRepository $actions;

    private readonly GameStrikeAccess $access;

    private readonly GameCommandRepository $commands;

    private readonly GameNpcRepository $npcs;

    private readonly GameCharacterRepository $characters;

    private readonly GameMemberRepository $members;

    private readonly GameStrikeBody $body;

    private readonly GameStrikeClose $closeService;

    private readonly GameStrikeOpen $openService;

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
     * @param IEventManager $events Доставка.
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
        ICharacterActualMutations $mutations,
        private readonly GameStrikeRules $rules,
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
        $this->access = new GameStrikeAccess($this->members, $this->characters, $this->battles, $this->cardAccess);
        $this->body = new GameStrikeBody();
        $this->replayTransaction = new GameReplayTransaction($smartTableGateway);

        $sheetWrites = new GameStrikeSheetWrites($mutations, $this->npcs);
        $conflictProjection = new ConflictSheetProjection(
            new CharacterSectionMask(),
            new GameCharacterProjectionMask(),
            new GameNpcVisibility(),
        );
        $sheets = new GameStrikeSheets(
            $rules,
            $this->npcs,
            $this->characters,
            $this->members,
            $conflictProjection,
        );
        $amounts = new GameStrikeAmounts();
        $spend = new GameStrikeSpend($rules, $sheetWrites);
        $this->closeService = new GameStrikeClose(
            $rules,
            $this->battles,
            $this->actions,
            $this->commands,
            $this->access,
            $sheets,
            $sheetWrites,
            new GameStrikeResolution($rules, $sheets, $this->npcs, $consequences, $amounts),
            $spend,
            new GameStrikeChat($this->npcs, $rules),
            $consequences,
            $amounts,
            $this->replayTransaction,
        );
        $this->openService = new GameStrikeOpen(
            $this->battles,
            $this->actions,
            $spend,
            $rules,
            $sheets,
            $sheetWrites,
        );
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
        $choice = $this->body->wideAttack($attack);
        $ready = $this->ready($gameId, $actorUserId, $viewAll, $idempotencyKey, [
            'attack' => $choice,
            'battleId' => $battleId,
            'expectedVersion' => $expectedBattleVersion,
        ]);
        if ($ready['replay'] !== null) {
            return $ready['replay'];
        }

        $this->access->assertAttacker($ready['game'], $actorUserId, $editAll, $choice['attacker']);

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
     * Принимает ответы за цели; последний необходимый ответ закрывает удар.
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
        $choices = $this->body->wideDefense($defense);
        $ready = $this->ready($gameId, $actorUserId, $viewAll, $idempotencyKey, [
            'battleId' => $battleId,
            'defense' => $choices,
            'expectedVersion' => $expectedBattleVersion,
        ]);
        if ($ready['replay'] !== null) {
            return $ready['replay'];
        }

        $battle = $this->battleOf($ready['sessionId'], $battleId, $expectedBattleVersion);
        $result = $this->closeService->closeWide(
            $ready['game'],
            $ready['sessionId'],
            $battle,
            $idempotencyKey,
            $expectedBattleVersion,
            $choices,
            $actorUserId,
            $editAll,
            $viewAll,
        );
        $this->announce($ready['game']->getId(), $idempotencyKey);

        return $result;
    }

    /**
     * Видимость, повтор и сессия.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $requestBody Тело.
     *
     * @return array{game: GameRecord, sessionId: int, replay: array<string, mixed>|null} Ход.
     */
    private function ready(
        int $gameId,
        int $actorUserId,
        bool $viewAll,
        string $idempotencyKey,
        array $requestBody,
    ): array {
        $game = $this->games->get($gameId);
        $this->access->assertVisible($game, $actorUserId, $viewAll);

        $replay = $this->replayTransaction->find(
            fn (): ?array => $this->commands->findByKey($gameId, 'wide_strike', $idempotencyKey),
            $requestBody,
        );
        if ($replay !== null) {
            return ['game' => $game, 'sessionId' => 0, 'replay' => $replay];
        }

        if ($idempotencyKey === '' || $game->getStatus() === 'completed') {
            throw new GameInvalidException('Game wide strike is invalid');
        }

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
        $this->assertOpening($game, $battleId, $choice);
        $body = [
            'attack' => $choice,
            'battleId' => $battleId,
            'expectedVersion' => $expectedBattleVersion,
        ];
        $execution = $this->replayTransaction->execute(
            fn (): ?array => $this->commands->findByKey($game->getId(), 'wide_strike', $idempotencyKey),
            fn (): int => $this->commands->reserve(
                $game->getId(),
                $sessionId,
                'wide_strike',
                $idempotencyKey,
                $body,
            ),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            fn (): array => $this->openService->openAction(
                $game,
                $sessionId,
                $battle,
                $expectedBattleVersion,
                $choice,
                'wide_strike',
                '1-n',
                'Game wide strike',
            ),
            $body,
        );
        $this->announce($game->getId(), $idempotencyKey);

        return $execution['result'];
    }

    /**
     * Проверяет открытие широкого удара.
     *
     * @param GameRecord $game Игра.
     * @param int $battleId Бой.
     * @param array $choice Выбор.
     *
     * @return void
     */
    private function assertOpening(GameRecord $game, int $battleId, array $choice): void
    {
        $this->access->assertRoster($battleId, $choice['attacker'], $choice['targets']);
        if ($this->actions->findOpenAny($battleId) !== null) {
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
            $choice['instanceIndex'] ?? 0,
        );
        $this->rules->assertAttackTargets(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['actionRuleCode'],
            count($choice['targets']),
        );
        $this->availability->assertAvailable(
            $game,
            $choice['attacker']['type'],
            $choice['attacker']['id'],
            $choice['actionRuleCode'],
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
                ? $this->attackerChoices($game->getId(), $choice['attacker']['id'])
                : null,
        );
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
        $stored = $this->commands->findByKey($gameId, 'wide_strike', $idempotencyKey);
        if ($stored === null) {
            return;
        }

        $this->deliverySignal->recorded($gameId, 'strike', $stored['id'], []);
    }

    /**
     * Снимок choices атакующего NPC.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return array<string, mixed> Документ choices.
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
     * Бой этой сессии с ожидаемой версией.
     *
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param int $expectedBattleVersion Версия.
     *
     * @return GameBattleRecord Строка.
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
