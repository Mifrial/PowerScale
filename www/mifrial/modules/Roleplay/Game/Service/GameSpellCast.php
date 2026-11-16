<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassTooLong
// Каст, выбор насыщения и отложенное состояние — одна причина изменения.

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameActiveSpellRecord;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
use Mifrial\Roleplay\Game\Dto\GameSpellCastSaturationPlan;
use Mifrial\Roleplay\Game\Dto\GameSpellCastSaturationResult;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

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
     * @param GameSpellDeviation $deviation Отклонение.
     * @param GameSpellDeviationBurst $burst Взрыв отклонения.
     * @param GameActiveSpellCharge $activeCharge Заряд поддержания.
     * @param GameSpellChainPending $chainPending Состояние цепи.
     * @param GameSpellChainDamage $chainDamage Урон прыжка цепи.
     * @param GameSpellChainHop $chainHop Правила прыжка.
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
        private readonly GameSpellDeviation $deviation,
        private readonly GameSpellDeviationBurst $burst,
        private readonly GameActiveSpellGuard $activeGuard,
        private readonly GameActiveSpellCreate $activeCreate,
        private readonly GameActiveSpellChat $activeChat,
        private readonly GameActiveSpellCharge $activeCharge,
        private readonly GameSpellChainPending $chainPending,
        private readonly GameSpellChainDamage $chainDamage,
        private readonly GameSpellChainHop $chainHop,
        private readonly GameSpellMana $mana = new GameSpellMana(),
        private readonly GameSpellStability $stability = new GameSpellStability(),
        private readonly GameSpellDurationRules $durations = new GameSpellDurationRules(),
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
     * Прыжок цепи к следующей цели.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Тело прыжка.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если запрос или домен отвергнуты.
     * @throws GameNotFoundException Если боя или цели нет.
     * @throws GameBattleConflictException Если версия, ключ или лист чужие.
     */
    public function chain(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        return $this->runChain($gameId, $actorUserId, $editAll, $viewAll, $body, false);
    }

    /**
     * Останавливает цепь без следующей цели.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Тело остановки.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если запрос или домен отвергнуты.
     * @throws GameNotFoundException Если боя нет.
     * @throws GameBattleConflictException Если версия или ключ чужие.
     */
    public function chainStop(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        return $this->runChain($gameId, $actorUserId, $editAll, $viewAll, $body, true);
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
     * Фаза прыжка или остановки цепи.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Тело.
     * @param bool $stop Остановить цепь.
     *
     * @return array<string, mixed> Итог.
     */
    private function runChain(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
        bool $stop,
    ): array {
        return $this->execute(
            $gameId,
            $actorUserId,
            $editAll,
            $viewAll,
            $body,
            null,
            $stop ? 'Game spell chain stop is invalid' : 'Game spell chain is invalid',
            fn (GameRecord $game, int $sessionId): array => $stop
                ? $this->chainStopWork($game, $sessionId, $actorUserId, $editAll, $body)
                : $this->chainWork($game, $sessionId, $actorUserId, $editAll, $body),
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
        if ($prepared->chargeSpellId === null) {
            $this->activeGuard->assertSourceFree(
                $battle->getId(),
                $prepared->casterKind,
                $prepared->casterId,
                $prepared->sourceKey,
            );
        }
        if ($this->durations->isSustained($prepared->spell->getSpell()->getDuration())) {
            $this->activeGuard->assertCasterLimit(
                $battle->getId(),
                $prepared->casterKind,
                $prepared->casterId,
                $prepared->path->getActiveSpellLimit(),
            );
        }
        $this->perform->assertSpends($game, $prepared->casterSheet, $prepared->spends);
        $actorKey = $prepared->casterKind . ':' . $prepared->casterId;
        $trainingDelta = $this->nextCast->peek($battle->getPendingActionEffects(), $actorKey, $prepared->spent);
        $check = $this->check->check($game, $prepared, $trainingDelta);
        $plan = $this->saturation->plan($prepared->saturationSource, $check);
        $deviation = $this->deviation->afterCheck($prepared, $check);
        $castId = is_string($body['idempotencyKey'] ?? null) ? $body['idempotencyKey'] : '';

        if ($allowPause && ($plan->hasChoice() || $this->deviation->needsPause($deviation))) {
            return $this->openPending(
                $actorUserId,
                $body,
                $castId,
                $prepared,
                $check,
                $trainingDelta,
                $battle,
                $plan,
                $deviation,
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
            $deviation,
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
     * @param array<string, mixed>|null $deviation Отклонение.
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
        ?array $deviation,
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
            $deviation,
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
            null,
            [],
            $this->deviationView($prepared, $deviation),
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
     * @param array<string, mixed>|null $deviation Отклонение или null.
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
        ?array $deviation,
    ): array {
        $saturation = $this->saturation->apply($plan, $steps);
        $damageForPower = $this->damage->apply($game, $prepared, $check['castOk'], null, $saturation->getPowerDelta());
        $casterOperations = [
            ...$this->casterOperations($prepared, $deviation),
            ...$this->activeCharge->spendOperations($prepared),
            ...$this->mana->spendOperations($prepared),
        ];

        $versions = $this->write->write(
            $game,
            $prepared,
            $body,
            $damageForPower,
            $pendingCast === null ? null : (int) $pendingCast['casterVersion'],
            $casterOperations,
        );
        $activeSpell = $this->activeCreate->fromCast(
            $game,
            $prepared,
            $battle,
            $check['castOk'] === true,
            false,
            $versions['casterVersion'],
        );
        $burstRows = $this->applyBurst($game, $battle, $prepared, $deviation, $body);

        [$pending, $rows] = $this->queueNext($prepared, $battle, $saturation);
        $chain = $this->chainOf($castId, $prepared, $damageForPower, $this->threadOf($battle, $castId, $pendingCast));
        $saved = $this->saveBattle($battle, $pending, $pendingCast, $chain);

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
            $deviation,
            $burstRows,
            $plan,
            $activeSpell,
            $chain,
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
     * @param array<string, mixed>|null $deviation Отклонение.
     * @param list<array<string, mixed>> $burst Цели взрыва.
     * @param GameSpellCastSaturationPlan|null $plan Выбор насыщения.
     * @param GameActiveSpellRecord|null $activeSpell Созданное волшебство.
     * @param array<string, mixed>|null $chain Продолжение цепи.
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
        ?array $deviation = null,
        array $burst = [],
        ?GameSpellCastSaturationPlan $plan = null,
        ?GameActiveSpellRecord $activeSpell = null,
        ?array $chain = null,
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
            $deviation,
        );
        if ($burst !== []) {
            $this->chat->postBurst(
                $game,
                $actorUserId,
                $prepared,
                $battle,
                $this->threadOf($battle, $castId, $pendingCast),
                $burst,
            );
        }

        if ($activeSpell instanceof GameActiveSpellRecord) {
            $thread = GameCombatThread::buildTurn($battle->getId(), $battle->getRound(), $battle->getActiveIndex());
            $this->activeChat->createdAt($game, $actorUserId, $activeSpell, $thread);
            $versions['casterVersion'] = $this->activeCharge->grantOnCreate(
                $game,
                $actorUserId,
                $activeSpell,
                $prepared,
                $versions['casterVersion'],
                $thread,
            );
        }

        return $this->view->build(
            $prepared,
            $check,
            $damage,
            $versions,
            'resolve',
            $castId,
            $plan,
            $saturation,
            $rows,
            $this->deviationView($prepared, $deviation),
        ) + [
            'kind' => 'spell',
            'battleId' => $battle->getId(),
            'version' => $saved->getStateVersion(),
            'burst' => $this->burstView($burst),
            'activeSpell' => $activeSpell === null ? null : (new GameActiveSpellView())->build(
                $activeSpell,
                $this->stability->value($prepared->spell, $activeSpell->getParameterValues(), $prepared->usedPower),
            ),
            'chain' => $chain === null ? null : $this->chainPending->view($chain),
        ];
    }

    /**
     * Операции листа кастера от отклонения.
     *
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param array<string, mixed>|null $deviation Исход отклонения.
     *
     * @return list<array<string, mixed>> Операции.
     *
     * @throws GameInvalidException Если карточка отклонения битая.
     */
    private function casterOperations(GameSpellCastPrepared $prepared, ?array $deviation): array
    {
        if ($deviation === null) {
            return [];
        }

        $spec = $this->deviation->spec($prepared->slice, $prepared->path->getCastCheckCode());
        if ($spec === null) {
            return [];
        }

        return $this->deviation->operations(
            $spec,
            $prepared->casterSheet,
            $prepared->sourceKey,
            is_int($deviation['strength'] ?? null) ? $deviation['strength'] : 0,
        );
    }

    /**
     * Блок отклонения для ответа.
     *
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param array<string, mixed>|null $deviation Исход отклонения.
     *
     * @return array<string, mixed>|null Блок или null.
     *
     * @throws GameInvalidException Если карточка отклонения битая.
     */
    private function deviationView(GameSpellCastPrepared $prepared, ?array $deviation): ?array
    {
        return $this->deviation->view($prepared, $deviation);
    }

    /**
     * Взрыв по целям из запроса и записи листов.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param array<string, mixed>|null $deviation Отклонение.
     * @param array<string, mixed> $body Тело.
     *
     * @return list<array<string, mixed>> Строки целей.
     *
     * @throws GameInvalidException Если цели или карточка битые.
     * @throws GameBattleConflictException Если лист цели уже другой.
     */
    private function applyBurst(
        GameRecord $game,
        GameBattleRecord $battle,
        GameSpellCastPrepared $prepared,
        ?array $deviation,
        array $body,
    ): array {
        if ($deviation === null || ($deviation['isDoubles'] ?? false) !== true) {
            return [];
        }

        $spec = $this->deviation->spec($prepared->slice, $prepared->path->getCastCheckCode());
        $burst = $spec?->getBurst();
        if ($burst === null) {
            return [];
        }

        $targets = $this->burstTargetsOf($body, $prepared, $battle);
        $rows = $this->burst->plan($game, $prepared, $deviation, $burst, $targets);
        foreach ($rows as $index => $row) {
            if ($row['operations'] === []) {
                continue;
            }

            $target = $row['target'];
            $rows[$index]['version'] = $this->sheets->write(
                $game,
                $target['type'],
                $target['id'],
                $row['version'],
                $row['operations'],
            );
        }

        return $rows;
    }

    /**
     * Цели взрыва: список из запроса плюс кастер с дистанцией 0.
     *
     * @param array<string, mixed> $body Тело.
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param GameBattleRecord $battle Бой.
     *
     * @return list<array{type: string, id: int, distanceIpari: int}> Цели.
     *
     * @throws GameInvalidException Если цель или дистанция битые.
     * @throws GameNotFoundException Если цели нет в бою.
     */
    private function burstTargetsOf(array $body, GameSpellCastPrepared $prepared, GameBattleRecord $battle): array
    {
        $radius = (new DimensionalNumber($prepared->usedPower->getBase(), $prepared->usedPower->getSize()))->toInteger();
        $raw = $body['burstTargets'] ?? null;
        if ($raw === null) {
            $raw = [];
        }

        if (!is_array($raw) || !array_is_list($raw)) {
            throw new GameInvalidException('Game spell burst targets are invalid');
        }

        $targets = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                throw new GameInvalidException('Game spell burst targets are invalid');
            }

            $type = $row['type'] ?? null;
            $id = $row['id'] ?? null;
            $distance = $row['distanceIpari'] ?? null;
            if (
                !in_array($type, ['character', 'npc'], true)
                || !is_int($id)
                || $id < 1
                || !is_int($distance)
                || $distance < 0
                || $distance > $radius
            ) {
                throw new GameInvalidException('Game spell burst targets are invalid');
            }

            $targets[$type . ':' . $id] = ['type' => $type, 'id' => $id, 'distanceIpari' => $distance];
        }

        $casterKey = $prepared->casterKind . ':' . $prepared->casterId;
        $targets[$casterKey] = [
            'type' => $prepared->casterKind,
            'id' => $prepared->casterId,
            'distanceIpari' => 0,
        ];

        $others = [];
        foreach ($targets as $key => $target) {
            if ($key !== $casterKey) {
                $others[] = ['type' => $target['type'], 'id' => $target['id']];
            }
        }

        $this->access->assertRoster(
            $battle->getId(),
            ['type' => $prepared->casterKind, 'id' => $prepared->casterId],
            $others,
        );

        return array_values($targets);
    }

    /**
     * Цели взрыва для ответа.
     *
     * @param list<array<string, mixed>> $rows Строки.
     *
     * @return list<array<string, mixed>> Блок ответа.
     */
    private function burstView(array $rows): array
    {
        $view = [];
        foreach ($rows as $row) {
            $view[] = [
                'target' => $row['target'],
                'damage' => $this->pair($row['damage']),
                'injury' => $this->pair($row['injury']),
                'sheetVersion' => $row['version'],
            ];
        }

        return $view;
    }

    /**
     * Пара размерного числа для ответа.
     *
     * @param mixed $value Значение.
     *
     * @return array{base: int, size: int}|null Пара или null.
     */
    private function pair(mixed $value): ?array
    {
        if (!$value instanceof DimensionalNumber) {
            return null;
        }

        return ['base' => $value->getBase(), 'size' => $value->getSize()];
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
        $deviation = is_array($pendingCast['deviation'] ?? null) ? $pendingCast['deviation'] : null;
        $versions = $this->resolveWrite($game, $prepared, $castBody, $damage, $pendingCast, $deviation);
        $deliverySr = is_int($pendingCast['deliverySr'] ?? null) ? $pendingCast['deliverySr'] : null;
        $activeSpell = $this->activeCreate->fromCast(
            $game,
            $prepared,
            $battle,
            $check['castOk'],
            $deliverySr !== null && $deliverySr <= 0,
            $versions['casterVersion'],
        );
        $burstRows = $this->applyBurst($game, $battle, $prepared, $deviation, $body);
        [$pending, $rows] = $this->queueNext($prepared, $battle, $saturation);
        $chain = $this->chainOf($castId, $prepared, $damage, $this->threadOf($battle, $castId, $pendingCast));
        $saved = $chain === null
            ? $this->repos->battles->finishSpellCast($battleId, $expected, $pending)
            : $this->repos->battles->replacePending($battleId, $expected, $pending, null, $chain);
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
            true,
            $pendingCast,
            $saved,
            $deviation,
            $burstRows,
            $plan,
            $activeSpell,
            $chain,
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
     * Применяет прыжок цепи и обновляет её состояние.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param array<string, mixed> $body Тело прыжка.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если цель или состояние цепи битые.
     * @throws GameNotFoundException Если боя или цели нет.
     * @throws GameBattleConflictException Если версия боя чужая.
     */
    private function chainWork(
        GameRecord $game,
        int $sessionId,
        int $actorUserId,
        bool $editAll,
        array $body,
    ): array {
        $battleId = is_int($body['battleId'] ?? null) ? $body['battleId'] : 0;
        $expected = is_int($body['expectedVersion'] ?? null) ? $body['expectedVersion'] : -1;
        $battle = $this->lookup->of($sessionId, $battleId, $expected);
        $chain = $this->chainPending->from($battle->getPendingSpellCast());
        $castId = is_string($body['castId'] ?? null) ? $body['castId'] : '';
        if ($castId === '' || $chain['castId'] !== $castId) {
            throw new GameInvalidException('Game spell chain is unknown');
        }

        $actor = $this->chainActor($chain);
        $this->access->assertAttacker($game, $actorUserId, $editAll, $actor);
        $target = is_array($body['chainTarget'] ?? null) ? $body['chainTarget'] : null;
        if ($target === null) {
            throw new GameInvalidException('Game spell chain target is missing');
        }

        $this->access->assertRoster(
            $battleId,
            $actor,
            [['type' => $target['type'], 'id' => $target['id']]],
        );
        $key = $target['type'] . ':' . $target['id'];
        if (!$this->chainHop->canHit($this->chainPending->chainSpec($chain), $key, (string) $chain['lastKey'])) {
            throw new GameInvalidException('Game spell chain target is invalid');
        }

        $row = $this->chainDamage->hop($game, $chain, $target);
        $broken = $row === null || $this->chainBroke($row);
        if (!$broken) {
            $row = $this->writeHop($game, $target, $row);
            $chain = $this->chainPending->advance($chain, $row['damage'], $key);
        }

        $next = $broken ? null : $this->chainNext($chain);
        $this->chat->postChain($game, $actorUserId, $battle, $chain, $row, $broken);
        $saved = $next === null
            ? $this->repos->battles->finishSpellCast($battleId, $expected, $battle->getPendingActionEffects())
            : $this->repos->battles->replacePending(
                $battleId,
                $expected,
                $battle->getPendingActionEffects(),
                null,
                $next,
            );

        return [
            'kind' => 'spell',
            'phase' => 'chain',
            'battleId' => $battleId,
            'castId' => $castId,
            'version' => $saved->getStateVersion(),
            'broke' => $broken,
            'hop' => $row === null ? null : $this->hopView($row),
            'chain' => $next === null ? null : $this->chainPending->view($next),
        ];
    }

    /**
     * Снимает продолжение цепи.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param array<string, mixed> $body Тело остановки.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если состояние цепи битое.
     * @throws GameNotFoundException Если боя нет.
     * @throws GameBattleConflictException Если версия боя чужая.
     */
    private function chainStopWork(
        GameRecord $game,
        int $sessionId,
        int $actorUserId,
        bool $editAll,
        array $body,
    ): array {
        $battleId = is_int($body['battleId'] ?? null) ? $body['battleId'] : 0;
        $expected = is_int($body['expectedVersion'] ?? null) ? $body['expectedVersion'] : -1;
        $battle = $this->lookup->of($sessionId, $battleId, $expected);
        $chain = $this->chainPending->from($battle->getPendingSpellCast());
        $castId = is_string($body['castId'] ?? null) ? $body['castId'] : '';
        if ($castId === '' || $chain['castId'] !== $castId) {
            throw new GameInvalidException('Game spell chain is unknown');
        }

        $this->access->assertAttacker($game, $actorUserId, $editAll, $this->chainActor($chain));
        $saved = $this->repos->battles->clearSpellCast($battleId, $expected);

        return [
            'kind' => 'spell',
            'phase' => 'chainStop',
            'battleId' => $battleId,
            'castId' => $castId,
            'version' => $saved->getStateVersion(),
        ];
    }

    /**
     * Продолжение цепи после попадания.
     *
     * @param string $castId Ключ каста.
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param array<string, mixed> $damage Итог урона.
     * @param array<string, mixed> $thread Нить чата.
     *
     * @return array<string, mixed>|null Продолжение или null.
     */
    private function chainOf(
        string $castId,
        GameSpellCastPrepared $prepared,
        array $damage,
        array $thread,
    ): ?array {
        $ruleCode = $prepared->upgrades->getChainRuleCode();

        return $ruleCode === null
            ? null
            : $this->chainPending->build($castId, $prepared, $damage, $ruleCode, $thread);
    }

    /**
     * Кастер цепи в форме состава.
     *
     * @param array<string, mixed> $chain Состояние цепи.
     *
     * @return array{type: string, id: int} Кастер.
     *
     * @throws GameInvalidException Если кастер битый.
     */
    private function chainActor(array $chain): array
    {
        $actor = is_array($chain['actor'] ?? null) ? $chain['actor'] : [];
        $type = $actor['type'] ?? null;
        $id = $actor['id'] ?? null;
        if (!is_string($type) || $type === '' || !is_int($id)) {
            throw new GameInvalidException('Game spell chain actor is invalid');
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * Попадание прыжка не нанесло повреждений.
     *
     * @param array<string, mixed> $row Строка прыжка.
     *
     * @return bool true, если цепь обрывается.
     */
    private function chainBroke(array $row): bool
    {
        $injury = $row['injury'] ?? null;

        return !$injury instanceof DimensionalNumber || $injury->toInteger() <= 0;
    }

    /**
     * Цепь может продолжиться со следующей целью.
     *
     * @param array<string, mixed> $chain Состояние цепи.
     *
     * @return array<string, mixed>|null Продолжение или null.
     */
    private function chainNext(array $chain): ?array
    {
        $amount = $this->chainHop->nextAmount(
            $this->chainPending->chainSpec($chain),
            $this->chainPending->damageSpec($chain),
            $this->chainPending->amount($chain),
            0,
        );

        return $amount === null ? null : $chain;
    }

    /**
     * Пишет операции листа поражённой цели.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int, distanceIpari: int} $target Цель.
     * @param array<string, mixed> $row Строка прыжка.
     *
     * @return array<string, mixed> Строка с версией листа.
     */
    private function writeHop(GameRecord $game, array $target, array $row): array
    {
        if ($row['operations'] === []) {
            return $row;
        }

        $row['version'] = $this->sheets->write(
            $game,
            $target['type'],
            $target['id'],
            $row['version'],
            $row['operations'],
        );

        return $row;
    }

    /**
     * Блок прыжка для ответа.
     *
     * @param array<string, mixed> $row Строка прыжка.
     *
     * @return array<string, mixed> Блок.
     */
    private function hopView(array $row): array
    {
        return [
            'target' => $row['target'],
            'damage' => $this->pair($row['damage']),
            'injury' => $this->pair($row['injury']),
            'sheetVersion' => $row['version'],
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
                $battle,
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
     * @param array<string, mixed>|null $deviation Отклонение.
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
        ?array $deviation = null,
    ): array {
        $casterOperations = [
            ...$this->casterOperations($prepared, $deviation),
            ...$this->activeCharge->spendOperations($prepared),
            ...$this->mana->spendOperations($prepared),
        ];

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
                $casterOperations,
            );
        }

        $casterVersion = (int) $pendingCast['casterVersion'];
        if ($casterOperations !== []) {
            $casterVersion = $this->sheets->write(
                $game,
                $prepared->casterKind,
                $prepared->casterId,
                $casterVersion,
                $casterOperations,
            );
        }

        $target = $prepared->damageTarget;
        if ($target === null) {
            return ['casterVersion' => $casterVersion, 'targetVersion' => null];
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

        return ['casterVersion' => $casterVersion, 'targetVersion' => $version];
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
     * @param array<string, mixed>|null $chain Продолжение цепи.
     *
     * @return GameBattleRecord Бой после записи.
     *
     * @throws GameBattleConflictException Если версия боя чужая.
     */
    private function saveBattle(
        GameBattleRecord $battle,
        array $pending,
        ?array $pendingCast,
        ?array $chain = null,
    ): GameBattleRecord {
        if ($chain !== null) {
            return $this->repos->battles->replacePending(
                $battle->getId(),
                $battle->getStateVersion(),
                $pending,
                null,
                $chain,
            );
        }

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
