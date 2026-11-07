<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;

/**
 * Сборка правил, броска, проверок и последствий удара.
 */
final class GameStrikeRuleWiring
{
    private readonly GameStrikePorts $ports;

    private readonly GameStrikeCharacterPorts $characters;

    private readonly GameStrikeMechanicPorts $mechanics;

    /**
     * Создаёт сборку расчётных сервисов.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return void
     */
    public function __construct(IServiceLocator $serviceLocator)
    {
        $this->ports = new GameStrikePorts($serviceLocator);
        $this->characters = new GameStrikeCharacterPorts($serviceLocator);
        $this->mechanics = new GameStrikeMechanicPorts($serviceLocator);
    }

    /**
     * Правила удара со слоями.
     *
     * @return GameStrikeRules Правила.
     */
    public function rules(): GameStrikeRules
    {
        $slices = $this->slices();
        $characters = $this->characters->characters();
        $participants = new GameStrikeParticipants(
            $characters,
            $this->characters->formulaContexts(),
            $this->characters->characterSheets(),
            $slices,
        );

        return new GameStrikeRules(
            $this->mechanics->formulaEvaluations(),
            $this->checkRoll(),
            $this->layers(),
            $slices,
            $characters,
            $participants,
        );
    }

    /**
     * Бросок попадания.
     *
     * @return GameCheckRoll Расчёт.
     */
    public function checkRoll(): GameCheckRoll
    {
        return new GameCheckRoll(
            $this->characters->ruleSlices(),
            $this->characters->characters(),
            $this->mechanics->mechanics(),
            $this->mechanics->rolls(),
            $this->characters->formulaContexts(),
            $this->characters->characterSheets(),
        );
    }

    /**
     * Проверки прироста состояний: кубик и связка из спеки.
     *
     * @return GameStateIncreaseChecks Исполнитель.
     */
    public function checks(): GameStateIncreaseChecks
    {
        return new GameStateIncreaseChecks(
            $this->rules(),
            new GameStateCheck($this->checkRoll(), new GameStateIncreaseRule()),
            new GameStateDecline(),
        );
    }

    /**
     * Последствия удара со слоями.
     *
     * @param GameStrikeRules $rules Правила.
     *
     * @return GameStrikeConsequences Последствия.
     */
    public function consequences(GameStrikeRules $rules): GameStrikeConsequences
    {
        $states = $this->states();
        $decline = new GameStateDecline();
        $will = $this->will($states, $decline);

        return new GameStrikeConsequences(
            $rules,
            $states,
            $will,
            $this->effects($rules, $states, $will, $decline),
            new GameStrikeConsequenceChat($this->ports->chats()),
        );
    }

    /**
     * Срезы ревизии для удара.
     *
     * @return GameStrikeSlices Срезы.
     */
    private function slices(): GameStrikeSlices
    {
        return new GameStrikeSlices($this->characters->ruleSlices());
    }

    /**
     * Сопротивление слоёв.
     *
     * @return GameStrikeLayers Расчёт.
     */
    private function layers(): GameStrikeLayers
    {
        return new GameStrikeLayers(
            $this->characters->combatLayers(),
            $this->mechanics->engine(),
            $this->mechanics->mechanics(),
            new GameStrikeAmounts(),
        );
    }

    /**
     * Состояния последствий.
     *
     * @return GameStrikeConsequenceStates Состояния.
     */
    private function states(): GameStrikeConsequenceStates
    {
        return new GameStrikeConsequenceStates(
            $this->mechanics->mechanics(),
            $this->mechanics->engine(),
        );
    }

    /**
     * Проверка воли последствий.
     *
     * @param GameStrikeConsequenceStates $states Состояния.
     * @param GameStateDecline $decline Отказ.
     *
     * @return GameStrikeConsequenceWill Воля.
     */
    private function will(GameStrikeConsequenceStates $states, GameStateDecline $decline): GameStrikeConsequenceWill
    {
        $checkRoll = $this->checkRoll();

        return new GameStrikeConsequenceWill(
            $states,
            new GameStateCheck($checkRoll, new GameStateIncreaseRule()),
            $decline,
            $checkRoll,
        );
    }

    /**
     * Эффекты последствий.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeConsequenceStates $states Состояния.
     * @param GameStrikeConsequenceWill $will Воля.
     * @param GameStateDecline $decline Отказ.
     *
     * @return GameStrikeConsequenceEffects Эффекты.
     */
    private function effects(
        GameStrikeRules $rules,
        GameStrikeConsequenceStates $states,
        GameStrikeConsequenceWill $will,
        GameStateDecline $decline,
    ): GameStrikeConsequenceEffects {
        return new GameStrikeConsequenceEffects(
            $rules,
            $states,
            new GameStrikeConsequenceRules($this->characters->formulaContexts(), $states),
            $will,
            $decline,
        );
    }
}
