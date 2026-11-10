<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterDonorGrant;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityGrantReader;
use Mifrial\Roleplay\Game\Dto\GameCheckEfficiencyResolution;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CheckEfficiencyGrant;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Эффективность проверки из грантов check_efficiency.
 */
final class GameCheckEfficiency
{
    /**
     * Создаёт расчёт.
     *
     * @param CharacterAbilityGrantReader $grants Гранты способностей.
     * @param GameCheckChain $chain Карточки проверок.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterAbilityGrantReader $grants,
        private readonly GameCheckChain $chain,
    ) {
    }

    /**
     * Считает эффективность и вклады источников.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param CharacterChoices $choices Выборы кастера.
     * @param array<string, mixed> $abilityLevels Уровни способностей.
     * @param string|null $checkCode Код проверки.
     *
     * @return GameCheckEfficiencyResolution Результат.
     *
     * @throws GameInvalidException Если карточка проверки битая.
     */
    public function resolve(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        array $abilityLevels,
        ?string $checkCode,
    ): GameCheckEfficiencyResolution {
        $base = 3;
        $explicit = false;
        if ($checkCode !== null && $checkCode !== '') {
            $check = $this->chain->checkOf($slice, $checkCode, true);
            $default = $check->getDefaultEfficiency();
            if ($default !== null) {
                $base = $default;
                $explicit = true;
            }
        }

        $entries = $this->collectEntries($slice, $choices, $abilityLevels, $checkCode);
        $adjustments = $this->aggregate($entries);
        $net = 0;
        foreach ($adjustments as $adjustment) {
            $net += $adjustment['delta'];
        }

        return new GameCheckEfficiencyResolution(
            (new CharacteristicNumber($base, 0))->modify($net),
            $adjustments,
            $explicit || $net !== 0,
        );
    }

    /**
     * Гранты, применимые к проверке.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param CharacterChoices $choices Выборы кастера.
     * @param array<string, mixed> $abilityLevels Уровни способностей.
     * @param string|null $checkCode Код проверки.
     *
     * @return list<array{source_code: string, source_label: string, delta: int}> Вклады.
     */
    private function collectEntries(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        array $abilityLevels,
        ?string $checkCode,
    ): array {
        if ($checkCode === null || $checkCode === '') {
            return [];
        }

        $levels = $this->levelsOf($abilityLevels);
        $entries = [];
        foreach ($this->grants->grants($slice, $choices, $levels) as $row) {
            $grant = $row->getGrant();
            if (!$grant instanceof CheckEfficiencyGrant) {
                continue;
            }

            if (!in_array($checkCode, $grant->getCheckCodes(), true)) {
                continue;
            }

            if (!$this->applies($row, $grant, $levels)) {
                continue;
            }

            $source = $grant->getSourceCode();
            if ($source === null || $source === '') {
                $source = $row->getDonorCode();
            }

            $entries[] = [
                'source_code' => $source,
                'source_label' => $slice->findLive($row->getDonorCode())?->getName() ?? $source,
                'delta' => $grant->getAmount(),
            ];
        }

        return $entries;
    }

    /**
     * Оставляет уровни способностей целыми.
     *
     * @param array<string, mixed> $abilityLevels Уровни.
     *
     * @return array<string, int> Уровни.
     */
    private function levelsOf(array $abilityLevels): array
    {
        $levels = [];
        foreach ($abilityLevels as $code => $level) {
            if (is_int($level)) {
                $levels[$code] = $level;
            }
        }

        return $levels;
    }

    /**
     * Проверяет уровень блока и постоянство гранта.
     *
     * @param CharacterDonorGrant $row Грант донора.
     * @param CheckEfficiencyGrant $grant Грант.
     * @param array<string, int> $levels Уровни способностей.
     *
     * @return bool true, если грант действует.
     */
    private function applies(CharacterDonorGrant $row, CheckEfficiencyGrant $grant, array $levels): bool
    {
        if ($grant->isPermanent()) {
            return true;
        }

        $level = $levels[$row->getDonorCode()] ?? null;

        return $level === null || $row->getBlockLevel() === $level;
    }

    /**
     * Сильнейший плюс и минус одного источника, затем сумма источников.
     *
     * @param list<array{source_code: string, source_label: string, delta: int}> $entries Вклады.
     *
     * @return list<array{source_code: string, source_label: string, delta: int}> Вклады.
     */
    private function aggregate(array $entries): array
    {
        $groups = [];
        foreach ($entries as $entry) {
            $groups[$entry['source_code']][] = $entry;
        }

        $aggregated = [];
        foreach ($groups as $group) {
            $bonus = null;
            $penalty = null;
            foreach ($group as $entry) {
                if ($entry['delta'] > 0 && ($bonus === null || $entry['delta'] > $bonus['delta'])) {
                    $bonus = $entry;
                }

                if ($entry['delta'] < 0 && ($penalty === null || $entry['delta'] < $penalty['delta'])) {
                    $penalty = $entry;
                }
            }

            if ($bonus !== null) {
                $aggregated[] = $bonus;
            }

            if ($penalty !== null) {
                $aggregated[] = $penalty;
            }
        }

        return $aggregated;
    }
}
