<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterProblemList;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\CharacterValidation;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterOsSteps;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Character\Service\Save\CharacterChoiceAssembler;
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

    private readonly CharacterChoiceAssembler $choiceAssembler;

    /**
     * Собирает части сборки. Снаружи шаг доплаты, каталог механик и срез.
     *
     * @param ICharacterOsSteps $osSteps Доплата.
     * @param IMechanics $mechanics Каталог механик.
     * @param ICharacterRuleSlices $ruleSlices Срезы мира.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacterOsSteps $osSteps,
        IMechanics $mechanics,
        private readonly ICharacterRuleSlices $ruleSlices,
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
        $this->choiceAssembler = new CharacterChoiceAssembler();
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
     * Пустой validate сохранённого документа choices. Запись не делает.
     *
     * @param int $spaceId Мир листа.
     * @param int $rulesRevision Ревизия листа.
     * @param array<string, mixed> $choices Документ choices.
     *
     * @return bool true, если отказов нет.
     *
     * @throws CharacterInvalidException Если срез битый.
     * @throws CharacterNotFoundException Если ревизии нет.
     */
    public function acceptsStoredChoices(int $spaceId, int $rulesRevision, array $choices): bool
    {
        $assembled = $this->storedChoices($choices);
        if ($assembled === null) {
            return false;
        }

        $slice = $this->ruleSlices->get($spaceId, $rulesRevision);

        return $this->validate($slice, $assembled)->getProblems() === [];
    }

    /**
     * Документ в выборы. Битый код расы или список — null.
     *
     * @param array<string, mixed> $choices Документ.
     *
     * @return CharacterChoices|null Выборы или null.
     */
    private function storedChoices(array $choices): ?CharacterChoices
    {
        $name = $choices['name'] ?? '';
        $raceCode = $choices['raceCode'] ?? null;
        $abilities = $choices['abilities'] ?? [];
        $inventory = $choices['inventory'] ?? [];
        $purchases = $choices['characteristicPurchases'] ?? [];
        $customRules = $choices['customRules'] ?? [];
        $active = array_key_exists('active', $choices) ? $choices['active'] : null;
        if (!$this->storedShape($name, $raceCode, $abilities, $inventory, $purchases, $customRules, $active)) {
            return null;
        }

        return $this->choiceAssembler->assemble(
            $name,
            $raceCode,
            $abilities,
            $inventory,
            $purchases,
            $customRules,
            $active,
        );
    }

    /**
     * Документ годится для assemble.
     *
     * @param mixed $name Имя.
     * @param mixed $raceCode Код расы.
     * @param mixed $abilities Способности.
     * @param mixed $inventory Предметы.
     * @param mixed $purchases Закупки.
     * @param mixed $customRules Свои правила.
     * @param mixed $active Признак.
     *
     * @return bool true, если типы сходятся.
     */
    private function storedShape(
        mixed $name,
        mixed $raceCode,
        mixed $abilities,
        mixed $inventory,
        mixed $purchases,
        mixed $customRules,
        mixed $active,
    ): bool {
        $lists = is_array($abilities) && is_array($inventory) && is_array($purchases) && is_array($customRules);
        $flag = $active === null || is_bool($active);

        return is_string($name) && is_string($raceCode) && $lists && $flag;
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
