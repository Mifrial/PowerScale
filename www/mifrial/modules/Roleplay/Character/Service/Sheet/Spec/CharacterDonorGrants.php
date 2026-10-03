<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet\Spec;

use Mifrial\Roleplay\Character\Dto\CharacterDonorGrant;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpec;

/**
 * Гранты способности из контракта spec, без схлопывания в словарь.
 */
final class CharacterDonorGrants
{
    /**
     * Все гранты способности. Уровень блока не фильтрует.
     *
     * @param CharacterResolvedRule $rule Правило.
     * @param string $donorCode Код донора.
     *
     * @return array<int, CharacterDonorGrant> Гранты.
     */
    public function getAll(CharacterResolvedRule $rule, string $donorCode): array
    {
        return $this->rows($rule, $donorCode, null);
    }

    /**
     * Гранты блоков, чей целый level не выше купленного.
     *
     * @param CharacterResolvedRule $rule Способность.
     * @param string $donorCode Код донора.
     * @param int $level Купленный уровень.
     *
     * @return array<int, CharacterDonorGrant> Гранты.
     */
    public function getForLevel(CharacterResolvedRule $rule, string $donorCode, int $level): array
    {
        if ($level < 1) {
            return [];
        }

        return $this->rows($rule, $donorCode, $level);
    }

    /**
     * Плоский список. $level null — без фильтра.
     *
     * @param CharacterResolvedRule $rule Правило.
     * @param string $donorCode Код донора.
     * @param int|null $level Потолок или null.
     *
     * @return array<int, CharacterDonorGrant> Гранты.
     */
    private function rows(CharacterResolvedRule $rule, string $donorCode, ?int $level): array
    {
        $spec = $rule->getSpec();
        if ($rule->isSpecBroken() || !$spec instanceof AbilitySpec) {
            return [];
        }

        $rows = [];
        foreach ($spec->getGrantBlocks() as $block) {
            if ($level !== null && $block->getLevel() > $level) {
                continue;
            }

            foreach ($block->getGrants() as $grant) {
                $rows[] = new CharacterDonorGrant($donorCode, $grant);
            }
        }

        return $rows;
    }
}
