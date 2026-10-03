<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterProblemList;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\CharacterValidation;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterOsSteps;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterActiveInput;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterCharacteristicPurchases;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterEquippedItems;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterGrantCoverage;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterInputChecks;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterMechanicBindings;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterMechanicSupport;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterStudyPairs;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterRaceAbilityCodes;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterSpecReader;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;

/**
 * Оркестратор сборки листа C4.
 */
final class CharacterSheets implements ICharacterSheets
{
    private readonly CharacterInputChecks $inputChecks;

    private readonly CharacterRaceAbilityCodes $raceAbilityCodes;

    private readonly CharacterGrantCoverage $grantCoverage;

    private readonly CharacterStudyPairs $studyPairs;

    private readonly CharacterEquippedItems $equippedItems;

    private readonly CharacterMechanicSupport $mechanicSupport;

    private readonly CharacterCharacteristicPurchases $purchases;

    /**
     * Собирает части сборки. Снаружи только шаг доплаты и каталог механик.
     *
     * @param ICharacterOsSteps $osSteps Доплата.
     * @param IMechanics $mechanics Каталог механик.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacterOsSteps $osSteps,
        IMechanics $mechanics,
    ) {
        $specReader = new CharacterSpecReader();
        $donorGrants = new CharacterDonorGrants();
        $this->inputChecks = new CharacterInputChecks(new CharacterActiveInput());
        $this->purchases = new CharacterCharacteristicPurchases();
        $this->raceAbilityCodes = new CharacterRaceAbilityCodes();
        $this->grantCoverage = new CharacterGrantCoverage($donorGrants, $specReader);
        $this->studyPairs = new CharacterStudyPairs();
        $this->equippedItems = new CharacterEquippedItems();
        $this->mechanicSupport = new CharacterMechanicSupport($mechanics, new CharacterMechanicBindings());
    }

    /**
     * Строит снимок и problems. Запись персонажа не делает.
     *
     * @param CharacterRuleSlice $slice Уже загруженный срез.
     * @param CharacterChoices $choices Выборы.
     *
     * @return CharacterValidation Снимок и отказы.
     */
    public function validate(CharacterRuleSlice $slice, CharacterChoices $choices): CharacterValidation
    {
        $problems = new CharacterProblemList();
        $this->inputChecks->check($slice, $choices, $problems);
        $this->studyPairs->check($choices, $problems);
        $this->mechanicSupport->check($slice, $problems);
        $levels = $this->getAbilityLevels($choices);
        $racialCodes = $this->raceAbilityCodes->getCodes($slice, $choices->getRaceCode());
        $covered = $this->grantCoverage->check($slice, $choices, $problems);
        $equipped = $this->equippedItems->getModifiers($slice, $choices, $problems);
        $purchased = $this->purchases->resolve($slice, $choices, $problems);
        $context = $this->osSteps->runOsSteps($slice, $levels, $racialCodes);

        return new CharacterValidation(
            $problems->all(),
            $levels,
            $racialCodes,
            $context->getOsSurchargeTotal(),
            $equipped,
            $covered,
            $purchased,
            $choices->isActive(),
        );
    }

    /**
     * Максимум уровня выборов. automatic уровень не добавляет.
     *
     * @param CharacterChoices $choices Выборы.
     *
     * @return array<string, int> Код → уровень.
     */
    private function getAbilityLevels(CharacterChoices $choices): array
    {
        $levels = [];
        foreach ($choices->getAbilities() as $ability) {
            $code = $ability->getRuleCode();
            $current = $levels[$code] ?? 0;
            if ($ability->getLevel() > $current) {
                $levels[$code] = $ability->getLevel();
            }
        }

        return $levels;
    }
}
