<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet\Spec;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterDonorGrant;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;

/**
 * Гранты выбранных и расовых способностей при данных уровнях.
 */
final class CharacterAbilityGrantReader
{
    /**
     * Создаёт чтение грантов.
     *
     * @param CharacterDonorGrants $donorGrants Гранты донора.
     * @param CharacterRaceAbilityCodes $raceAbilityCodes Коды расы.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterDonorGrants $donorGrants,
        private readonly CharacterRaceAbilityCodes $raceAbilityCodes,
    ) {
    }

    /**
     * Гранты выбранных и расовых способностей.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, int> $abilityLevels Уровни.
     *
     * @return array<int, CharacterDonorGrant> Гранты.
     */
    public function grants(CharacterRuleSlice $slice, CharacterChoices $choices, array $abilityLevels): array
    {
        $rows = [];
        foreach ($abilityLevels as $code => $level) {
            $rule = $slice->findLive($code);
            if ($rule !== null) {
                array_push($rows, ...$this->donorGrants->getForLevel($rule, $code, $level));
            }
        }

        foreach ($this->raceAbilityCodes->getCodes($slice, $choices->getRaceCode()) as $code) {
            if (isset($abilityLevels[$code])) {
                continue;
            }

            $rule = $slice->findLive($code);
            if ($rule !== null) {
                array_push($rows, ...$this->donorGrants->getAll($rule, $code));
            }
        }

        return $rows;
    }
}
