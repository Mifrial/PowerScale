<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Interface\Service;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\CharacterValidation;

/**
 * Сборка листа и тот же валидатор, что пойдёт на validate-only.
 */
interface ICharacterSheets
{
    /**
     * Строит снимок и problems. Запись персонажа не делает.
     *
     * @param CharacterRuleSlice $slice Уже загруженный срез.
     * @param CharacterChoices $choices Выборы.
     *
     * @return CharacterValidation Снимок и отказы.
     */
    public function validate(CharacterRuleSlice $slice, CharacterChoices $choices): CharacterValidation;
}
