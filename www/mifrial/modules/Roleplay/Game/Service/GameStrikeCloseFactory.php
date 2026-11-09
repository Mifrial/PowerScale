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
     * @param GameSpellTouchFinish|null $spellTouch Заклинание касания.
     *
     * @return GameStrikeClose Закрытие.
     */
    public function create(
        GameStrikeRules $rules,
        GameStrikeShared $shared,
        GameStrikeConsequences $consequences,
        GameStateIncreaseChecks $stateChecks,
        GameProcessFlow $processes,
        ?GameSpellTouchFinish $spellTouch = null,
    ): GameStrikeClose {
        $wiring = new GameStrikeCloseWiring($rules, $shared, $consequences, $stateChecks, $processes, $spellTouch);

        return new GameStrikeClose(
            $shared->repos->commands,
            $shared->replay,
            $wiring->single(),
            $wiring->wide(),
        );
    }
}
