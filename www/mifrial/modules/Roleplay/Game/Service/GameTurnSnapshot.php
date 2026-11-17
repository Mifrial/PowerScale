<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;

/**
 * Снимок листа текущего участника.
 */
final class GameTurnSnapshot
{
    /**
     * Создаёт снимок.
     *
     * @param GameStrikeRules $rules Срез.
     * @param GameNpcRepository $npcs Лист NPC.
     * @param GameStrikeSheets $sheets Проекция наложенных состояний.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameNpcRepository $npcs,
        private readonly GameStrikeSheets $sheets,
    ) {
    }

    /**
     * Версия, лист и выборы участника.
     *
     * @param array{type: string, id: int} $current Текущий.
     *
     * @return array{actualVersion: int, sheet: array<string, mixed>, choices: array<string, mixed>} Снимок.
     *
     * @throws GameNotFoundException Если NPC нет.
     */
    public function of(array $current): array
    {
        if ($current['type'] === 'character') {
            $snapshot = $this->rules->data->characterSnapshot($current['id']);

            return [
                'actualVersion' => $snapshot['actualVersion'],
                'sheet' => $snapshot['sheet'],
                'choices' => $snapshot['choices'],
            ];
        }

        $npc = $this->npcs->getById($current['id']);
        $version = $npc->getVersion();
        $choices = $version['choices'] ?? [];

        return [
            'actualVersion' => $npc->getActualVersion(),
            'sheet' => $version['sheet'] ?? [],
            'choices' => is_array($choices) ? $choices : [],
        ];
    }

    /**
     * Лист участника с наложенными состояниями цели.
     *
     * Наложенные состояния живут в `game_active_effect` и не пишутся в лист.
     * Концу хода они нужны для тиков и лимитов ресурсов, поэтому расчёт идёт
     * по проекции, а операции пишутся в базовый лист.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $current Текущий.
     * @param array{sheet: array<string, mixed>, choices?: array<string, mixed>} $snapshot Снимок.
     *
     * @return array<string, mixed> Лист с состояниями.
     */
    public function projection(GameRecord $game, array $current, array $snapshot): array
    {
        $choices = $snapshot['choices'] ?? [];

        return $this->sheets->project(
            $game,
            $current['type'],
            $current['id'],
            $snapshot['sheet'],
            $choices,
        );
    }

    /**
     * Срез правил игры.
     *
     * @param GameRecord $game Игра.
     *
     * @return CharacterRuleSlice Срез.
     */
    public function slice(GameRecord $game): CharacterRuleSlice
    {
        return $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
    }
}
