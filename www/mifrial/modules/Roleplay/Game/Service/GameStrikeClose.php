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
    ) {
        $this->singleChat = new GameStrikeSingleChat(
            $this->chat,
            $this->consequences,
            $this->spend,
            $this->rules,
        );
        $this->wideChat = new GameStrikeWideChat(
            $this->chat,
            $this->consequences,
            $this->spend,
            $this->rules,
        );
        $this->rows = new GameStrikeCloseRows($this->battles, $this->actions, $this->access);
        $choices = new GameStrikeTargetChoices($this->rules, $this->sheets);
        $this->targets = new GameStrikeTargetClose(
            $this->rules,
            $this->sheets,
            $this->sheetWrites,
            $this->resolution,
            $this->spend,
            $this->amounts,
            $choices,
        );
        $this->single = new GameStrikeSingleClose(
            $this->rules,
            $this->actions,
            $this->battles,
            $this->sheets,
            $this->sheetWrites,
            $this->resolution,
            $this->spend,
            $this->amounts,
            $this->rows,
            $this->targets,
            $choices,
            $this->singleChat,
            new GameActionModifierRunner(
                $this->rules,
                $this->sheets,
                $this->sheetWrites,
                $this->resolution,
                $this->consequences,
                $this->chat,
                $this->amounts,
                new GameActionModifiers(),
                new GameActionModifierPresenter(),
            ),
        );
        $this->wide = new GameStrikeWideClose(
            $this->rules,
            $this->battles,
            $this->actions,
            $this->access,
            $this->sheets,
            $this->rows,
            $this->targets,
            $choices,
            $this->wideChat,
            $this->spend,
            $this->amounts,
            $this->resolution,
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
