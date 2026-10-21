<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Closure;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameWideStrikeRepository;
use Mifrial\Roleplay\Game\Repository\GameWideStrikeTargetRepository;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Атомарная запись открытия и закрытия wide strike с replay.
 */
final class GameWideStrikeCommit
{
    /**
     * Создаёт writer transaction.
     *
     * @param GameBattleRepository $battles Бои.
     * @param GameWideStrikeRepository $strikes Удары.
     * @param GameWideStrikeTargetRepository $targets Цели.
     * @param GameCommandRepository $commands Команды.
     * @param GameWideStrikeResults $results Результаты целей.
     * @param GameReplayTransaction $replayTransaction Общая граница replay.
     * @param GameStrikeRules $rules Срез.
     * @param GameNpcRepository $npcs NPC.
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     *
     * @return void
     */
    public function __construct(
        private readonly GameBattleRepository $battles,
        private readonly GameWideStrikeRepository $strikes,
        private readonly GameWideStrikeTargetRepository $targets,
        private readonly GameCommandRepository $commands,
        private readonly GameWideStrikeResults $results,
        private readonly GameReplayTransaction $replayTransaction,
        private readonly GameStrikeRules $rules,
        private readonly GameNpcRepository $npcs,
        private readonly GameStrikeSheetWrites $sheetWrites,
    ) {
    }

    /**
     * Карта ожидания после списания удара.
     *
     * @var array<string, mixed>|null
     */
    private ?array $strikePending = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $debtHold = null;

    /**
     * Коммитит открытие wide strike.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Ожидаемая версия.
     * @param array<string, mixed> $choice Выбор.
     * @param GameWideStrikeOpening $opening Построитель строк.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если поля удара невалидны.
     * @throws GameNotFoundException Если сессия не найдена.
     * @throws GameBattleConflictException Если версия боя другая.
     */
    public function open(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $choice,
        GameWideStrikeOpening $opening,
    ): array {
        $body = $this->openBody($battle, $choice, $expectedBattleVersion);

        return $this->executeOpenReplay(
            $game,
            $sessionId,
            $idempotencyKey,
            $body,
            $battle,
            $expectedBattleVersion,
            $choice,
            $opening,
        )['result'];
    }

    /**
     * Коммитит закрытие wide strike.
     *
     * @param array<string, mixed> $context Контекст закрытия wide.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если поле или результат невалидны.
     * @throws GameNotFoundException Если цель не найдена.
     * @throws GameBattleConflictException Если версия боя или цели другая.
     */
    public function close(array $context): array
    {
        $context['body'] = $this->closeBody(
            $context['battle'],
            $context['requestChoices'] ?? $context['choices'],
            $context['expectedBattleVersion'],
        );

        return $this->executeCloseReplay($context);
    }

    /**
     * Принимает ответы целей через общую replay boundary.
     *
     * @param array<string, mixed> $context Контекст ответа.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если поле или результат невалидны.
     * @throws GameNotFoundException Если цель не найдена.
     * @throws GameBattleConflictException Если версия боя или цели другая.
     */
    public function respond(array $context): array
    {
        $work = $context['work'] ?? null;
        unset($context['work']);
        if (!$work instanceof Closure) {
            throw new GameInvalidException('Game wide strike response is invalid');
        }

        $context['body'] = $this->closeBody(
            $context['battle'],
            $context['requestChoices'] ?? [],
            $context['expectedBattleVersion'],
        );

        return $this->replayTransaction->execute(
            fn (): ?array => $this->commands->findByKey(
                $context['game']->getId(),
                'wide_strike',
                $context['idempotencyKey'],
            ),
            fn (): int => $this->commands->reserve(
                $context['game']->getId(),
                $context['sessionId'],
                'wide_strike',
                $context['idempotencyKey'],
                $context['body'],
            ),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            fn (): array => $work($context),
            $context['body'],
        )['result'];
    }

