<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassTooLong
// Каст, выбор насыщения и отложенное состояние — одна причина изменения.

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
use Mifrial\Roleplay\Game\Dto\GameSpellCastSaturationPlan;
use Mifrial\Roleplay\Game\Dto\GameSpellCastSaturationResult;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;

/**
 * Серверная команда сотворения заклинания.
 */
final class GameSpellCast
{
    private readonly GameStrikeBattleLookup $lookup;

    /**
     * Создаёт команду каста.
     *
     * @param GameStrikeReady $ready Повтор, права и сессия.
     * @param GameReplayTransaction $replay Граница replay.
     * @param GameCommandRepository $commands Журнал команд.
     * @param GameStrikeAnnounce $announce Сигнал доставки.
     * @param GameStrikeRepositories $repos Репозитории боя.
     * @param GameStrikeAccess $access Права и состав боя.
     * @param GameActionPerform $perform Траты.
     * @param GameSpellCastFacts $facts Подготовка.
     * @param GameSpellTouchFacts $touchFacts Подготовка касания.
     * @param GameSpellCastCheck $check Сложность и бросок.
     * @param GameSpellCastDamage $damage Урон.
     * @param GameSpellCastWrite $write Запись.
     * @param GameSpellCastChat $chat Чат.
     * @param GameSpellCastView $view Ответ.
     * @param GameSpellCastSaturation $saturation Насыщение.
     * @param GameSpellCastNextCast $nextCast Хвост Сложности.
     * @param GameSpellCastPending $pending Отложенный каст.
     * @param GameStrikeSheetWrites $sheets Запись листа касания.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeReady $ready,
        private readonly GameReplayTransaction $replay,
        private readonly GameCommandRepository $commands,
        private readonly GameStrikeAnnounce $announce,
        private readonly GameStrikeRepositories $repos,
        private readonly GameStrikeAccess $access,
        private readonly GameActionPerform $perform,
        private readonly GameSpellCastFacts $facts,
        private readonly GameSpellTouchFacts $touchFacts,
        private readonly GameSpellCastCheck $check,
        private readonly GameSpellCastDamage $damage,
        private readonly GameSpellCastWrite $write,
        private readonly GameSpellCastChat $chat,
        private readonly GameSpellCastView $view,
        private readonly GameSpellCastSaturation $saturation,
        private readonly GameSpellCastNextCast $nextCast,
        private readonly GameSpellCastPending $pending,
        private readonly GameStrikeSheetWrites $sheets,
    ) {
        $this->lookup = new GameStrikeBattleLookup($repos->battles);
    }

    /**
     * Выполняет однофазный каст без паузы.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Нормализованное тело.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если запрос или домен отвергнуты.
     * @throws GameNotFoundException Если боя или цели нет.
     * @throws GameBattleConflictException Если версия, ключ или лист чужие.
     */
    public function cast(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        return $this->runCast($gameId, $actorUserId, $editAll, $viewAll, $body, false);
    }

    /**
     * Проверка сотворения с возможной паузой на выбор насыщения.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Нормализованное тело.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если запрос или домен отвергнуты.
     * @throws GameNotFoundException Если боя или цели нет.
     * @throws GameBattleConflictException Если версия, ключ или лист чужие.
     */
    public function check(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        return $this->runCast($gameId, $actorUserId, $editAll, $viewAll, $body, true);
    }

    /**
     * Применяет выбранные шаги к отложенному касту.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Тело resolve.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если запрос или домен отвергнуты.
     * @throws GameNotFoundException Если боя или цели нет.
     * @throws GameBattleConflictException Если версия, ключ или лист чужие.
     */
    public function resolve(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        return $this->runPhase($gameId, $actorUserId, $editAll, $viewAll, $body, false);
    }

