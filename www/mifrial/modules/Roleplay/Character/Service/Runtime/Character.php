<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Runtime;

use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterSheetLive;

/**
 * Снимок персонажа после полного пересчёта листа.
 */
final class Character
{
    /**
     * Создаёт снимок.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Документ choices.
     * @param array<string, mixed> $sheet Документ sheet.
     * @param CharacterSheetLive $live Калькулятор.
     *
     * @return void
     */
    private function __construct(
        private readonly CharacterRuleSlice $slice,
        private readonly array $choices,
        private readonly array $sheet,
        private readonly CharacterSheetLive $live,
    ) {
    }

    /**
     * Считает лист из документов.
     *
     * @param CharacterRuleSlice $slice Уже загруженный срез.
     * @param array<string, mixed> $choices Choices.
     * @param array<string, mixed> $sheet Sheet.
     * @param ICharacterSheets $sheets Валидатор.
     *
     * @return self Снимок.
     */
    public static function fromDocument(
        CharacterRuleSlice $slice,
        array $choices,
        array $sheet,
        ICharacterSheets $sheets,
    ): self {
        $live = new CharacterSheetLive($sheets);

        return new self($slice, $choices, $live->compute($slice, $choices, $sheet), $live);
    }

    /**
     * Считает лист из actual-строки.
     *
     * @param CharacterRecord $record Строка.
     * @param CharacterRuleSlice $slice Срез той же ревизии.
     * @param ICharacterSheets $sheets Валидатор.
     *
     * @return self Снимок.
     */
    public static function fromRecord(
        CharacterRecord $record,
        CharacterRuleSlice $slice,
        ICharacterSheets $sheets,
    ): self {
        return self::fromDocument($slice, $record->getChoices(), $record->getSheet(), $sheets);
    }

    /**
     * Патч документа и полный пересчёт. Срез не читается заново.
     * Каталог kind применяется снаружи; сюда приходит уже патченный документ.
     *
     * @param array<string, mixed> $choices Новый choices.
     * @param array<string, mixed> $sheet Новый sheet до compute.
     *
     * @return self Снимок.
     */
    public function apply(array $choices, array $sheet): self
    {
        return new self($this->slice, $choices, $this->live->compute($this->slice, $choices, $sheet), $this->live);
    }

    /**
     * Документ choices.
     *
     * @return array<string, mixed> Choices.
     */
    public function getChoices(): array
    {
        return $this->choices;
    }

    /**
     * Документ sheet.
     *
     * @return array<string, mixed> Sheet.
     */
    public function getSheet(): array
    {
        return $this->sheet;
    }

    /**
     * Срез, с которым считали.
     *
     * @return CharacterRuleSlice Срез.
     */
    public function getSlice(): CharacterRuleSlice
    {
        return $this->slice;
    }
}