    /**
     * Закрывает wide внутри уже открытой транзакции ответа.
     *
     * @param array<string, mixed> $context Контекст закрытия.
     *
     * @return array<string, mixed> Итог.
     */
    public function closeInTransaction(array $context): array
    {
        return $this->executeClose($context);
    }

    /**
     * Выполняет закрытие wide через общую replay boundary.
     *
     * @param array<string, mixed> $context Контекст закрытия wide.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если поле или результат невалидны.
     * @throws GameNotFoundException Если цель не найдена.
     * @throws GameBattleConflictException Если версия боя или цели другая.
     */
    private function executeCloseReplay(
        array $context,
    ): array {
        return $this->replayTransaction->execute(
            fn (): ?array => $this->commands->findByKey($context['game']->getId(), 'wide_strike', $context['idempotencyKey']),
            fn (): int => $this->commands->reserve(
                $context['game']->getId(),
                $context['sessionId'],
                'wide_strike',
                $context['idempotencyKey'],
                $context['body'],
            ),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            fn (): array => $this->executeClose($context),
            $context['body'],
        )['result'];
    }

    /**
     * Выполняет запись открытия внутри transaction.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattleVersion Ожидаемая версия.
     * @param array<string, mixed> $choice Выбор.
     * @param GameWideStrikeOpening $opening Построитель строк.
     *
     * @return array<string, mixed> Итог.
     */
    private function executeOpen(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        int $expectedBattleVersion,
        array $choice,
        GameWideStrikeOpening $opening,
    ): array {
        $this->debtHold = null;
        $this->spendAttacker($game, $choice, $battle);
        $result = [
            'battleId' => $battle->getId(),
            'strikeId' => 0,
            'version' => $expectedBattleVersion + 1,
            'targetResults' => [],
        ];
        if ($this->debtHold !== null) {
            $saved = $this->battles->replaceTracked(
                $battle->getId(),
                $expectedBattleVersion,
                $this->strikePending ?? $battle->getPendingActionEffects(),
                $this->debtHold,
                null,
                null,
            );
            $result['version'] = $saved->getStateVersion();
            $result['debt'] = true;

            return $result;
        }

        $result['strikeId'] = $this->strikes->add($opening->row($battle, $sessionId, $choice));
        $opening->addTargets($result['strikeId'], $choice['targets']);
        $this->battles->replacePending(
            $battle->getId(),
            $expectedBattleVersion,
            $this->strikePending ?? $battle->getPendingActionEffects(),
        );

        return $result;
    }

    /**
     * Открывает широкий удар, когда F1 уже уплачены.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattleVersion Версия.
     * @param array<string, mixed> $choice Тело.
     * @param GameWideStrikeOpening $opening Строки.
     *
     * @return array<string, mixed> Итог.
     */
    public function openPaid(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        int $expectedBattleVersion,
        array $choice,
        GameWideStrikeOpening $opening,
    ): array {
        $kind = $choice['attacker']['type'] ?? null;
        $id = $choice['attacker']['id'] ?? null;
        if (!is_string($kind) || !is_int($id)) {
            throw new GameInvalidException('Game wide strike attacker is invalid');
        }

        $snapshot = $kind === 'character'
            ? $this->rules->characterSnapshot($id)
            : $this->npcSnapshot($game->getId(), $id);
        $inventorySpend = $this->rules->resolveInventorySpend(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['itemRuleCode'],
            $choice['profileType'],
            $choice['profileIndex'],
            $choice['itemInventoryId'],
            $choice['ammunitionInventoryId'] ?? null,
            $kind,
            $id,
            $kind === 'npc' ? $snapshot['choices'] : null,
        );
        if ($inventorySpend !== null) {
            $this->sheetWrites->write($game, $kind, $id, $snapshot['actualVersion'], [], null, null, [], $inventorySpend);
        }

        $strikeId = $this->strikes->add($opening->row($battle, $sessionId, $choice));
        $opening->addTargets($strikeId, $choice['targets']);
        $debt = $battle->getActionDebt();
        unset($debt[(new GameActionDebt())->key($kind, $id)]);
        $saved = $this->battles->replaceTracked(
            $battle->getId(),
            $expectedBattleVersion,
            $battle->getPendingActionEffects(),
            $debt,
            null,
            null,
        );

        return ['battleId' => $battle->getId(), 'strikeId' => $strikeId, 'version' => $saved->getStateVersion()];
    }

