<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Resource;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ResourceGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ResourceLimitChangeGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;

/**
 * Собирает применимые permanent-гранты ресурсов.
 */
final class CharacterResourceGrantReader
{
    /**
     * Создаёт читатель грантов.
     *
     * @param CharacterDonorGrants $donorGrants Гранты способностей.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterDonorGrants $donorGrants,
    ) {
    }

    /**
     * Собирает permanent-гранты по коду ресурса.
     *
     * @param CharacterRuleSlice $slice Live-срез.
     * @param CharacterChoices $choices Выбранные способности.
     *
     * @return array<string, array<int, ResourceGrant|ResourceLimitChangeGrant>> Гранты.
     *
     * @throws CharacterInvalidException Если ресурс гранта неизвестен.
     */
    public function getByResource(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
    ): array {
        $levels = $this->levels($choices);
        $grants = [];
        foreach ($levels as $code => $level) {
            $rule = $slice->findLive($code);
            if ($rule === null) {
                throw new CharacterInvalidException('Ability grant rule is unknown');
            }

            foreach ($this->resourceGrants($slice, $rule, $code, $level) as $grant) {
                $grants[$grant->getResourceCode()][] = $grant;
            }
        }

        return $grants;
    }

    /**
     * Возвращает максимальный выбранный уровень каждой способности.
     *
     * @param CharacterChoices $choices Selected abilities.
     *
     * @return array<string, int> Ability levels.
     */
    private function levels(CharacterChoices $choices): array
    {
        $levels = [];
        foreach ($choices->getAbilities() as $ability) {
            $code = $ability->getRuleCode();
            $levels[$code] = max($levels[$code] ?? 0, $ability->getLevel());
        }

        return $levels;
    }

    /**
     * Выбирает permanent resource grants одного донора.
     *
     * @param CharacterRuleSlice $slice Live-срез.
     * @param CharacterResolvedRule $rule Донор.
     * @param string $code Код донора.
     * @param int $level Максимальный уровень.
     *
     * @return array<int, ResourceGrant|ResourceLimitChangeGrant> Гранты.
     *
     * @throws CharacterInvalidException Если ресурс гранта неизвестен.
     */
    private function resourceGrants(
        CharacterRuleSlice $slice,
        CharacterResolvedRule $rule,
        string $code,
        int $level,
    ): array {
        $grants = [];
        foreach ($this->donorGrants->getForLevel($rule, $code, $level) as $donor) {
            $grant = $donor->getGrant();
            if (!$grant instanceof ResourceGrant && !$grant instanceof ResourceLimitChangeGrant) {
                continue;
            }

            if (!$grant->isPermanent()) {
                continue;
            }

            $this->assertResource($slice, $grant->getResourceCode());
            $grants[] = $grant;
        }

        return $grants;
    }

    /**
     * Проверяет live resource rule гранта.
     *
     * @param CharacterRuleSlice $slice Live-срез.
     * @param string $resourceCode Код ресурса.
     *
     * @return void
     *
     * @throws CharacterInvalidException Если ресурс неизвестен.
     */
    private function assertResource(CharacterRuleSlice $slice, string $resourceCode): void
    {
        $resourceRule = $slice->findLive($resourceCode);
        if (
            $resourceRule === null
            || $resourceRule->isSpecBroken()
            || !$resourceRule->getSpec() instanceof ResourceSpec
        ) {
            throw new CharacterInvalidException('Ability grant resource is unknown');
        }
    }
}