    /**
     * Прерывает отложенный каст без трат.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Тело abort.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если запрос или домен отвергнуты.
     * @throws GameNotFoundException Если боя или цели нет.
     * @throws GameBattleConflictException Если версия, ключ или лист чужие.
     */
    public function abort(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        return $this->runPhase($gameId, $actorUserId, $editAll, $viewAll, $body, true);
    }

    /**
     * Однофазный каст или проверка с паузой.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Нормализованное тело.
     * @param bool $allowPause Разрешить паузу.
     *
     * @return array<string, mixed> Итог.
     */
    private function runCast(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
        bool $allowPause,
    ): array {
        return $this->execute(
            $gameId,
            $actorUserId,
            $editAll,
            $viewAll,
            $body,
            is_array($body['actor'] ?? null) ? $body['actor'] : null,
            'Game spell cast is invalid',
            fn (GameRecord $game, int $sessionId): array => $this->work(
                $game,
                $sessionId,
                $actorUserId,
                $body,
                $allowPause,
            ),
        );
    }

    /**
     * Фаза resolve или abort отложенного каста.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Тело.
     * @param bool $abort Прервать.
     *
     * @return array<string, mixed> Итог.
     */
    private function runPhase(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
        bool $abort,
    ): array {
        return $this->execute(
            $gameId,
            $actorUserId,
            $editAll,
            $viewAll,
            $body,
            null,
            $abort ? 'Game spell abort is invalid' : 'Game spell resolve is invalid',
            fn (GameRecord $game, int $sessionId): array => $abort
                ? $this->abortWork($game, $sessionId, $actorUserId, $editAll, $body)
                : $this->resolveWork($game, $sessionId, $actorUserId, $editAll, $body),
        );
    }

    /**
     * Готовит повтор, права и транзакцию фазы каста.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Тело.
     * @param array<string, mixed>|null $actingEntity Кастер или null.
     * @param string $invalid Текст ошибки невалидного запроса.
     * @param callable(GameRecord, int): array<string, mixed> $work Работа фазы.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если запрос или домен отвергнуты.
     * @throws GameNotFoundException Если боя или цели нет.
     * @throws GameBattleConflictException Если версия, ключ или лист чужие.
     */
    private function execute(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
        ?array $actingEntity,
        string $invalid,
        callable $work,
    ): array {
        $key = is_string($body['idempotencyKey'] ?? null) ? $body['idempotencyKey'] : '';
        $ready = $this->ready->check(
            $gameId,
            $actorUserId,
            $editAll,
            $viewAll,
            $key,
            $body,
            $actingEntity,
            'spell',
            $invalid,
        );
        if ($ready['replay'] !== null) {
            return $ready['replay'];
        }

        $game = $ready['game'];
        $sessionId = $ready['sessionId'];
        $execution = $this->replay->execute(
            fn (): ?array => $this->commands->findByKey($gameId, 'spell', $key),
            fn (): int => $this->commands->reserve($gameId, $sessionId, 'spell', $key, $body),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            fn (): array => $work($game, $sessionId),
            $body,
        );
        if (!$execution['replay']) {
            $this->announce->recorded($gameId, $key, 'spell');
        }

        return $execution['result'];
    }