    /**
     * Списывает стоимость wide declaration один раз с атакующего.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     *
     * @return void
     *
     * @throws GameInvalidException Если ресурс или атакующий невалидны.
     * @throws GameNotFoundException Если revision или NPC отсутствует.
     * @throws GameBattleConflictException Если CAS атакующего устарел.
     */
    private function spendAttacker(GameRecord $game, array $choice, GameBattleRecord $battle): void
    {
        $kind = $choice['attacker']['type'] ?? null;
        $id = $choice['attacker']['id'] ?? null;
        if (!is_string($kind) || !is_int($id)) {
            throw new GameInvalidException('Game wide strike attacker is invalid');
        }

        $snapshot = $kind === 'character'
            ? $this->rules->characterSnapshot($id)
            : $this->npcSnapshot($game->getId(), $id);
        $spends = $this->rules->resolveResourceSpends(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['actionRuleCode'],
            $choice['itemInventoryId'],
            $choice['itemRuleCode'],
            $snapshot['choices'],
            $snapshot['sheet'],
            $choice['chosenAmounts'],
        );
        $adjusted = (new GamePendingActionCosts())->adjustSpends(
            $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision()),
            $snapshot['choices'],
            $choice['actionRuleCode'],
            $kind,
            $id,
            $battle->getPendingActionEffects(),
            $spends,
        );
        $spends = $adjusted['spends'];
        $this->strikePending = $adjusted['pending'];
        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $debt = new GameActionDebt();
        $actorKey = $debt->key($kind, $id);
        $debt->assertFree($battle->getActionDebt(), $actorKey);
        $planned = $debt->plan($slice, $snapshot['sheet'], $spends, $debt->refillCode($slice), $choice, '1-n');
        $spends = $planned['spends'];
        if (!$planned['open']) {
            $held = $battle->getActionDebt();
            $held[$actorKey] = $planned['slot'];
            $this->debtHold = $held;
        }

        $inventorySpend = $planned['open'] ? $this->rules->resolveInventorySpend(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['itemRuleCode'],
            $choice['profileType'],
            $choice['profileIndex'],
            $choice['itemInventoryId'],
            $choice['ammunitionInventoryId'] ?? null,
            $kind,
            $id,
            $kind === 'npc' ? $snapshot['choices'] : null,
        ) : null;
        if ($spends === [] && $inventorySpend === null) {
            return;
        }

        if (
            $spends !== [] && !$this->rules->hasSufficientResources(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $snapshot['sheet'],
                $spends,
            )
        ) {
            throw new GameInvalidException('Game wide strike attacker resource is insufficient');
        }

