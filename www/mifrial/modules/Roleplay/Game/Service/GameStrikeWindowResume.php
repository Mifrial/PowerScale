<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Продолжение одиночного или широкого удара по решению окна.
 */
final class GameStrikeWindowResume
{
    /**
     * Создаёт продолжение окна.
     *
     * @param GameStrikeWindowLookup $lookup Ожидания строки.
     * @param GameStrikeClose $closeService Закрытие удара.
     * @param GameStrikeBattleLookup $battles Бой.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeWindowLookup $lookup,
        private readonly GameStrikeClose $closeService,
        private readonly GameStrikeBattleLookup $battles,
    ) {
    }

    /**
     * Продолжает одиночный удар по решению окна.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param array<string, mixed> $row Строка удара.
     * @param string $target Ключ цели.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Ключ.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если цель не входит в ожидания.
     */
    public function single(
        GameRecord $game,
        int $sessionId,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $row,
        string $target,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
    ): array {
        $awaiting = $this->lookup->awaitingOfRow($row);
        if (!$this->lookup->optionAllowed($awaiting, $target)) {
            throw new GameInvalidException('Game strike window target is invalid');
        }

        return $this->closeService->resumeSingle(
            $game,
            $sessionId,
            $battleId,
            $idempotencyKey,
            $expectedBattleVersion,
            ['actualTarget' => $target],
            $actorUserId,
            $editAll,
            $viewAll,
        );
    }

    /**
     * Продолжает широкий удар по решению окна одной строки.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param list<array<string, mixed>> $strikes Строки удара.
     * @param string $rowKey Ключ строки.
     * @param string $target Ключ цели.
     * @param array<string, mixed> $window Решение окна.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Ключ.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если строки или цель чужие.
     */
    public function wide(
        GameRecord $game,
        int $sessionId,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $strikes,
        string $rowKey,
        string $target,
        array $window,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
    ): array {
        $resume = $this->matching($strikes, $rowKey, $target);
        if ($resume === [] || !isset($window['actualTarget'])) {
            throw new GameInvalidException('Game strike window target is invalid');
        }

        return $this->resumeWide($game, $sessionId, $battleId, $idempotencyKey, $expectedBattleVersion, $resume, $actorUserId, $editAll, $viewAll);
    }

    /**
     * Продолжает широкий удар с проверкой боя.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param array<int, array{actualTarget: string}> $resume Выбор по строкам.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Ключ.
     *
     * @return array<string, mixed> Итог.
     */
    private function resumeWide(
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
        return $this->closeService->resumeWide(
            $game,
            $sessionId,
            $this->battles->of($sessionId, $battleId, $expectedBattleVersion),
            $idempotencyKey,
            $expectedBattleVersion,
            $resume,
            $actorUserId,
            $editAll,
            $viewAll,
        );
    }

    /**
     * Строки удара, попадающие под решение окна.
     *
     * @param list<array<string, mixed>> $strikes Строки.
     * @param string $rowKey Ключ строки.
     * @param string $target Ключ цели.
     *
     * @return array<int, array{actualTarget: string}> Выбор по строкам.
     */
    private function matching(array $strikes, string $rowKey, string $target): array
    {
        $resume = [];
        foreach ($strikes as $row) {
            $resume += $this->rowResume($row, $rowKey, $target);
        }

        return $resume;
    }

    /**
     * Решение окна для одной строки.
     *
     * @param array<string, mixed> $row Строка удара.
     * @param string $rowKey Ключ строки.
     * @param string $target Ключ цели.
     *
     * @return array<int, array{actualTarget: string}> Выбор или пустой список.
     */
    private function rowResume(array $row, string $rowKey, string $target): array
    {
        $rowId = is_int($row['id'] ?? null) ? $row['id'] : 0;
        $awaitings = $this->lookup->awaitingOfRow($row);
        if ($awaitings === [] || !$this->lookup->optionAllowed($awaitings, $target)) {
            return [];
        }

        if (!$this->matches($rowId, $awaitings, $rowKey)) {
            return [];
        }

        return [$rowId => ['actualTarget' => $target]];
    }

    /**
     * Строка или её ожидание совпадают с ключом.
     *
     * @param int $rowId Id строки.
     * @param list<array<string, mixed>> $awaitings Ожидания.
     * @param string $rowKey Ключ строки.
     *
     * @return bool Признак.
     */
    private function matches(int $rowId, array $awaitings, string $rowKey): bool
    {
        if ($rowKey === '' || $rowKey === (string) $rowId) {
            return true;
        }

        foreach ($awaitings as $entry) {
            if (($entry['rowKey'] ?? null) === $rowKey) {
                return true;
            }
        }

        return false;
    }
}