    /**
     * Проверяет бой и решает, нужна ли пауза.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $actorUserId Пользователь.
     * @param array<string, mixed> $body Тело.
     * @param bool $allowPause Разрешить паузу.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если запрос или домен отвергнуты.
     * @throws GameNotFoundException Если боя или цели нет.
     * @throws GameBattleConflictException Если версия боя чужая.
     */
    private function work(
        GameRecord $game,
        int $sessionId,
        int $actorUserId,
        array $body,
        bool $allowPause,
    ): array {
        $battleId = is_int($body['battleId'] ?? null) ? $body['battleId'] : 0;
        $expected = is_int($body['expectedVersion'] ?? null) ? $body['expectedVersion'] : -1;
        $battle = $this->lookup->of($sessionId, $battleId, $expected);
        if ($this->repos->actions->findOpenAny($battleId) !== null) {
            throw new GameInvalidException('Game spell cast is blocked by an open action');
        }

        $actor = $this->sideOf($body['actor'] ?? null);
        $target = is_array($body['target'] ?? null) ? $this->sideOf($body['target']) : null;
        $this->access->assertRoster($battleId, $actor, $target === null ? [] : [$target]);

        $prepared = $this->facts->prepare($game, $body);
        $this->perform->assertSpends($game, $prepared->casterSheet, $prepared->spends);
        $actorKey = $prepared->casterKind . ':' . $prepared->casterId;
        $trainingDelta = $this->nextCast->peek($battle->getPendingActionEffects(), $actorKey, $prepared->spent);
        $check = $this->check->check($game, $prepared, $trainingDelta);
        $plan = $this->saturation->plan($prepared->saturationSource, $check);
        $castId = is_string($body['idempotencyKey'] ?? null) ? $body['idempotencyKey'] : '';

        if ($allowPause && $plan->hasChoice()) {
            return $this->openPending(
                $actorUserId,
                $body,
                $castId,
                $prepared,
                $check,
                $trainingDelta,
                $battle,
                $plan,
            );
        }

        return $this->finish(
            $game,
            $actorUserId,
            $body,
            $castId,
            $prepared,
            $check,
            $battle,
            $plan,
            0,
            null,
        );
    }

    /**
     * Открывает действие и пишет отложенный каст.
     *
     * @param int $actorUserId Пользователь.
     * @param array<string, mixed> $body Тело.
     * @param string $castId Ключ каста.
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param array<string, mixed> $check Итог проверки.
     * @param int $trainingDelta Сдвиг Сложности.
     * @param GameBattleRecord $battle Бой.
     * @param GameSpellCastSaturationPlan $plan Выбор.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если действие или строка боя битые.
     * @throws GameBattleConflictException Если версия боя чужая.
     */
    private function openPending(
        int $actorUserId,
        array $body,
        string $castId,
        GameSpellCastPrepared $prepared,
        array $check,
        int $trainingDelta,
        GameBattleRecord $battle,
        GameSpellCastSaturationPlan $plan,
    ): array {
        $actionId = $this->repos->actions->add([
            'kind' => 'spell',
            'battle_id' => $battle->getId(),
            'session_id' => $battle->getSessionId(),
            'attacker_kind' => $prepared->casterKind,
            'attacker_id' => $prepared->casterId,
            'action_rule_code' => $prepared->spellCode,
            'open' => true,
        ]);
        $pending = $this->pending->build(
            $castId,
            $actionId,
            $actorUserId,
            $prepared,
            $body,
            $check,
            $trainingDelta,
            null,
            false,
            null,
            GameCombatThread::buildSpellCast($battle, $castId),
            $plan,
        );
        $saved = $this->repos->battles->beginSpellCast($battle->getId(), $battle->getStateVersion(), $pending);

        return $this->view->build(
            $prepared,
            $check,
            $this->emptyDamage(),
            [
                'casterVersion' => $prepared->casterVersion,
                'targetVersion' => $prepared->target === null ? null : $prepared->target['version'],
            ],
            'check',
            $castId,
            $plan,
        ) + [
            'kind' => 'spell',
            'battleId' => $battle->getId(),
            'version' => $saved->getStateVersion(),
        ];
    }

