<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Game\Dto\GameCharacterRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Лист персонажа игры: actual, маска секций и краткая запись.
 */
final class GameCharacterProjection
{
    /**
     * Создаёт чтение.
     *
     * @param ICharacters $characters Actual.
     * @param ICharacterRuleSlices $ruleSlices Срез правил.
     * @param ICharacterSheets $sheets Пересчёт листа.
     * @param GameCharacterProjectionMask $mask Секции строки.
     * @param GameProjectionAccess $access Доступ читателя.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacters $characters,
        private readonly ICharacterRuleSlices $ruleSlices,
        private readonly ICharacterSheets $sheets,
        private readonly GameCharacterProjectionMask $mask,
        private readonly GameProjectionAccess $access,
    ) {
    }

    /**
     * Actual или отказ всего вызова.
     *
     * @param int $characterId Персонаж.
     *
     * @return CharacterRecord Строка.
     *
     * @throws GameNotFoundException Если строки нет.
     */
    public function actual(int $characterId): CharacterRecord
    {
        try {
            return $this->characters->get($characterId);
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game character was not found', $exception);
        }
    }

    /**
     * Краткая запись персонажа.
     *
     * @param GameCharacterRecord $row Строка.
     *
     * @return array<string, mixed> JSON.
     *
     * @throws GameNotFoundException Если actual нет.
     */
    public function brief(GameCharacterRecord $row): array
    {
        $actual = $this->actual($row->getCharacterId());

        return [
            'type' => 'character',
            'id' => $row->getCharacterId(),
            'name' => $actual->getName(),
            'status' => $row->getStatus(),
            'actualVersion' => $actual->getActualVersion(),
        ];
    }

    /**
     * Лист персонажа после маски.
     *
     * @param GameRecord $game Игра.
     * @param GameCharacterRecord $row Строка.
     * @param int $actorUserId Актор.
     *
     * @return array<string, mixed> Лист.
     *
     * @throws GameNotFoundException Если actual нет.
     */
    public function sheet(GameRecord $game, GameCharacterRecord $row, int $actorUserId): array
    {
        $actual = $this->actual($row->getCharacterId());
        $sheet = $this->sheets->compute(
            $this->ruleSlices->get($actual->getSpaceId(), $actual->getRulesRevision()),
            $actual->getChoices(),
            $actual->getSheet(),
        );
        $masked = $this->mask->apply(
            $actual->getChoices(),
            $sheet,
            $row->getSectionVisibility(),
            $this->access->characterOpen($game, $row, $actorUserId),
        );

        return [
            'type' => 'character',
            'id' => $row->getCharacterId(),
            'name' => $actual->getName(),
            'actualVersion' => $actual->getActualVersion(),
            'spaceId' => $actual->getSpaceId(),
            'rulesRevision' => $actual->getRulesRevision(),
            'choices' => $masked['choices'],
            'sheet' => $masked['sheet'],
        ];
    }
}
