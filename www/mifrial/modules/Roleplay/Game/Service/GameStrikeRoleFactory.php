<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

/**
 * Сборка ролей одиночного удара.
 */
final class GameStrikeRoleFactory
{
    /**
     * Создаёт сборку ролей.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeShared $shared Общие сервисы.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeShared $shared,
    ) {
    }

    /**
     * Собирает объявление удара.
     *
     * @param GameStrikeBody $body Разбор выбора.
     * @param GameStrikeConsequences $consequences Последствия.
     * @param GameCardAvailability $availability Доступность карточки.
     * @param GameProcessFlow $processes Сессия процесса.
     *
     * @return GameStrikeDeclare Объявление.
     */
    public function declare(
        GameStrikeBody $body,
        GameStrikeConsequences $consequences,
        GameCardAvailability $availability,
        GameProcessFlow $processes,
    ): GameStrikeDeclare {
        return new GameStrikeDeclare(
            $body,
            $this->shared->ready,
            $this->openFlow($consequences, $availability, $processes),
        );
    }

    /**
     * Собирает окно удара.
     *
     * @param GameCheckRoll $checkRoll Бросок проверки.
     * @param GameStrikeClose $closeService Закрытие удара.
     *
     * @return GameStrikeWindow Окно.
     */
    public function window(GameCheckRoll $checkRoll, GameStrikeClose $closeService): GameStrikeWindow
    {
        $battles = new GameStrikeBattleLookup($this->shared->repos->battles);
        $lookup = new GameStrikeWindowLookup($this->shared->repos->actions);
        $maneuver = new GameStrikeManeuver(
            $this->shared->repos->actions,
            $this->shared->repos->battles,
            $this->shared->sheets,
        );

        return new GameStrikeWindow(
            $this->shared->ready,
            $this->shared->access,
            $lookup,
            new GameStrikeManeuverWindow($this->shared->access, $battles, $maneuver, $checkRoll, $closeService),
            new GameStrikeWindowResume($lookup, $closeService, $battles),
            $this->shared->announce,
        );
    }

    /**
     * Собирает закрытие удара.
     *
     * @param GameStrikeBody $body Разбор защиты.
     * @param GameStrikeClose $closeService Закрытие.
     *
     * @return GameStrikeCloseRunner Закрытие удара.
     */
    public function closeRunner(GameStrikeBody $body, GameStrikeClose $closeService): GameStrikeCloseRunner
    {
        return new GameStrikeCloseRunner($body, $this->shared->ready, $closeService, $this->shared->announce);
    }

    /**
     * Собирает открытие удара.
     *
     * @param GameStrikeConsequences $consequences Последствия.
     * @param GameCardAvailability $availability Доступность карточки.
     * @param GameProcessFlow $processes Сессия процесса.
     *
     * @return GameStrikeOpenFlow Открытие.
     */
    private function openFlow(
        GameStrikeConsequences $consequences,
        GameCardAvailability $availability,
        GameProcessFlow $processes,
    ): GameStrikeOpenFlow {
        $checks = $this->checks($availability);
        $transaction = $this->transaction($consequences, $processes);

        return new GameStrikeOpenFlow(
            $this->shared->repos->actions,
            $this->shared->access,
            new GameStrikeBattleLookup($this->shared->repos->battles),
            new GameStrikeOpenPreparation($processes, $this->rules, $checks, $availability),
            $transaction,
            $this->shared->announce,
        );
    }

    /**
     * Собирает проверки выбора.
     *
     * @param GameCardAvailability $availability Доступность карточки.
     *
     * @return GameStrikeOpenChecks Проверки.
     */
    private function checks(GameCardAvailability $availability): GameStrikeOpenChecks
    {
        return new GameStrikeOpenChecks(
            $this->rules,
            $availability,
            $this->shared->sheets,
            $this->shared->repos->npcs,
        );
    }

    /**
     * Собирает транзакцию открытия.
     *
     * @param GameStrikeConsequences $consequences Последствия.
     * @param GameProcessFlow $processes Сессия процесса.
     *
     * @return GameStrikeOpenTransaction Транзакция.
     */
    private function transaction(
        GameStrikeConsequences $consequences,
        GameProcessFlow $processes,
    ): GameStrikeOpenTransaction {
        return new GameStrikeOpenTransaction(
            $this->shared->replay,
            $this->shared->repos->commands,
            $this->openService($processes),
            new GameStrikeOpenAnnouncement(
                $this->shared->chat,
                $consequences,
                $this->shared->spend,
                $this->rules,
            ),
        );
    }

    /**
     * Собирает сервис открытия.
     *
     * @param GameProcessFlow $processes Сессия процесса.
     *
     * @return GameStrikeOpen Открытие.
     */
    private function openService(GameProcessFlow $processes): GameStrikeOpen
    {
        return (new GameStrikeOpenFactory())->create(
            $this->rules,
            $this->shared->repos->battles,
            $this->shared->repos->actions,
            $this->shared->spend,
            $this->shared->sheets,
            $this->shared->sheetWrites,
            $processes,
        );
    }
}