    /**
     * Применяет каст и пишет листы, хвосты и чат.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param array<string, mixed> $body Тело.
     * @param string $castId Ключ каста.
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param array<string, mixed> $check Итог проверки.
     * @param GameBattleRecord $battle Бой.
     * @param GameSpellCastSaturationPlan $plan Выбор.
     * @param int $steps Выбранные шаги.
     * @param array<string, mixed>|null $pendingCast Отложенный каст или null.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если шаги или лист битые.
     * @throws GameBattleConflictException Если версия боя чужая.
     */
    private function finish(
        GameRecord $game,
        int $actorUserId,
        array $body,
        string $castId,
        GameSpellCastPrepared $prepared,
        array $check,
        GameBattleRecord $battle,
        GameSpellCastSaturationPlan $plan,
        int $steps,
        ?array $pendingCast,
    ): array {
        $saturation = $this->saturation->apply($plan, $steps);
        $damageForPower = $this->damage->apply($game, $prepared, $check['castOk'], null, $saturation->getPowerDelta());
        $versions = $this->write->write(
            $game,
            $prepared,
            $body,
            $damageForPower,
            $pendingCast === null ? null : (int) $pendingCast['casterVersion'],
        );

        [$pending, $rows] = $this->queueNext($prepared, $battle, $saturation);
        $saved = $this->saveBattle($battle, $pending, $pendingCast);

        return $this->respond(
            $game,
            $actorUserId,
            $prepared,
            $battle,
            $check,
            $damageForPower,
            $versions,
            $castId,
            $saturation,
            $rows,
            $pendingCast === null,
            $pendingCast,
            $saved,
        );
    }

    /**
     * Пишет чат и собирает ответ фазы.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $check Проверка.
     * @param array<string, mixed> $damage Урон.
     * @param array{casterVersion: int, targetVersion: int|null} $versions Версии.
     * @param string $castId Ключ каста.
     * @param GameSpellCastSaturationResult $saturation Насыщение.
     * @param array<int, array<string, mixed>> $rows Хвосты.
     * @param bool $announce Писать ли объявление.
     * @param array<string, mixed>|null $pendingCast Отложенный каст.
     * @param GameBattleRecord $saved Бой после записи.
     *
     * @return array<string, mixed> Итог.
     */
    private function respond(
        GameRecord $game,
        int $actorUserId,
        GameSpellCastPrepared $prepared,
        GameBattleRecord $battle,
        array $check,
        array $damage,
        array $versions,
        string $castId,
        GameSpellCastSaturationResult $saturation,
        array $rows,
        bool $announce,
        ?array $pendingCast,
        GameBattleRecord $saved,
    ): array {
        $this->chat->post(
            $game,
            $actorUserId,
            $prepared,
            $battle,
            $check,
            $damage,
            $announce,
            $this->threadOf($battle, $castId, $pendingCast),
            $saturation,
            $rows,
        );

        return $this->view->build(
            $prepared,
            $check,
            $damage,
            $versions,
            'resolve',
            $castId,
            null,
            $saturation,
            $rows,
        ) + [
            'kind' => 'spell',
            'battleId' => $battle->getId(),
            'version' => $saved->getStateVersion(),
        ];
    }

