<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

/**
 * Сборка одиночного и широкого закрытия удара.
 */
final class GameStrikeCloseWiring
{
    private readonly GameStrikeRules $rules;

    private readonly GameStrikeShared $shared;

    private readonly GameStrikeConsequences $consequences;

    private readonly GameStateIncreaseChecks $stateChecks;

    private readonly GameProcessFlow $processes;

    private readonly GamePushRules $push;

    private readonly GameStrikeAmounts $amounts;

    private readonly GameStrikeResolution $resolution;

    private readonly GameStrikeCloseRows $rows;

    private readonly GameStrikeTargetChoices $choices;

    private readonly GameStrikeSupport $support;

    private readonly GameStrikeCover $cover;

    private readonly GameStrikeDeclaredSpend $declaredSpend;

    private readonly GameStrikeTargetClose $targets;

    private readonly GameActionModifierRunner $runner;

    private readonly GameStrikeSingleChat $singleChat;

    private readonly GameStrikeWideChat $wideChat;

    /**
     * Создаёт сборку закрытия.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeShared $shared Общие сервисы.
     * @param GameStrikeConsequences $consequences Последствия.
     * @param GameStateIncreaseChecks $stateChecks Проверки прироста.
     * @param GameProcessFlow $processes Сессия процесса.
     *
     * @return void
     */
    public function __construct(
        GameStrikeRules $rules,
        GameStrikeShared $shared,
        GameStrikeConsequences $consequences,
        GameStateIncreaseChecks $stateChecks,
        GameProcessFlow $processes,
    ) {
        $this->rules = $rules;
        $this->shared = $shared;
        $this->consequences = $consequences;
        $this->stateChecks = $stateChecks;
        $this->processes = $processes;
        $this->push = new GamePushRules($rules);
        $this->amounts = new GameStrikeAmounts();
        $this->resolution = new GameStrikeResolution($rules, $shared->sheets, $shared->repos->npcs, $consequences);
        $this->rows = new GameStrikeCloseRows($shared->repos->battles, $shared->repos->actions, $shared->access);
        $this->choices = new GameStrikeTargetChoices($rules, $shared->sheets);
        $this->support = new GameStrikeSupport();
        $this->cover = new GameStrikeCover($rules, $this->rows, $this->resolution);
        $this->declaredSpend = new GameStrikeDeclaredSpend($rules, $shared->repos->actions, $shared->sheets, $shared->sheetWrites, $shared->spend);
        $this->targets = $this->targets();
        $this->runner = $this->runner();
        $this->singleChat = $this->singleChat();
        $this->wideChat = $this->wideChat();
    }

    /**
     * Закрытие одиночного удара.
     *
     * @return GameStrikeSingleClose Закрытие.
     */
    public function single(): GameStrikeSingleClose
    {
        return new GameStrikeSingleClose(
            $this->prepare(),
            $this->resolve(),
            $this->write(),
        );
    }

    /**
     * Закрытие широкого удара.
     *
     * @return GameStrikeWideClose Закрытие.
     */
    public function wide(): GameStrikeWideClose
    {
        $checks = new GameStrikeWideChecks($this->rules, $this->rows, $this->shared->sheets, $this->choices);
        $resume = new GameStrikeWideResume($this->shared->repos->actions, $this->cover);
        $pause = new GameStrikeWidePause(
            $this->shared->sheetWrites,
            $this->shared->repos->actions,
            $this->shared->repos->battles,
        );

        return new GameStrikeWideClose(
            new GameStrikeWideRequest(
                $this->rows,
                $this->shared->access,
                $this->shared->repos->actions,
                $checks,
                $resume,
            ),
            new GameStrikeWideOpening($this->rules, $this->declaredSpend, $this->support, $this->targets, $resume),
            new GameStrikeWidePrepare($this->targets, $this->cover, $checks, $resume, $pause, $this->rows),
            new GameStrikeWideHits($this->rules, $this->amounts, $this->resolution, $this->support, $this->targets),
            new GameStrikeWidePending($this->choices, $this->shared->spend, $this->targets),
            new GameStrikeWideFinalize(
                $this->wideChat,
                $this->shared->repos->actions,
                $this->shared->repos->battles,
                $this->targets,
            ),
        );
    }

    /**
     * Цели широкого удара.
     *
     * @return GameStrikeTargetClose Цели.
     */
    private function targets(): GameStrikeTargetClose
    {
        $reaction = new GameStrikeTargetReaction(
            $this->rules,
            $this->shared->sheets,
            $this->shared->spend,
            $this->choices,
        );
        $write = new GameStrikeTargetWrite($this->shared->sheets, $this->shared->sheetWrites, $this->resolution);
        $rolls = new GameStrikeTargetRolls($this->rules, $this->resolution, $this->amounts);

        return new GameStrikeTargetClose(
            $this->rules,
            $reaction,
            $this->cover,
            new GameStrikeTargetFlow($this->rules, $this->amounts, $reaction, $write, $rolls),
            $rolls,
        );
    }

