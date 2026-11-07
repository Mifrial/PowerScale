<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Closure;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;

/**
 * Закрытие удара по одной цели или по списку целей.
 */
final class GameStrikeClose
{
    /**
     * Создаёт закрытие удара.
     *
     * @param GameCommandRepository $commands Журнал команд.
     * @param GameReplayTransaction $replayTransaction Граница replay.
     * @param GameStrikeSingleClose $single Одиночный удар.
     * @param GameStrikeWideClose $wide Широкий удар.
     *
     * @return void
     */
    public function __construct(
        private readonly GameCommandRepository $commands,
        private readonly GameReplayTransaction $replayTransaction,
        private readonly GameStrikeSingleClose $single,
        private readonly GameStrikeWideClose $wide,
    ) {
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

        return $this->transaction(
            $game,
            $sessionId,
            'wide_strike',
            $idempotencyKey,
            $body,
            fn (): array => $this->wide->close($game, $battle, $expectedBattleVersion, $choices, $actorUserId, $editAll, $viewAll),
        );
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

        return $this->transaction(
            $game,
            $sessionId,
            'strike_window',
            $idempotencyKey,
            $body,
            fn (): array => $this->single->close($game, $sessionId, $battleId, $expectedBattleVersion, 0, [], $actorUserId, $editAll, $viewAll, $resume),
        );
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

        return $this->transaction(
            $game,
            $sessionId,
            'wide_window',
            $idempotencyKey,
            $body,
            fn (): array => $this->wide->close($game, $battle, $expectedBattleVersion, [], $actorUserId, $editAll, $viewAll, $resume),
        );
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

        return $this->transaction(
            $game,
            $sessionId,
            'strike',
            $idempotencyKey,
            $body,
            fn (): array => $this->single->close($game, $sessionId, $battleId, $expectedBattleVersion, $expectedSheetVersion, $choice, $actorUserId, $editAll, $viewAll),
        );
    }

    /**
     * Выполняет работу в границе replay-транзакции.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param string $kind Вид команды.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $body Тело команды.
     * @param Closure $work Работа домена.
     *
     * @return array<string, mixed> Итог.
     */
    private function transaction(
        GameRecord $game,
        int $sessionId,
        string $kind,
        string $idempotencyKey,
        array $body,
        Closure $work,
    ): array {
        return $this->replayTransaction->execute(
            fn (): ?array => $this->commands->findByKey($game->getId(), $kind, $idempotencyKey),
            fn (): int => $this->commands->reserve(
                $game->getId(),
                $sessionId,
                $kind,
                $idempotencyKey,
                $body,
            ),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            $work,
            $body,
        )['result'];
    }
}
