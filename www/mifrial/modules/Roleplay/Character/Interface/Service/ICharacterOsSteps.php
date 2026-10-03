<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Interface\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Mechanic\Dto\CharacterMechanicContext;

/**
 * Шаг «Основа»: binding среза и узкий снимок в порт Mechanic Engine.
 */
interface ICharacterOsSteps
{
    /**
     * Считает доплату character.osSteps. Запись персонажа не делает.
     *
     * @param CharacterRuleSlice $slice Уже загруженный срез.
     * @param array<string, int> $abilityLevels Уровни по коду способности, в порядке вставки.
     * @param array<int, string> $racialAbilityCodes Коды способностей выбранной расы.
     *
     * @return CharacterMechanicContext Контекст с аккумуляторами доплаты.
     */
    public function runOsSteps(
        CharacterRuleSlice $slice,
        array $abilityLevels,
        array $racialAbilityCodes,
    ): CharacterMechanicContext;
}
