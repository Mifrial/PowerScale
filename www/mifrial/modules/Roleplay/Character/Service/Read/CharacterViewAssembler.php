<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Read;

use Mifrial\Roleplay\Character\Dto\CharacterOpening;
use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Dto\CharacterViewerRecord;

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
        $view = $this->listItem($record);
        if ($opening->isForOwner()) {
            return $view + $this->ownerFields($opening, $record);
        }

        $masked = $this->sectionMask->apply($record->getChoices(), $record->getSheet(), $opening->getSections());
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
     *
     * @return array<string, mixed> JSON.
     */
    private function ownerFields(CharacterOpening $opening, CharacterRecord $record): array
    {
        return [
            'actualVersion' => $record->getActualVersion(),
            'choices' => $record->getChoices(),
            'sheet' => $record->getSheet(),
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
