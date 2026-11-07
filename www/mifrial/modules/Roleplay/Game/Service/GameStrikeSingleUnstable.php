<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameStrikeSingleRun;

/**
 * Состояние неустойчивости от толчка в операциях листа.
 */
final class GameStrikeSingleUnstable
{
    /**
     * Создаёт расчёт неустойчивости.
     *
     * @param GameStrikeSheets $sheets Листы.
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     * @param GameStateIncreaseChecks $stateChecks Проверки прироста.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeSheets $sheets,
        private readonly GameStrikeSheetWrites $sheetWrites,
        private readonly GameStateIncreaseChecks $stateChecks,
    ) {
    }

    /**
     * Добавляет состояние неустойчивости к операциям листа.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param string $unstableCode Код состояния.
     * @param int $unstable Значение.
     *
     * @return void
     */
    public function apply(GameStrikeSingleRun $run, string $unstableCode, int $unstable): void
    {
        if (!is_array($run->prepared)) {
            return;
        }

        $run->prepared['operations'][] = [
            'kind' => 'putState',
            'stateRuleCode' => $unstableCode,
            'value' => $unstable,
        ];
        $game = $run->input->game;
        $preview = $this->preview($run);
        $planned = $this->stateChecks->plan(
            $game,
            $preview['sheet'],
            $preview['rulesRevision'],
            $preview['states'],
            $run->prepared['operations'],
            [$unstableCode],
        );
        $run->prepared['operations'] = $planned['operations'];
        foreach ($planned['rolls'] as $entry) {
            $run->pushChecks[] = $entry;
        }
    }

    /**
     * Предпросмотр документа листа с состоянием.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return array{sheet: array<string, mixed>, rulesRevision: int, states: array<string, mixed>} Снимок.
     */
    private function preview(GameStrikeSingleRun $run): array
    {
        $game = $run->input->game;
        $snapshot = $this->sheets->snapshot($game, $run->target['type'], $run->target['id']);
        $document = [
            'rulesRevision' => $game->getRulesRevision(),
            'choices' => is_array($snapshot['sheet']['choices'] ?? null) ? $snapshot['sheet']['choices'] : [],
            'sheet' => is_array($snapshot['sheet']['sheet'] ?? null) ? $snapshot['sheet']['sheet'] : [],
        ];
        $preview = $this->sheetWrites->previewDocument($game, $document, $run->prepared['operations'] ?? []);

        return [
            'sheet' => $document['sheet'],
            'rulesRevision' => $preview['rulesRevision'],
            'states' => $preview['states'],
        ];
    }
}
