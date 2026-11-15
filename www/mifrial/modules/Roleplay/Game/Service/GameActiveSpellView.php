<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameActiveSpellRecord;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * JSON поддерживаемого заклинания и вопроса поддержания.
 */
final class GameActiveSpellView
{
    /**
     * Собирает JSON строки.
     *
     * @param GameActiveSpellRecord $spell Строка.
     * @param DimensionalNumber|null $stability Стабильность или null.
     *
     * @return array<string, mixed> JSON.
     */
    public function build(GameActiveSpellRecord $spell, ?DimensionalNumber $stability = null): array
    {
        return [
            'id' => (string) $spell->getId(),
            'gameId' => $spell->getGameId(),
            'casterKey' => $spell->getCasterKey(),
            'spellCode' => $spell->getSpellCode(),
            'pathCode' => $spell->getPathCode(),
            'sourceKey' => $spell->getSourceKey(),
            'durationType' => $spell->getDurationType(),
            'usedPower' => $spell->getUsedPower(),
            'sustainPower' => $spell->getSustainPower(),
            'stability' => $stability === null ? null : [
                'base' => $stability->getBase(),
                'size' => $stability->getSize(),
            ],
            'parameterValues' => $spell->getParameterValues(),
            'appliedUpgradeCodes' => $spell->getAppliedUpgradeCodes(),
            'startedRound' => $spell->getStartedRound(),
            'startedParticipant' => $spell->getStartedParticipant(),
            'casterSheetVersion' => $spell->getCasterSheetVersion(),
            'version' => $spell->getStateVersion(),
        ];
    }

    /**
     * Вопрос поддержания на входе хода.
     *
     * @param GameActiveSpellRecord $spell Строка.
     * @param array{base: int, size: int}|null $defaultSustainPower Мощь из формы карточки или null.
     * @param DimensionalNumber|null $stability Стабильность или null.
     *
     * @return array<string, mixed> JSON.
     */
    public function prompt(
        GameActiveSpellRecord $spell,
        ?array $defaultSustainPower,
        ?DimensionalNumber $stability = null,
    ): array {
        return $this->build($spell, $stability) + ['defaultSustainPower' => $defaultSustainPower];
    }
}
