<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet\Spec;

use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\MagicPath\MagicPathSpec;

/**
 * Читает из контракта spec поля, которые лист сводит к числу или списку кодов.
 */
final class CharacterSpecReader
{
    /**
     * Коды путей, которые включает путь.
     *
     * @param CharacterResolvedRule $rule Правило.
     *
     * @return array<int, string> Коды.
     */
    public function getIncludedPathCodes(CharacterResolvedRule $rule): array
    {
        $spec = $this->readable($rule);
        if (!$spec instanceof MagicPathSpec) {
            return [];
        }

        return array_values(array_filter(
            $spec->getIncludesPathCodes(),
            static fn (string $code): bool => $code !== '',
        ));
    }

    /**
     * Цена предмета в шопе. Не item или не int — 0.
     *
     * @param CharacterResolvedRule $rule Правило.
     *
     * @return int cost_gm.
     */
    public function getItemCostGm(CharacterResolvedRule $rule): int
    {
        $spec = $this->readable($rule);
        if (!$spec instanceof ItemSpec) {
            return 0;
        }

        return $spec->getCostGm() ?? 0;
    }

    /**
     * Предмет врождённый и в сумму шопа не входит.
     *
     * @param CharacterResolvedRule $rule Правило.
     *
     * @return bool true, если innate.
     */
    public function isInnateItem(CharacterResolvedRule $rule): bool
    {
        $spec = $this->readable($rule);

        return $spec instanceof ItemSpec && $spec->isInnate();
    }

    /**
     * Контракт, если версия не битая.
     *
     * @param CharacterResolvedRule $rule Правило.
     *
     * @return mixed Spec или null.
     */
    private function readable(CharacterResolvedRule $rule): mixed
    {
        if ($rule->isSpecBroken()) {
            return null;
        }

        return $rule->getSpec();
    }
}
