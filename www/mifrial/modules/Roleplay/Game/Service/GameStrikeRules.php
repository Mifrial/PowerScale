<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;

/**
 * Реестр сервисов удара: доступ к данным и расчётные роли.
 */
final class GameStrikeRules
{
    private readonly GameStrikeSoaks $soaks;

    private readonly GameStrikeSplits $splits;

    private readonly GameActionResourceResolver $resourceResolver;

    private readonly GameStrikeSuccessModifier $successModifier;

    private readonly GameStrikeAttackFactory $attacks;

    private GameStrikeMastery $mastery;

    public GameStrikeData $data;

    public GameStrikeMovement $movement;

    public GameStrikeEquipment $equipment;

    public GameStrikeDamage $damage;

    public GameStrikeDistance $distance;

    public GameStrikeDefense $defense;

    public GameStrikeRolls $rolls;

    public GameStrikeHitCheck $hitCheck;

    public GameStrikeModifiers $modifiers;

    public GameStrikeActionCards $cards;

    public GameStrikeChecks $checks;

    public GameStrikeSpends $spends;

    public GameStrikeOutcome $outcome;

    /**
     * Создаёт реестр.
     *
     * @param IFormulaEvaluations $formulaEvaluations Обход формулы.
     * @param GameCheckRoll $hitRoll Бросок попадания.
     * @param GameStrikeLayers $layers Сопротивление слоёв.
     * @param GameStrikeSlices $slices Срезы ревизии.
     * @param ICharacters $characters Листы персонажей.
     * @param GameStrikeParticipants $participants Участники удара.
     *
     * @return void
     */
    public function __construct(
        private readonly IFormulaEvaluations $formulaEvaluations,
        private readonly GameCheckRoll $hitRoll,
        private readonly GameStrikeLayers $layers,
        private readonly GameStrikeSlices $slices,
        private readonly ICharacters $characters,
        private readonly GameStrikeParticipants $participants,
    ) {
        $this->soaks = new GameStrikeSoaks();
        $this->splits = new GameStrikeSplits();
        $this->resourceResolver = new GameActionResourceResolver();
        $this->successModifier = new GameStrikeSuccessModifier();
        $this->movement = new GameStrikeMovement();
        $this->equipment = new GameStrikeEquipment($slices);
        $this->data = new GameStrikeData($slices, $participants);
        $this->attacks = new GameStrikeAttackFactory(
            $slices,
            $participants,
            $this->equipment,
            new GameStrikeProfileContext($formulaEvaluations),
        );
        $this->initDamage($formulaEvaluations);
        $this->initRolls($hitRoll, $slices, $participants, $layers);
        $this->initOutcome($slices, $participants, $characters);
    }

    /**
     * Собирает расчёт урона и дистанции.
     *
     * @param IFormulaEvaluations $formulaEvaluations Обход формулы.
     *
     * @return void
     */
    private function initDamage(IFormulaEvaluations $formulaEvaluations): void
    {
        $this->distance = new GameStrikeDistance($formulaEvaluations, $this->movement);
        $this->damage = new GameStrikeDamage($formulaEvaluations, $this->attacks);
        $this->cards = new GameStrikeActionCards($this->slices, $this->participants);
        $this->checks = new GameStrikeChecks(
            $this->slices,
            $this->participants,
            $this->equipment,
            $this->cards,
        );
        $this->mastery = new GameStrikeMastery($this->participants, $this->equipment);
    }

    /**
     * Собирает броски и защиту.
     *
     * @param GameCheckRoll $hitRoll Бросок попадания.
     * @param GameStrikeSlices $slices Срезы ревизии.
     * @param GameStrikeParticipants $participants Участники.
     * @param GameStrikeLayers $layers Слои.
     *
     * @return void
     */
    private function initRolls(
        GameCheckRoll $hitRoll,
        GameStrikeSlices $slices,
        GameStrikeParticipants $participants,
        GameStrikeLayers $layers,
    ): void {
        $this->hitCheck = new GameStrikeHitCheck($hitRoll, $slices);
        $this->defense = new GameStrikeDefense($slices, $participants, $layers, $this->soaks, $this->cards);
        $this->rolls = new GameStrikeRolls(
            $hitRoll,
            $slices,
            $participants,
            $this->equipment,
            $this->mastery,
            $this->checks,
        );
    }

    /**
     * Собирает модификаторы, списания и исход.
     *
     * @param GameStrikeSlices $slices Срезы ревизии.
     * @param GameStrikeParticipants $participants Участники.
     * @param ICharacters $characters Листы персонажей.
     *
     * @return void
     */
    private function initOutcome(
        GameStrikeSlices $slices,
        GameStrikeParticipants $participants,
        ICharacters $characters,
    ): void {
        $this->modifiers = new GameStrikeModifiers($this->successModifier);
        $this->spends = new GameStrikeSpends(
            $slices,
            $this->resourceResolver,
            new GameStrikeInventorySpend($slices, $this->equipment, $characters),
        );
        $this->outcome = new GameStrikeOutcome(
            $slices,
            $participants,
            $this->splits,
            $this->equipment,
            $this->mastery,
        );
    }
}
