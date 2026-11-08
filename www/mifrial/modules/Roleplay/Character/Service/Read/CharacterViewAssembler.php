<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Read;

use Mifrial\Roleplay\Character\Dto\CharacterOpening;
use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\CharacterViewerRecord;
use Mifrial\Roleplay\Character\Enum\CharacterSheetSection;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Character\Service\Runtime\Character;

/**
 * JSON list и detail. Не решает доступ.
 */
final class CharacterViewAssembler
{
    /**
     * Создаёт сборщик.
     *
     * @param CharacterSectionMask $sectionMask Маска ключей.
     * @param ICharacterRuleSlices $ruleSlices Срезы правил.
     * @param ICharacterSheets $sheets Листы персонажа.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterSectionMask $sectionMask,
        private readonly ICharacterRuleSlices $ruleSlices,
        private readonly ICharacterSheets $sheets,
    ) {
    }

    /**
     * Строка списка.
     *
     * @param CharacterRecord $record Персонаж.
     *
     * @return array<string, mixed> Проекция.
     */
    public function listItem(CharacterRecord $record): array
    {
        $choices = $record->getChoices();
        $shortDescription = $choices['shortDescription'] ?? null;

        return [
            'id' => $record->getId(),
            'name' => $record->getName(),
            'ownerId' => $record->getOwnerId(),
            'status' => $record->isActive() ? 'ready' : 'draft',
            'spaceId' => $record->getSpaceId(),
            'revision' => $record->getRulesRevision(),
            'active' => $record->isActive(),
            'shortDescription' => is_string($shortDescription) ? $shortDescription : null,
        ];
    }

    /**
     * Detail владельца или чужого.
     *
     * @param CharacterOpening $opening Доступ.
     *
     * @return array<string, mixed> JSON.
     */
    public function detail(CharacterOpening $opening): array
    {
        $record = $opening->getRecord();
        $slice = $this->ruleSlices->get($record->getSpaceId(), $record->getRulesRevision());
        $sheet = Character::fromRecord($record, $slice, $this->sheets)->getSheet();
        $view = $this->listItem($record);
        $view['spaceCode'] = $slice->getSpaceCode();
        $view['visibleSections'] = $opening->getSections();
        $view['raceLabel'] = $this->raceLabel($slice, $record->getChoices(), $opening);
        if ($opening->isForOwner()) {
            return $view + $this->ownerFields($opening, $record, $sheet);
        }

        if ($opening->hasFullSheet()) {
            $view['choices'] = $record->getChoices();
            $view['sheet'] = $sheet;

            return $view;
        }

        $masked = $this->sectionMask->apply($record->getChoices(), $sheet, $opening->getSections());
        $view['choices'] = $masked['choices'];
        $view['sheet'] = $masked['sheet'];

        return $view;
    }

    /**
     * Подпись расы из среза ревизии, если секция выдана зрителю.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы.
     * @param CharacterOpening $opening Доступ.
     *
     * @return string|null Название или null.
     */
    private function raceLabel(CharacterRuleSlice $slice, array $choices, CharacterOpening $opening): ?string
    {
        $sections = $opening->getSections();
        $allowed = $opening->isForOwner()
            || $opening->hasFullSheet()
            || in_array(CharacterSheetSection::Race->value, $sections, true);
        if (!$allowed) {
            return null;
        }

        $code = $choices['raceCode'] ?? null;
        if (!is_string($code) || $code === '') {
            return null;
        }

        return $slice->findLive($code)?->getName();
    }

    /**
     * Поля, которые видит только владелец.
     *
     * @param CharacterOpening $opening Доступ.
     * @param CharacterRecord $record Строка.
     * @param array<string, mixed> $sheet Посчитанный лист.
     *
     * @return array<string, mixed> JSON.
     */
    private function ownerFields(CharacterOpening $opening, CharacterRecord $record, array $sheet): array
    {
        return [
            'actualVersion' => $record->getActualVersion(),
            'choices' => $record->getChoices(),
            'sheet' => $sheet,
            'ownerNotes' => $record->getOwnerNotes(),
            'visibilityFields' => $record->getVisibilityFields(),
            'viewers' => $this->viewers($opening->getViewers()),
        ];
    }

    /**
     * Зрители в JSON.
     *
     * @param array<int, CharacterViewerRecord> $viewers Гранты.
     *
     * @return list<array{userId: int, fields: array}> Строки.
     */
    private function viewers(array $viewers): array
    {
        $rows = [];
        foreach ($viewers as $viewer) {
            $rows[] = [
                'userId' => $viewer->getUserId(),
                'fields' => $viewer->getFields(),
            ];
        }

        return $rows;
    }
}