    /**
     * Исполнение модификаторов действий.
     *
     * @return GameActionModifierRunner Исполнение.
     */
    private function runner(): GameActionModifierRunner
    {
        $ports = $this->modifierPorts();

        return new GameActionModifierRunner(
            $ports,
            new GameActionModifiers(),
            new GameActionModifierPresenter(),
            new GameActionModifierOutcome($ports),
        );
    }

    /**
     * Порты исполнения модификаторов.
     *
     * @return GameActionModifierPorts Порты.
     */
    private function modifierPorts(): GameActionModifierPorts
    {
        return new GameActionModifierPorts(
            $this->rules,
            $this->shared->sheets,
            $this->shared->sheetWrites,
            $this->resolution,
            $this->consequences,
            $this->shared->chat,
            $this->amounts,
        );
    }

    /**
     * Подготовка одиночного удара.
     *
     * @return GameStrikeSinglePrepare Подготовка.
     */
    private function prepare(): GameStrikeSinglePrepare
    {
        return new GameStrikeSinglePrepare(
            new GameStrikeSingleLoad(
                $this->rows,
                $this->rules,
                $this->push,
                $this->cover,
                $this->processes,
                $this->shared->sheets,
            ),
            new GameStrikeSingleStart(
                new GameStrikeManeuver(
                    $this->shared->repos->actions,
                    $this->shared->repos->battles,
                    $this->shared->sheets,
                ),
                $this->choices,
                $this->rules,
            ),
            new GameStrikeSingleDefender(
                $this->rules,
                $this->shared->sheets,
                $this->shared->spend,
                $this->choices,
                $this->declaredSpend,
            ),
            new GameStrikeSingleAdvantages($this->support, $this->resolution, $this->processes),
        );
    }

    /**
     * Расчёт одиночного удара.
     *
     * @return GameStrikeSingleResolve Расчёт.
     */
    private function resolve(): GameStrikeSingleResolve
    {
        return new GameStrikeSingleResolve(
            new GameStrikeSingleCoverPhase(
                $this->cover,
                $this->rows,
                $this->shared->sheetWrites,
                $this->choices,
                $this->shared->repos->actions,
                $this->shared->repos->battles,
            ),
            new GameStrikeSingleRound($this->rules, $this->shared->sheets, $this->resolution, $this->amounts),
            new GameStrikeSingleHits(
                $this->rules,
                $this->shared->sheets,
                $this->resolution,
                $this->amounts,
                $this->push,
                new GameStrikeSingleUnstable($this->shared->sheets, $this->shared->sheetWrites, $this->stateChecks),
            ),
        );
    }

    /**
     * Запись одиночного удара.
     *
     * @return GameStrikeSingleWrite Запись.
     */
    private function write(): GameStrikeSingleWrite
    {
        return new GameStrikeSingleWrite(
            $this->shared->sheetWrites,
            $this->shared->sheets,
            $this->singleChat,
            $this->shared->repos->actions,
            $this->shared->repos->battles,
            new GameStrikeSingleTrail(
                $this->rules,
                $this->runner,
                $this->processes,
                $this->shared->spend,
            ),
        );
    }

    /**
     * Чат одиночного удара.
     *
     * @return GameStrikeSingleChat Чат.
     */
    private function singleChat(): GameStrikeSingleChat
    {
        $view = new GameStrikeSingleView($this->shared->chat, $this->rules);

        return new GameStrikeSingleChat(
            $view,
            new GameStrikeSingleNarrative($this->shared->chat, $this->rules, $this->shared->spend),
            new GameStrikeSingleChecks(
                $this->shared->chat,
                $this->shared->spend,
                $this->consequences,
                $view,
            ),
            new GameStrikeSingleOutcome(
                $this->shared->chat,
                $this->consequences,
                new GameStrikeSinglePush($this->shared->chat),
            ),
        );
    }

    /**
     * Чат широкого удара.
     *
     * @return GameStrikeWideChat Чат.
     */
    private function wideChat(): GameStrikeWideChat
    {
        $coverText = new GameStrikeWideCoverText(
            $this->shared->chat,
            $this->consequences,
            $this->shared->spend,
        );
        $narrative = new GameStrikeWideNarrative(
            $this->shared->chat,
            $this->rules,
            $this->shared->spend,
            $coverText,
        );

        return new GameStrikeWideChat(
            $narrative,
            $coverText,
            new GameStrikeWideOutcome($this->shared->chat, $this->consequences),
            $this->shared->chat,
            $this->consequences,
            $this->shared->spend,
        );
    }
}
