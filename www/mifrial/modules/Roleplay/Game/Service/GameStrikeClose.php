<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;

/**
 * Закрытие удара по одной цели или по списку целей.
 */
final class GameStrikeClose
{
    private readonly GameStrikeSingleChat $singleChat;

    private readonly GameStrikeWideChat $wideChat;

    private readonly GameStrikeCloseRows $rows;

    private readonly GameStrikeTargetClose $targets;

    private readonly GameStrikeSingleClose $single;

    private readonly GameStrikeWideClose $wide;

    /**
     * Создаёт закрытие удара.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameBattleRepository $battles Бои.
     * @param GameActionRepository $actions Действия и цели.
     * @param GameCommandRepository $commands Журнал команд.
     * @param GameStrikeAccess $access Права ответа.
     * @param GameStrikeSheets $sheets Листы и конфликты.
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     * @param GameStrikeResolution $resolution Расчёт бросков и урона.
     * @param GameStrikeSpend $spend Траты реакции.
     * @param GameStrikeChat $chat Токены и подписи.
     * @param GameStrikeConsequences $consequences Чат последствий.
     * @param GameStrikeAmounts $amounts Пары и нули.
     * @param GameReplayTransaction $replayTransaction Граница replay.
     * @param GamePushRules $push Толчок.
     * @param GameStateIncreaseChecks $stateChecks Проверки прироста.
     * @param GameProcessFlow $processes Сессия процесса.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameBattleRepository $battles,
        private readonly GameActionRepository $actions,
        private readonly GameCommandRepository $commands,
        private readonly GameStrikeAccess $access,
        private readonly GameStrikeSheets $sheets,
        private readonly GameStrikeSheetWrites $sheetWrites,
        private readonly GameStrikeResolution $resolution,
        private readonly GameStrikeSpend $spend,
        private readonly GameStrikeChat $chat,
        private readonly GameStrikeConsequences $consequences,
        private readonly GameStrikeAmounts $amounts,
        private readonly GameReplayTransaction $replayTransaction,
        private readonly GamePushRules $push,
        private readonly GameStateIncreaseChecks $stateChecks,
        private readonly GameProcessFlow $processes,
    ) {
        $singleView = new GameStrikeSingleView($this->chat, $this->rules);
        $this->singleChat = new GameStrikeSingleChat(
            $singleView,
            new GameStrikeSingleNarrative($this->chat, $this->rules, $this->spend),
            new GameStrikeSingleChecks($this->chat, $this->spend, $this->consequences, $singleView),
            new GameStrikeSingleOutcome(
                $this->chat,
                $this->consequences,
                new GameStrikeSinglePush($this->chat),
            ),
        );
        $wideCoverText = new GameStrikeWideCoverText($this->chat, $this->consequences, $this->spend);
        $wideNarrative = new GameStrikeWideNarrative($this->chat, $this->rules, $this->spend, $wideCoverText);
        $this->wideChat = new GameStrikeWideChat(
            $wideNarrative,
            $wideCoverText,
            new GameStrikeWideOutcome($this->chat, $this->consequences),
            $this->chat,
            $this->consequences,
            $this->spend,
        );
        $this->rows = new GameStrikeCloseRows($this->battles, $this->actions, $this->access);
        $choices = new GameStrikeTargetChoices($this->rules, $this->sheets);
        $support = new GameStrikeSupport();
        $cover = new GameStrikeCover($this->rules, $this->rows, $this->resolution);
        $declaredSpend = new GameStrikeDeclaredSpend(
            $this->rules,
            $this->actions,
            $this->sheets,
            $this->sheetWrites,
            $this->spend,
        );
        $targetReaction = new GameStrikeTargetReaction(
            $this->rules,
            $this->sheets,
            $this->spend,
            $choices,
        );
        $targetWrite = new GameStrikeTargetWrite(
            $this->sheets,
            $this->sheetWrites,
            $this->resolution,
        );
        $targetRolls = new GameStrikeTargetRolls($this->rules, $this->resolution, $this->amounts);
        $this->targets = new GameStrikeTargetClose(
            $this->rules,
            $targetReaction,
            $cover,
            new GameStrikeTargetFlow($this->rules, $this->amounts, $targetReaction, $targetWrite, $targetRolls),
            $targetRolls,
        );
        $modifierRunner = new GameActionModifierRunner(
            $this->rules,
            $this->sheets,
            $this->sheetWrites,
            $this->resolution,
            $this->consequences,
            $this->chat,
            $this->amounts,
            new GameActionModifiers(),
            new GameActionModifierPresenter(),
        );
        $this->single = new GameStrikeSingleClose(
            new GameStrikeSinglePrepare(
                new GameStrikeSingleLoad(
                    $this->rows,
                    $this->rules,
                    $this->push,
                    $cover,
                    $this->processes,
                    $this->sheets,
                ),
                new GameStrikeSingleStart(
                    new GameStrikeManeuver($this->actions, $this->battles, $this->sheets),
                    $choices,
                    $this->rules,
                ),
                new GameStrikeSingleDefender($this->rules, $this->sheets, $this->spend, $choices, $declaredSpend),
                new GameStrikeSingleAdvantages($support, $this->resolution, $this->processes),
            ),
            new GameStrikeSingleResolve(
                new GameStrikeSingleCoverPhase(
                    $cover,
                    $this->rows,
                    $this->sheetWrites,
                    $choices,
                    $this->actions,
                    $this->battles,
                ),
                new GameStrikeSingleRound($this->rules, $this->sheets, $this->resolution, $this->amounts),
                new GameStrikeSingleHits(
                    $this->rules,
                    $this->sheets,
                    $this->resolution,
                    $this->amounts,
                    $this->push,
                    new GameStrikeSingleUnstable($this->sheets, $this->sheetWrites, $this->stateChecks),
                ),
            ),
            new GameStrikeSingleWrite(
                $this->sheetWrites,
                $this->sheets,
                $this->singleChat,
                $this->actions,
                $this->battles,
                new GameStrikeSingleTrail($this->rules, $modifierRunner, $this->processes, $this->spend),
            ),
        );
        $wideChecks = new GameStrikeWideChecks($this->rules, $this->rows, $this->sheets, $choices);
        $wideResume = new GameStrikeWideResume($this->actions, $cover);
        $widePause = new GameStrikeWidePause($this->sheetWrites, $this->actions, $this->battles);
        $this->wide = new GameStrikeWideClose(
            new GameStrikeWideRequest($this->rows, $this->access, $this->actions, $wideChecks, $wideResume),
            new GameStrikeWideOpening(
                $this->rules,
                $declaredSpend,
                $support,
                $this->targets,
                $wideResume,
            ),
            new GameStrikeWidePrepare(
                $this->targets,
                $cover,
                $wideChecks,
                $wideResume,
                $widePause,
                $this->rows,
            ),
            new GameStrikeWideHits($this->rules, $this->amounts, $this->resolution, $support, $this->targets),
            new GameStrikeWidePending($choices, $this->spend, $this->targets),
            new GameStrikeWideFinalize($this->wideChat, $this->actions, $this->battles, $this->targets),
        );
    }

    /**
     * Принимает ответы целей широкого удара и закрывает его при полноте.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param list<array<string, mixed>> $choices Ответы целей.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array<string, mixed> Итог.
     */
    public function closeWide(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $choices,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
    ): array {
        $body = [
            'battleId' => $battle->getId(),
            'defense' => $choices,
            'expectedVersion' => $expectedBattleVersion,
        ];

        return $this->replayTransaction->execute(
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
            fn (): array => $this->wide->close(
                $game,
                $battle,
                $expectedBattleVersion,
                $choices,
                $actorUserId,
                $editAll,
                $viewAll,
            ),
            $body,
        )['result'];
    }

