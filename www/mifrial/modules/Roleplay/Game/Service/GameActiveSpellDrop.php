<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameActiveSpellRepository;

/**
 * Прекращение поддержания заклинания.
 */
final class GameActiveSpellDrop
{
    /**
     * Создаёт команду.
     *
     * @param GameActiveSpellFacts $facts Загрузка строки.
     * @param GameActiveSpellRepository $spells Строки поддержания.
     * @param GameActiveSpellChat $chat Чат.
     * @param GameTurnAccess $access Право хода.
     * @param GameActiveSpellCharge $activeCharge Заряд поддержания.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActiveSpellFacts $facts,
        private readonly GameActiveSpellRepository $spells,
        private readonly GameActiveSpellChat $chat,
        private readonly GameTurnAccess $access,
        private readonly GameActiveSpellCharge $activeCharge,
    ) {
    }

    /**
     * Снимает строку и пишет чат.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array{type: string, id: int} $current Текущий участник.
     * @param int $expectedBattle Ожидаемая версия боя.
     * @param array<string, mixed> $body Тело команды.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameBattleConflictException Если версия боя или строки другая.
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если кастер чужой или поле битое.
     */
    public function drop(
        GameRecord $game,
        GameBattleRecord $battle,
        array $current,
        int $expectedBattle,
        array $body,
        int $actorUserId,
        bool $editAll,
    ): array {
        if ($battle->getStateVersion() !== $expectedBattle) {
            throw new GameBattleConflictException($battle->getStateVersion());
        }

        $record = $this->facts->load(
            $battle->getId(),
            is_int($body['spellId'] ?? null) ? $body['spellId'] : 0,
            is_int($body['expectedSpellVersion'] ?? null) ? $body['expectedSpellVersion'] : -1,
            $current,
            $this->access->isWriter($game, $actorUserId, $editAll),
        );
        $thread = GameCombatThread::buildTurn($battle->getId(), $battle->getRound(), $battle->getActiveIndex());
        $prepared = $this->facts->prepare($game, $record);
        $sheetVersion = $this->activeCharge->removeFor($game, $prepared);
        $this->spells->deleteWithVersion($record->getId(), $record->getStateVersion());
        $this->chat->droppedAt($game, $actorUserId, $record, $thread);

        return [
            'dropped' => true,
            'battleId' => $battle->getId(),
            'version' => $battle->getStateVersion(),
            'sheetVersion' => $sheetVersion,
        ];
    }
}
