<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameActiveEffectRecord;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameActiveEffectRepository;

/**
 * Тик длительных эффектов в конце хода их цели.
 */
final class GameActiveEffectTick
{
    /**
     * Создаёт тик.
     *
     * @param GameActiveEffectRepository $effects Строки эффектов.
     * @param GameActiveEffectChat $chat Чат.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActiveEffectRepository $effects,
        private readonly GameActiveEffectChat $chat,
    ) {
    }

    /**
     * Уменьшает срок эффектов актора, чей ход закончился.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой до сдвига курсора.
     * @param array{type: string, id: int} $actor Актор.
     * @param int $actorUserId Пользователь.
     * @param array{id: string, parentId: string, kind: string} $thread Нить хода.
     *
     * @return void
     *
     * @throws GameBattleConflictException Если версия строки другая.
     * @throws GameInvalidException Если строка битая.
     * @throws GameNotFoundException Если строки нет.
     */
    public function tick(
        GameRecord $game,
        GameBattleRecord $battle,
        array $actor,
        int $actorUserId,
        array $thread,
    ): void {
        $actorKey = $actor['type'] . ':' . $actor['id'];
        foreach ($this->effects->findForTarget($battle->getId(), $actor['type'], $actor['id']) as $effect) {
            if ($this->isCreationTurn($effect, $battle, $actorKey)) {
                continue;
            }

            $left = $effect->getTurnsLeft();
            if ($left === null) {
                continue;
            }

            if ($left <= 1) {
                $this->effects->deleteWithVersion($effect->getId(), $effect->getStateVersion());
                $this->chat->expiredAt($game, $actorUserId, $effect, $thread);

                continue;
            }

            $this->effects->updateTurnsLeft($effect->getId(), $effect->getStateVersion(), $left - 1);
        }
    }

    /**
     * Эффект наложен на себя в этот же ход: первый конец хода не тикает.
     *
     * @param GameActiveEffectRecord $effect Строка.
     * @param GameBattleRecord $battle Бой.
     * @param string $actorKey Ключ актора.
     *
     * @return bool true, если тик пропускается.
     */
    private function isCreationTurn(GameActiveEffectRecord $effect, GameBattleRecord $battle, string $actorKey): bool
    {
        return $effect->getStartedParticipant() === $actorKey
            && $effect->getStartedRound() === $battle->getRound();
    }
}