    /**
     * Применяет отложенный каст.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если запрос или домен отвергнуты.
     * @throws GameNotFoundException Если боя или цели нет.
     * @throws GameBattleConflictException Если версия боя чужая.
     */
    private function resolveWork(
        GameRecord $game,
        int $sessionId,
        int $actorUserId,
        bool $editAll,
        array $body,
    ): array {
        $battleId = is_int($body['battleId'] ?? null) ? $body['battleId'] : 0;
        $expected = is_int($body['expectedVersion'] ?? null) ? $body['expectedVersion'] : -1;
        [$battle, $pendingCast, $castId] = $this->loadPending($sessionId, $battleId, $expected, $body);

        $this->access->assertAttacker($game, $actorUserId, $editAll, $pendingCast['actor']);
        $prepared = $this->preparedOf($game, $battle, $pendingCast);
        $this->assertVersions($pendingCast, $prepared, $battle);
        $plan = $this->saturation->plan($prepared->saturationSource, $pendingCast['check']);
        $maxSteps = is_array($pendingCast['saturation'] ?? null)
            ? (int) ($pendingCast['saturation']['maxSteps'] ?? 0)
            : 0;
        if ($plan->getMaxSteps() !== $maxSteps) {
            throw new GameBattleConflictException($battle->getStateVersion());
        }

        $steps = is_int($body['saturationSteps'] ?? null) ? $body['saturationSteps'] : -1;
        $saturation = $this->saturation->apply($plan, $steps);
        $check = $pendingCast['check'];
        $check['castOk'] = (bool) ($check['castOk'] ?? false);
        $check['needsCheck'] = (bool) ($check['needsCheck'] ?? false);
        $damage = $this->damage->apply(
            $game,
            $prepared,
            $check['castOk'],
            is_int($pendingCast['deliverySr'] ?? null) ? $pendingCast['deliverySr'] : null,
            $saturation->getPowerDelta(),
        );
        $castBody = $this->pending->bodyOf($pendingCast, $game->getId(), $battleId);
        $versions = $this->resolveWrite($game, $prepared, $castBody, $damage, $pendingCast);
        [$pending, $rows] = $this->queueNext($prepared, $battle, $saturation);
        $saved = $this->repos->battles->finishSpellCast($battleId, $expected, $pending);
        $this->repos->actions->close($pendingCast['actionId']);
        return $this->respond(
            $game,
            $actorUserId,
            $prepared,
            $battle,
            $check,
            $damage,
            $versions,
            $castId,
            $saturation,
            $rows,
            false,
            $pendingCast,
            $saved,
        );
    }

    /**
     * Прерывает отложенный каст.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если запрос или домен отвергнуты.
     * @throws GameNotFoundException Если боя или цели нет.
     * @throws GameBattleConflictException Если версия боя чужая.
     */
    private function abortWork(
        GameRecord $game,
        int $sessionId,
        int $actorUserId,
        bool $editAll,
        array $body,
    ): array {
        $battleId = is_int($body['battleId'] ?? null) ? $body['battleId'] : 0;
        $expected = is_int($body['expectedVersion'] ?? null) ? $body['expectedVersion'] : -1;
        $battle = $this->repos->battles->find($battleId);
        if ($battle === null || $battle->getSessionId() !== $sessionId) {
            throw new GameNotFoundException('Game spell battle was not found');
        }

        $pendingCast = $this->pending->from($battle->getPendingSpellCast());
        $castId = is_string($body['castId'] ?? null) ? $body['castId'] : '';
        if ($castId === '' || $pendingCast['castId'] !== $castId) {
            throw new GameInvalidException('Game spell pending cast is unknown');
        }

        $current = $battle->getStateVersion();
        if (!$this->access->isWriter($game, $actorUserId, $editAll)) {
            $this->access->assertAttacker($game, $actorUserId, $editAll, $pendingCast['actor']);
            if ($current === $expected && !$this->hasStaleSheet($game, $pendingCast)) {
                throw new GameInvalidException('Game spell cast is not stale');
            }
        }

        $saved = $this->repos->battles->clearSpellCast($battleId, $current);
        $this->repos->actions->close($pendingCast['actionId']);

        return [
            'kind' => 'spell',
            'phase' => 'abort',
            'battleId' => $battleId,
            'castId' => $castId,
            'version' => $saved->getStateVersion(),
        ];
    }

    /**
     * Лист кастера или цели изменился с момента проверки.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $pendingCast Отложенный каст.
     *
     * @return bool true, если лист устарел.
     */
    private function hasStaleSheet(GameRecord $game, array $pendingCast): bool
    {
        $actor = $pendingCast['actor'];
        if ($this->facts->actualVersion($game, $actor['type'], $actor['id']) !== (int) $pendingCast['casterVersion']) {
            return true;
        }

        $target = is_array($pendingCast['target'] ?? null) ? $pendingCast['target'] : null;
        if ($target === null) {
            return false;
        }

        return $this->facts->actualVersion($game, $target['type'], $target['id'])
            !== (int) ($target['version'] ?? -1);
    }

