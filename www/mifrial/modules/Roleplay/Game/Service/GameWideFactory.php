<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Interface\Service\IGameWideStrikes;

/**
 * Сборка широкого удара.
 */
final class GameWideFactory
{
    /**
     * Создаёт широкий удар.
     *
     * @param ISmartTableGateway $gateway Шлюз таблиц.
     * @param IGames $games Строка игры.
     * @param ICharacterActualMutations $mutations Порт листа.
     * @param GameStrikeRules $rules Правила.
     * @param GameCardAccess $cardAccess Фильтр карточки.
     * @param IEventManager $events Доставка.
     * @param GameStrikeConsequences $consequences Последствия.
     * @param GameCardAvailability $availability Доступность карточки.
     * @param GameStateIncreaseChecks $stateChecks Проверки прироста.
     * @param GameProcessFlow $processes Сессия процесса.
     *
     * @return IGameWideStrikes Широкий удар.
     */
    public function create(
        ISmartTableGateway $gateway,
        IGames $games,
        ICharacterActualMutations $mutations,
        GameStrikeRules $rules,
        GameCardAccess $cardAccess,
        IEventManager $events,
        GameStrikeConsequences $consequences,
        GameCardAvailability $availability,
        GameStateIncreaseChecks $stateChecks,
        GameProcessFlow $processes,
    ): IGameWideStrikes {
        $shared = new GameStrikeShared($gateway, $games, $mutations, $rules, $cardAccess, $events);
        $battles = new GameStrikeBattleLookup($shared->repos->battles);

        return new GameWideStrikes(
            new GameStrikeBody(),
            $this->open($shared, $rules, $processes, $battles, $availability),
            $this->resolve($shared, $rules, $consequences, $stateChecks, $processes, $battles),
        );
    }

    /**
     * Собирает открытие широкого удара.
     *
     * @param GameStrikeShared $shared Общие сервисы.
     * @param GameStrikeRules $rules Правила.
     * @param GameProcessFlow $processes Сессия процесса.
     * @param GameStrikeBattleLookup $battles Бой.
     * @param GameCardAvailability $availability Доступность карточки.
     *
     * @return GameWideOpen Открытие.
     */
    private function open(
        GameStrikeShared $shared,
        GameStrikeRules $rules,
        GameProcessFlow $processes,
        GameStrikeBattleLookup $battles,
        GameCardAvailability $availability,
    ): GameWideOpen {
        return new GameWideOpen(
            $shared->ready,
            $shared->access,
            $battles,
            new GameWideOpening(
                $shared->access,
                $shared->repos->actions,
                $rules,
                $availability,
                new GameWideChoices($shared->repos->npcs),
            ),
            new GameWideTransaction(
                $shared->replay,
                $shared->repos->commands,
                $this->strikeOpen($shared, $rules, $processes),
            ),
            $shared->announce,
        );
    }

    /**
     * Собирает сервис открытия удара.
     *
     * @param GameStrikeShared $shared Общие сервисы.
     * @param GameStrikeRules $rules Правила.
     * @param GameProcessFlow $processes Сессия процесса.
     *
     * @return GameStrikeOpen Открытие.
     */
    private function strikeOpen(
        GameStrikeShared $shared,
        GameStrikeRules $rules,
        GameProcessFlow $processes,
    ): GameStrikeOpen {
        return (new GameStrikeOpenFactory())->create(
            $rules,
            $shared->repos->battles,
            $shared->repos->actions,
            $shared->spend,
            $shared->sheets,
            $shared->sheetWrites,
            $processes,
        );
    }

    /**
     * Собирает закрытие широкого удара.
     *
     * @param GameStrikeShared $shared Общие сервисы.
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeConsequences $consequences Последствия.
     * @param GameStateIncreaseChecks $stateChecks Проверки прироста.
     * @param GameProcessFlow $processes Сессия процесса.
     * @param GameStrikeBattleLookup $battles Бой.
     *
     * @return GameWideResolve Закрытие.
     */
    private function resolve(
        GameStrikeShared $shared,
        GameStrikeRules $rules,
        GameStrikeConsequences $consequences,
        GameStateIncreaseChecks $stateChecks,
        GameProcessFlow $processes,
        GameStrikeBattleLookup $battles,
    ): GameWideResolve {
        return new GameWideResolve(
            $shared->ready,
            $battles,
            (new GameStrikeCloseFactory())->create($rules, $shared, $consequences, $stateChecks, $processes),
            $shared->announce,
        );
    }
}
