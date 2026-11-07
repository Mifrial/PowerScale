<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

/**
 * Сборка закрытия удара.
 */
final class GameStrikeCloseFactory
{
    /**
     * Создаёт закрытие удара.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeShared $shared Общие сервисы.
     * @param GameStrikeConsequences $consequences Последствия.
     * @param GameStateIncreaseChecks $stateChecks Проверки прироста.
     * @param GameProcessFlow $processes Сессия процесса.
     *
     * @return GameStrikeClose Закрытие.
     */
    public function create(
        GameStrikeRules $rules,
        GameStrikeShared $shared,
        GameStrikeConsequences $consequences,
        GameStateIncreaseChecks $stateChecks,
        GameProcessFlow $processes,
    ): GameStrikeClose {
        return new GameStrikeClose(
            $rules,
            $shared->repos->battles,
            $shared->repos->actions,
            $shared->repos->commands,
            $shared->access,
            $shared->sheets,
            $shared->sheetWrites,
            new GameStrikeResolution($rules, $shared->sheets, $shared->repos->npcs, $consequences),
            $shared->spend,
            $shared->chat,
            $consequences,
            new GameStrikeAmounts(),
            $shared->replay,
            new GamePushRules($rules),
            $stateChecks,
            $processes,
        );
    }
}
