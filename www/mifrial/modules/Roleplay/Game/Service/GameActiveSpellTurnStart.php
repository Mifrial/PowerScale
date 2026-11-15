<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameActiveSpellRecord;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameActiveSpellRepository;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Вопрос поддержания и потеря источника на входе хода.
 */
final class GameActiveSpellTurnStart
{
    /**
     * Создаёт сценарий.
     *
     * @param GameActiveSpellRepository $spells Строки поддержания.
     * @param GameActiveSpellFacts $facts Снимок карточки и источника.
     * @param GameActiveSpellChat $chat Чат.
     * @param GameActiveSpellView $view JSON.
     * @param GameActiveSpellCharge $activeCharge Заряд поддержания.
     * @param GameSpellStability $stability Стабильность эффекта.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActiveSpellRepository $spells,
        private readonly GameActiveSpellFacts $facts,
        private readonly GameActiveSpellChat $chat,
        private readonly GameActiveSpellView $view,
        private readonly GameActiveSpellCharge $activeCharge,
        private readonly GameSpellStability $stability,
    ) {
    }

    /**
     * Снимает эффекты без источника и отдаёт вопросы для диалога.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой после сдвига курсора.
     * @param array{type: string, id: int} $current Новый текущий.
     * @param int $actorUserId Пользователь, закрывший ход.
     *
     * @return array<int, array<string, mixed>> Вопросы поддержания.
     *
     * @throws GameInvalidException Если карточка или лист битые.
     * @throws GameNotFoundException Если листа кастера нет.
     */
    public function prompts(
        GameRecord $game,
        GameBattleRecord $battle,
        array $current,
        int $actorUserId,
    ): array {
        $casterKey = $current['type'] . ':' . $current['id'];
        $thread = GameCombatThread::buildTurn($battle->getId(), $battle->getRound(), $battle->getActiveIndex());
        $prompts = [];
        foreach ($this->spells->findForBattle($battle->getId()) as $record) {
            if ($record->getCasterKey() !== $casterKey) {
                continue;
            }

            $prepared = $this->facts->prepare($game, $record);
            if (!$prepared->sourceAvailable) {
                $this->activeCharge->removeFor($game, $prepared);
                $this->spells->deleteWithVersion($record->getId(), $record->getStateVersion());
                $this->chat->droppedAt($game, $actorUserId, $record, $thread, true);

                continue;
            }

            if ($this->isCreationTurn($record, $battle, $casterKey)) {
                continue;
            }

            $power = $record->getSustainPower();
            $stability = $this->stability->value(
                $prepared->card,
                $record->getParameterValues(),
                new CharacteristicNumber($power['base'], $power['size']),
            );
            $prompts[] = $this->view->prompt($record, $prepared->defaultSustainPower, $stability);
        }

        return $prompts;
    }

    /**
     * Заклинание создано в этот же ход.
     *
     * @param GameActiveSpellRecord $record Строка.
     * @param GameBattleRecord $battle Бой.
     * @param string $casterKey Ключ кастера.
     *
     * @return bool true, если вопрос не нужен.
     */
    private function isCreationTurn(GameActiveSpellRecord $record, GameBattleRecord $battle, string $casterKey): bool
    {
        return $record->getStartedRound() === $battle->getRound()
            && $record->getStartedParticipant() === $casterKey;
    }
}
