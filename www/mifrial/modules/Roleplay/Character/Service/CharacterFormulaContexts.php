<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Character\Service\Runtime\Character;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;

/**
 * FormulaContext из живых характеристик sheet, иначе из закупок.
 */
final class CharacterFormulaContexts implements ICharacterFormulaContexts
{
    private readonly CharacterSheetFormulaContext $sheetContexts;

    /**
     * Создаёт разбор.
     *
     * @param ICharacters $characters Строка персонажа.
     * @param ?ICharacterRuleSlices $slices Срезы правил или null.
     * @param ?ICharacterSheets $sheets Листы или null.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacters $characters,
        private readonly ?ICharacterRuleSlices $slices = null,
        private readonly ?ICharacterSheets $sheets = null,
    ) {
        $this->sheetContexts = new CharacterSheetFormulaContext();
    }

    /**
     * Собирает контекст из документа sheet.
     *
     * @param array<string, mixed> $sheet Снимок листа.
     * @param array<string, int> $parameters Параметры формул.
     *
     * @return FormulaContext Контекст без баз действий.
     *
     * @throws CharacterInvalidException Если документ битый.
     */
    public function build(array $sheet, array $parameters = []): FormulaContext
    {
        return $this->sheetContexts->build($sheet, $parameters);
    }

    /**
     * Собирает контекст из sheet строки персонажа.
     *
     * @param int $characterId Идентификатор.
     *
     * @return FormulaContext Контекст без параметров и баз действий.
     *
     * @throws CharacterNotFoundException Если строки нет.
     * @throws CharacterInvalidException Если документ битый.
     */
    public function buildStored(int $characterId): FormulaContext
    {
        $record = $this->characters->get($characterId);
        if ($this->slices === null || $this->sheets === null) {
            return $this->build($record->getSheet());
        }

        return $this->build(Character::fromRecord(
            $record,
            $this->slices->get($record->getSpaceId(), $record->getRulesRevision()),
            $this->sheets,
        )->getSheet());
    }
}
