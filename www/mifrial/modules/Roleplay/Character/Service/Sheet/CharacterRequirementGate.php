<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterProblem;
use Mifrial\Roleplay\Character\Dto\CharacterProblemList;

/**
 * Жёсткая проверка требований новых покупок при сохранении.
 */
final class CharacterRequirementGate
{
    /**
     * Отказы для новых уровней, которые лист не активировал.
     *
     * @param CharacterChoices $choices Выборы.
     * @param array<string, mixed> $sheet Посчитанный лист.
     * @param array<int, array{code: string, level: int, domainCode: string}> $previous Купленные экземпляры до изменения.
     *
     * @return array<int, CharacterProblem> Отказы.
     */
    public function problems(CharacterChoices $choices, array $sheet, array $previous): array
    {
        $effective = $this->levelsOf($sheet);
        $problems = new CharacterProblemList();
        foreach ($choices->getAbilities() as $index => $ability) {
            $level = $ability->getLevel();
            $code = $ability->getRuleCode();
            if ($level < 1 || $this->boughtBefore($previous, $ability)) {
                continue;
            }

            if (!array_key_exists($code, $effective)) {
                continue;
            }

            if ($effective[$code] < $level) {
                $problems->add(
                    'CHARACTER_REQUIREMENT',
                    'Ability requirements are not met',
                    'requirement',
                    'abilities.' . $index,
                );
            }
        }

        return $problems->all();
    }

    /**
     * Этот экземпляр уже куплен на том же пути и не ниже уровня.
     *
     * @param array<int, array{code: string, level: int, domainCode: string}> $previous Предыдущие экземпляры.
     * @param CharacterAbilityChoice $ability Текущий выбор.
     *
     * @return bool true, если покупка не новая.
     */
    private function boughtBefore(array $previous, CharacterAbilityChoice $ability): bool
    {
        foreach ($previous as $row) {
            if (
                $row['code'] === $ability->getRuleCode()
                && $row['domainCode'] === $ability->getDomainCode()
                && $row['level'] >= $ability->getLevel()
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Уровни из документа sheet.
     *
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array<string, int> Код → уровень.
     */
    private function levelsOf(array $sheet): array
    {
        $rows = $sheet['abilityLevels'] ?? null;
        if (!is_array($rows)) {
            return [];
        }

        $levels = [];
        foreach ($rows as $code => $level) {
            if (is_string($code) && is_int($level)) {
                $levels[$code] = $level;
            }
        }

        return $levels;
    }
}
