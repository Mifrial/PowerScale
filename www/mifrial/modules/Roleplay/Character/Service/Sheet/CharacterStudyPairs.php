<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterProblemList;

/**
 * Пара пути только по studyPairId и studyPairRole.
 */
final class CharacterStudyPairs
{
    /**
     * Проверяет группы пар.
     *
     * @param CharacterChoices $choices Выборы.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return void
     */
    public function check(CharacterChoices $choices, CharacterProblemList $problems): void
    {
        $groups = [];
        foreach ($choices->getAbilities() as $ability) {
            $pairId = $ability->getStudyPairId();
            if ($pairId === null) {
                continue;
            }

            $groups[$pairId][] = $ability;
        }

        foreach ($groups as $pairId => $members) {
            $this->checkGroup((string) $pairId, $members, $problems);
        }
    }

    /**
     * Одна группа id.
     *
     * @param string $pairId Id.
     * @param array<int, CharacterAbilityChoice> $members Участники.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return void
     */
    private function checkGroup(string $pairId, array $members, CharacterProblemList $problems): void
    {
        if (!$this->rolesFit(count($members), $this->roleCounts($members))) {
            $problems->add('CHARACTER_STUDY_PAIR', 'Study pair roles are invalid', 'budget', 'studyPairId:' . $pairId);
        }
    }

    /**
     * Сколько charged и free.
     *
     * @param array<int, CharacterAbilityChoice> $members Участники.
     *
     * @return array{charged: int, free: int, other: int} Счётчики.
     */
    private function roleCounts(array $members): array
    {
        $counts = ['charged' => 0, 'free' => 0, 'other' => 0];
        foreach ($members as $member) {
            $role = $member->getStudyPairRole();
            if ($role === 'charged' || $role === 'free') {
                $counts[$role]++;
            } else {
                $counts['other']++;
            }
        }

        return $counts;
    }

    /**
     * Два: один charged и один free. Один: только charged.
     *
     * @param int $size Размер группы.
     * @param array{charged: int, free: int, other: int} $counts Счётчики.
     *
     * @return bool true, если роли допустимы.
     */
    private function rolesFit(int $size, array $counts): bool
    {
        if ($counts['other'] !== 0) {
            return false;
        }

        if ($size === 1) {
            return $counts['charged'] === 1;
        }

        return $size === 2 && $counts['charged'] === 1 && $counts['free'] === 1;
    }
}