    /**
     * Продолжает одиночный удар после выбора фактической цели.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param array<string, mixed> $resume Решение окна.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array<string, mixed> Итог.
     */
    public function resumeSingle(
        GameRecord $game,
        int $sessionId,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $resume,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
    ): array {
        $body = [
            'battleId' => $battleId,
            'window' => $resume,
            'expectedVersion' => $expectedBattleVersion,
        ];

        return $this->replayTransaction->execute(
            fn (): ?array => $this->commands->findByKey($game->getId(), 'strike_window', $idempotencyKey),
            fn (): int => $this->commands->reserve(
                $game->getId(),
                $sessionId,
                'strike_window',
                $idempotencyKey,
                $body,
            ),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            fn (): array => $this->single->close(
                $game,
                $sessionId,
                $battleId,
                $expectedBattleVersion,
                0,
                [],
                $actorUserId,
                $editAll,
                $viewAll,
                $resume,
            ),
            $body,
        )['result'];
    }

    /**
     * Продолжает широкий удар после выбора фактической цели по строкам.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param array<int, array<string, mixed>> $resume Решения окна по строкам.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array<string, mixed> Итог.
     */
    public function resumeWide(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $resume,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
    ): array {
        $body = [
            'battleId' => $battle->getId(),
            'window' => $resume,
            'expectedVersion' => $expectedBattleVersion,
        ];

        return $this->replayTransaction->execute(
            fn (): ?array => $this->commands->findByKey($game->getId(), 'wide_window', $idempotencyKey),
            fn (): int => $this->commands->reserve(
                $game->getId(),
                $sessionId,
                'wide_window',
                $idempotencyKey,
                $body,
            ),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            fn (): array => $this->wide->close(
                $game,
                $battle,
                $expectedBattleVersion,
                [],
                $actorUserId,
                $editAll,
                $viewAll,
                $resume,
            ),
            $body,
        )['result'];
    }

    /**
     * Закрывает одиночный удар.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param int $expectedSheetVersion Версия листа.
     * @param array<string, mixed> $choice Защита.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array<string, mixed> Итог.
     */
    public function closeSingle(
        GameRecord $game,
        int $sessionId,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        int $expectedSheetVersion,
        array $choice,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
    ): array {
        $body = [
            'battleId' => $battleId,
            'defense' => $choice,
            'expectedSheetVersion' => $expectedSheetVersion,
            'expectedVersion' => $expectedBattleVersion,
        ];

        return $this->replayTransaction->execute(
            fn (): ?array => $this->commands->findByKey($game->getId(), 'strike', $idempotencyKey),
            fn (): int => $this->commands->reserve(
                $game->getId(),
                $sessionId,
                'strike',
                $idempotencyKey,
                $body,
            ),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            fn (): array => $this->single->close(
                $game,
                $sessionId,
                $battleId,
                $expectedBattleVersion,
                $expectedSheetVersion,
                $choice,
                $actorUserId,
                $editAll,
                $viewAll,
            ),
            $body,
        )['result'];
    }
}
