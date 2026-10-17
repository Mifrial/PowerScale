<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Read;

use Mifrial\Roleplay\Character\Dto\CharacterOpening;
use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Dto\CharacterViewerRecord;
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
        return [
            'id' => $record->getId(),
            'name' => $record->getName(),
            'ownerId' => $record->getOwnerId(),
            'spaceId' => $record->getSpaceId(),
            'revision' => $record->getRulesRevision(),
            'active' => $record->isActive(),
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
        $sheet = Character::fromRecord(
            $record,
            $this->ruleSlices->get($record->getSpaceId(), $record->getRulesRevision()),
            $this->sheets,
        )->getSheet();
        $view = $this->listItem($record);
        if ($opening->isForOwner()) {
            return $view + $this->ownerFields($opening, $record, $sheet);
        }

        $masked = $this->sectionMask->apply($record->getChoices(), $sheet, $opening->getSections());
        $view['visibleSections'] = $opening->getSections();
        $view['choices'] = $masked['choices'];
        $view['sheet'] = $masked['sheet'];

        return $view;
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