    /**
     * Бой и отложенный каст по телу фазы.
     *
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param int $expected Ожидаемая версия.
     * @param array<string, mixed> $body Тело.
     *
     * @return array{0: GameBattleRecord, 1: array<string, mixed>, 2: string} Бой, каст и ключ.
     *
     * @throws GameInvalidException Если каст не совпал.
     * @throws GameNotFoundException Если боя нет.
     * @throws GameBattleConflictException Если версия чужая.
     */
    private function loadPending(
        int $sessionId,
        int $battleId,
        int $expected,
        array $body,
    ): array {
        $battle = $this->lookup->of($sessionId, $battleId, $expected);
        $pendingCast = $this->pending->from($battle->getPendingSpellCast());
        $castId = is_string($body['castId'] ?? null) ? $body['castId'] : '';
        if ($castId === '' || $pendingCast['castId'] !== $castId) {
            throw new GameInvalidException('Game spell pending cast is unknown');
        }

        return [$battle, $pendingCast, $castId];
    }

    /**
     * Подготовка отложенного каста.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $pendingCast Отложенный каст.
     *
     * @return GameSpellCastPrepared Подготовка.
     *
     * @throws GameInvalidException Если заклинание или цель битые.
     */
    private function preparedOf(GameRecord $game, GameBattleRecord $battle, array $pendingCast): GameSpellCastPrepared
    {
        if (($pendingCast['touch'] ?? false) === true) {
            $target = is_array($pendingCast['target'] ?? null) ? $pendingCast['target'] : null;
            $defender = $target === null
                ? []
                : ['type' => $target['type'] ?? '', 'id' => $target['id'] ?? 0];
            if ($defender === []) {
                throw new GameInvalidException('Game spell touch target is missing');
            }

            return $this->touchFacts->prepared(
                $game,
                ['attacker' => $pendingCast['actor'], 'defender' => $defender],
                $pendingCast['body'],
            );
        }

        return $this->facts->prepare(
            $game,
            $this->pending->bodyOf($pendingCast, $game->getId(), $battle->getId()),
        );
    }

    /**
     * Сверяет сохранённые версии с текущими.
     *
     * @param array<string, mixed> $pendingCast Отложенный каст.
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param GameBattleRecord $battle Бой.
     *
     * @return void
     *
     * @throws GameBattleConflictException Если версия изменилась.
     */
    private function assertVersions(
        array $pendingCast,
        GameSpellCastPrepared $prepared,
        GameBattleRecord $battle,
    ): void {
        if ($prepared->casterVersion !== (int) ($pendingCast['casterVersion'] ?? -1)) {
            throw new GameBattleConflictException($battle->getStateVersion());
        }

        $target = is_array($pendingCast['target'] ?? null) ? $pendingCast['target'] : null;
        $current = $prepared->damageTarget ?? $prepared->target;
        if ($target === null) {
            return;
        }

        if ($current === null || (int) ($target['version'] ?? -1) !== $current['version']) {
            throw new GameBattleConflictException($battle->getStateVersion());
        }
    }

    /**
     * Пишет листы для resolve.
     *
     * @param GameRecord $game Игра.
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param array<string, mixed> $body Тело.
     * @param array<string, mixed> $damage Урон.
     * @param array<string, mixed> $pendingCast Отложенный каст.
     *
     * @return array{casterVersion: int, targetVersion: int|null} Версии.
     *
     * @throws GameInvalidException Если лист битый.
     */
    private function resolveWrite(
        GameRecord $game,
        GameSpellCastPrepared $prepared,
        array $body,
        array $damage,
        array $pendingCast,
    ): array {
        if (($pendingCast['touch'] ?? false) !== true) {
            $body['expectedTargetVersion'] = is_array($pendingCast['target'] ?? null)
                ? (int) ($pendingCast['target']['version'] ?? 0)
                : null;

            return $this->write->write(
                $game,
                $prepared,
                $body,
                $damage,
                (int) $pendingCast['casterVersion'],
            );
        }

        $target = $prepared->damageTarget;
        if ($target === null) {
            return ['casterVersion' => (int) $pendingCast['casterVersion'], 'targetVersion' => null];
        }

        $expected = (int) ($pendingCast['target']['version'] ?? 0);
        $version = $damage['operations'] === []
            ? $expected
            : $this->sheets->write(
                $game,
                $target['type'],
                $target['id'],
                $expected,
                $damage['operations'],
                $target,
            );

        return ['casterVersion' => (int) $pendingCast['casterVersion'], 'targetVersion' => $version];
    }