        $this->sheetWrites->write(
            $game,
            $kind,
            $id,
            $snapshot['actualVersion'],
            [],
            null,
            null,
            $spends,
            $inventorySpend,
        );
    }

    /**
     * Возвращает authoritative snapshot NPC.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return array{actualVersion: int, choices: array<string, mixed>, sheet: array<string, mixed>} Snapshot.
     *
     * @throws GameInvalidException Если version повреждён.
     * @throws GameNotFoundException Если NPC отсутствует или чужой.
     */
    private function npcSnapshot(int $gameId, int $npcId): array
    {
        $npc = $this->npcs->getById($npcId);
        if ($npc->getGameId() !== $gameId) {
            throw new GameNotFoundException('Game wide strike npc was not found');
        }

        $version = $npc->getVersion();
        $choices = $version['choices'] ?? null;
        $sheet = $version['sheet'] ?? null;
        if (!is_array($choices) || !is_array($sheet)) {
            throw new GameInvalidException('Game wide strike npc sheet is invalid');
        }

        return [
            'actualVersion' => $npc->getActualVersion(),
            'choices' => $choices,
            'sheet' => $sheet,
        ];
    }

    /**
     * Выполняет открытие wide через общую replay boundary.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $body Тело.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattleVersion Ожидаемая версия.
     * @param array<string, mixed> $choice Выбор.
     * @param GameWideStrikeOpening $opening Построитель строк.
     *
     * @return array{result: array<string, mixed>, replay: bool} Итог.
     *
     * @throws GameBattleConflictException Если ключ занят другим телом.
     * @throws GameInvalidException Если итог невалиден.
     */
    private function executeOpenReplay(
        GameRecord $game,
        int $sessionId,
        string $idempotencyKey,
        array $body,
        GameBattleRecord $battle,
        int $expectedBattleVersion,
        array $choice,
        GameWideStrikeOpening $opening,
    ): array {
        return $this->replayTransaction->execute(
            fn (): ?array => $this->commands->findByKey($game->getId(), 'wide_strike', $idempotencyKey),
            fn (): int => $this->commands->reserve($game->getId(), $sessionId, 'wide_strike', $idempotencyKey, $body),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            fn (): array => $this->executeOpen(
                $game,
                $sessionId,
                $battle,
                $expectedBattleVersion,
                $choice,
                $opening,
            ),
            $body,
        );
    }

    /**
     * Выполняет запись закрытия внутри transaction.
     *
     * @param array<string, mixed> $context Контекст закрытия wide.
     *
     * @return array<string, mixed> Итог.
     */
    private function executeClose(
        array $context,
    ): array {
        $context = $this->prepareCloseContext($context);
        $result = $this->closeResult(
            $context['game'],
            $context['battle'],
            $context['expectedBattleVersion'],
            $context['strikeId'],
            $context['rows'],
            $context['choices'],
            $context['refusals'],
            $context['damage'],
            $context['attackerRoll'],
            $context['profile'],
            $context['penetration'] ?? null,
            $context['actorUserId'],
            $context['viewAll'],
            is_array($context['open'] ?? null) ? $context['open'] : [],
        );
        $this->storeReactions($context['rows'], $context['choices'], $result['targetResults']);
        $this->strikes->close($context['strikeId']);
        $this->battles->advanceVersion($context['battle']->getId(), $context['expectedBattleVersion']);

        return $result;
    }

    /**
     * Выполняет P3-подготовку результата внутри transaction work.
     *
     * @param array<string, mixed> $context Контекст закрытия.
     *
     * @return array<string, mixed> Подготовленный контекст.
     *
     * @throws GameInvalidException Если callback отсутствует или результат битый.
     */
    private function prepareCloseContext(array $context): array
    {
        $prepare = $context['prepare'] ?? null;
        unset($context['prepare']);
        if (!$prepare instanceof Closure) {
            throw new GameInvalidException('Game wide strike preparation is invalid');
        }

        $prepared = $prepare($context);
        if (!is_array($prepared)) {
            throw new GameInvalidException('Game wide strike preparation is invalid');
        }

        return $prepared;
    }

    /**
     * Собирает результат закрытия wide strike до записи реакций.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattleVersion Ожидаемая версия.
     * @param int $strikeId Удар.
     * @param list<array<string, mixed>> $rows Цели.
     * @param list<array{reaction: string, blockItemRuleCode: string|null, expectedSheetVersion: int}> $choices Защиты.
     * @param list<string|null> $refusals Non-CAS отказы.
     * @param DimensionalNumber $damage Урон.
     * @param array<string, mixed> $attackerRoll Roll атакующего.
     * @param array{itemRuleCode: string, profileType: string, profileIndex: int} $profile Профиль.
     * @param Closure|null $penetrationFactory Lazy penetration resolver.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array<string, mixed> Итог с targetResults.
     *
     * @throws GameInvalidException Если поле или результат невалидны.
     * @throws GameNotFoundException Если цель не найдена.
     * @throws GameBattleConflictException Если версия цели другая.
     */
    private function closeResult(
        GameRecord $game,
        GameBattleRecord $battle,
        int $expectedBattleVersion,
        int $strikeId,
        array $rows,
        array $choices,
        array $refusals,
        DimensionalNumber $damage,
        array $attackerRoll,
        array $profile,
        ?Closure $penetrationFactory,
        int $actorUserId,
        bool $viewAll,
        array $open = [],
    ): array {
        $targetResults = $this->targetResults(
            $game,
            $rows,
            $choices,
            $refusals,
            $damage,
            $attackerRoll,
            $profile,
            $penetrationFactory,
            $actorUserId,
            $viewAll,
            $open,
        );

        return [
            'battleId' => $battle->getId(),
            'strikeId' => $strikeId,
            'version' => $expectedBattleVersion + 1,
            'attackerRoll' => $attackerRoll,
            'targetResults' => $targetResults,
        ];
    }

    /**
     * Тело команды открытия.
     *
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $choice Выбор.
     * @param int $expectedBattleVersion Ожидаемая версия.
     *
     * @return array<string, mixed> Тело.
     */
    private function openBody(GameBattleRecord $battle, array $choice, int $expectedBattleVersion): array
    {
        return [
            'attack' => $choice,
            'battleId' => $battle->getId(),
            'expectedVersion' => $expectedBattleVersion,
        ];
    }

    /**
     * Тело команды закрытия.
     *
     * @param GameBattleRecord $battle Бой.
     * @param array<int, mixed> $choices Защиты.
     * @param int $expectedBattleVersion Ожидаемая версия.
     *
     * @return array<string, mixed> Тело.
     */
    private function closeBody(GameBattleRecord $battle, array $choices, int $expectedBattleVersion): array
    {
        return [
            'battleId' => $battle->getId(),
            'defense' => $choices,
            'expectedVersion' => $expectedBattleVersion,
        ];
    }

    /**
     * Результаты целей wide strike.
     *
     * @param GameRecord $game Игра.
     * @param list<array<string, mixed>> $rows Цели.
     * @param array<int, mixed> $choices Защиты.
     * @param list<string|null> $refusals Отказы.
     * @param DimensionalNumber $damage Урон.
     * @param array<string, mixed> $attackerRoll Roll атакующего.
     * @param array{itemRuleCode: string, profileType: string, profileIndex: int} $profile Профиль.
     * @param Closure|null $penetrationFactory Lazy penetration resolver.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return list<array<string, mixed>> Итоги.
     */
    private function targetResults(
        GameRecord $game,
        array $rows,
        array $choices,
        array $refusals,
        DimensionalNumber $damage,
        array $attackerRoll,
        array $profile,
        ?Closure $penetrationFactory,
        int $actorUserId,
        bool $viewAll,
        array $open = [],
    ): array {
        return $this->results->build(
            $game,
            $rows,
            $choices,
            $refusals,
            $damage,
            $attackerRoll,
            $profile['itemRuleCode'],
            $profile['profileType'],
            $profile['profileIndex'],
            $actorUserId,
            $viewAll,
            $penetrationFactory,
            $open,
        );
    }

    /**
     * Пишет реакции только принятых целей.
     *
     * @param list<array<string, mixed>> $rows Цели.
     * @param list<array{reaction: string, blockItemRuleCode: string|null, expectedSheetVersion: int}> $choices Защиты.
     * @param list<array<string, mixed>> $results Итоги.
     *
     * @return void
     *
     * @throws GameInvalidException Если id цели битый.
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

            $this->targets->close(
                $targetId,
                $choices[$index]['reaction'],
                $choices[$index]['blockItemInventoryId'],
                $choices[$index]['blockItemProfileIndex'],
                $choices[$index]['blockItemRuleCode'],
            );
        }
    }
}
