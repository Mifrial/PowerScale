<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Interface\Service;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\CharacterValidation;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;

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

    /**
     * Полный пересчёт производных ключей sheet.
     *
     * @param CharacterRuleSlice $slice Уже загруженный срез.
     * @param array<string, mixed> $choices Документ choices.
     * @param array<string, mixed> $sheet Документ sheet.
     *
     * @return array<string, mixed> Sheet после compute.
     */
    public function compute(CharacterRuleSlice $slice, array $choices, array $sheet): array;

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
    public function acceptsStoredChoices(int $spaceId, int $rulesRevision, array $choices): bool;
}