    /**
     * Снимает старый хвост и ставит новый.
     *
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param GameBattleRecord $battle Бой.
     * @param GameSpellCastSaturationResult $saturation Итог насыщения.
     *
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>} Ожидание и строки.
     */
    private function queueNext(
        GameSpellCastPrepared $prepared,
        GameBattleRecord $battle,
        GameSpellCastSaturationResult $saturation,
    ): array {
        $actorKey = $prepared->casterKind . ':' . $prepared->casterId;
        $pending = $this->nextCast->consume($battle->getPendingActionEffects(), $actorKey);
        $pending = $this->nextCast->queue(
            $pending,
            $actorKey,
            $prepared->nextCastSources,
            $prepared->spent,
            $saturation->getRemainingRating(),
        );

        return [$pending, $this->nextCast->rows($pending, $actorKey)];
    }

    /**
     * Пишет ожидание и отложенный каст.
     *
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $pending Ожидание.
     * @param array<string, mixed>|null $pendingCast Отложенный каст.
     *
     * @return GameBattleRecord Бой после записи.
     *
     * @throws GameBattleConflictException Если версия боя чужая.
     */
    private function saveBattle(
        GameBattleRecord $battle,
        array $pending,
        ?array $pendingCast,
    ): GameBattleRecord {
        if ($pendingCast !== null) {
            return $this->repos->battles->finishSpellCast(
                $battle->getId(),
                $battle->getStateVersion(),
                $pending,
            );
        }

        if ($pending === $battle->getPendingActionEffects()) {
            return $battle;
        }

        return $this->repos->battles->replacePending(
            $battle->getId(),
            $battle->getStateVersion(),
            $pending,
        );
    }

    /**
     * Нить чата каста.
     *
     * @param GameBattleRecord $battle Бой.
     * @param string $castId Ключ каста.
     * @param array<string, mixed>|null $pendingCast Отложенный каст.
     *
     * @return array{id: string, parentId: string, kind: string} Нить.
     */
    private function threadOf(GameBattleRecord $battle, string $castId, ?array $pendingCast): array
    {
        $thread = is_array($pendingCast['thread'] ?? null) ? $pendingCast['thread'] : null;

        return $thread ?? GameCombatThread::buildSpellCast($battle, $castId);
    }

    /**
     * Пустой урон для фазы check.
     *
     * @return array{
     *     spellSr: int|null,
     *     spellDamage: null,
     *     injury: null,
     *     operations: list<array<string, mixed>>,
     *     consequence: array<string, mixed>
     * } Пустой урон.
     */
    private function emptyDamage(): array
    {
        return [
            'spellSr' => null,
            'spellDamage' => null,
            'injury' => null,
            'operations' => [],
            'consequence' => [],
        ];
    }

    /**
     * Сторона тела команды.
     *
     * @param mixed $value Значение.
     *
     * @return array{type: string, id: int} Сторона.
     *
     * @throws GameInvalidException Если сторона битая.
     */
    private function sideOf(mixed $value): array
    {
        $type = is_array($value) ? ($value['type'] ?? null) : null;
        $id = is_array($value) ? ($value['id'] ?? null) : null;
        if (($type !== 'character' && $type !== 'npc') || !is_int($id)) {
            throw new GameInvalidException('Game spell side is invalid');
        }

        return ['type' => $type, 'id' => $id];
    }
}
