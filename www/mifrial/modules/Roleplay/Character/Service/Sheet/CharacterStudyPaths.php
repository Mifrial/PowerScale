<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityBase;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterSpecReader;
use Mifrial\Roleplay\Rule\Dto\Spec\MagicPath\MagicPathSpec;

/**
 * Пути ревизии, их включения и опыт изучения в рамках пути.
 */
final class CharacterStudyPaths
{
    private readonly CharacterSpecReader $specReader;

    private readonly CharacterAbilityCost $cost;

    /**
     * Собирает чтение путей.
     *
     * @return void
     */
    public function __construct()
    {
        $this->specReader = new CharacterSpecReader();
        $this->cost = new CharacterAbilityCost();
    }

    /**
     * Код — живой путь ревизии.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код.
     *
     * @return bool true, если это путь.
     */
    public function isPath(CharacterRuleSlice $slice, string $code): bool
    {
        if ($code === '') {
            return false;
        }

        $rule = $slice->findLive($code);

        return $rule !== null && !$rule->isSpecBroken() && $rule->getSpec() instanceof MagicPathSpec;
    }

    /**
     * Все живые пути ревизии.
     *
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array<int, string> Коды.
     */
    public function all(CharacterRuleSlice $slice): array
    {
        $codes = [];
        foreach ($slice->getLiveRules() as $rule) {
            if (!$rule->isSpecBroken() && $rule->getSpec() instanceof MagicPathSpec) {
                $codes[] = $rule->getCode();
            }
        }

        return $codes;
    }

    /**
     * Путь `owner` включает `target` транзитивно. Совпадение — тоже включение.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $owner Владелец.
     * @param string $target Путь.
     *
     * @return bool true, если включает.
     */
    public function reaches(CharacterRuleSlice $slice, string $owner, string $target): bool
    {
        return in_array($target, $this->covered($slice, $owner), true);
    }

    /**
     * Путь и все пути, которые он включает транзитивно.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $owner Путь.
     *
     * @return array<int, string> Коды.
     */
    public function covered(CharacterRuleSlice $slice, string $owner): array
    {
        if ($owner === '') {
            return [];
        }

        $queue = [$owner];
        $seen = [];
        while ($queue !== []) {
            $code = array_shift($queue);
            if (isset($seen[$code])) {
                continue;
            }

            $seen[$code] = true;
            $queue = array_merge($queue, $this->included($slice, $code));
        }

        return array_keys($seen);
    }

    /**
     * Опыт каждого пути: стоимость изученных карточек на самом пути и включённых путях.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, int> $levels Эффективные уровни.
     *
     * @return array<string, int> Путь → опыт.
     */
    public function experience(CharacterRuleSlice $slice, CharacterChoices $choices, array $levels): array
    {
        $experience = [];
        foreach ($this->all($slice) as $path) {
            $experience[$path] = 0;
        }

        foreach ($choices->getAbilities() as $ability) {
            $level = $levels[$ability->getRuleCode()] ?? 0;
            if ($level < 1) {
                continue;
            }

            $entry = $this->studied($slice, $ability);
            if ($entry === null) {
                continue;
            }

            [$base, $path] = $entry;
            $cost = $this->cost->toLevel($base, $level, $ability->getParameters()) ?? 0;
            if ($cost === 0) {
                continue;
            }

            foreach ($experience as $owner => $value) {
                if ($this->reaches($slice, $owner, $path)) {
                    $experience[$owner] = $value + $cost;
                }
            }
        }

        return $experience;
    }

    /**
     * База карточки и её путь, если карточка изучается в рамках пути.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterAbilityChoice $ability Выбор.
     *
     * @return array{0: \Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityBase, 1: string}|null База и путь.
     */
    public function studied(CharacterRuleSlice $slice, CharacterAbilityChoice $ability): ?array
    {
        $rule = $slice->findLive($ability->getRuleCode());
        if ($rule === null || $rule->isSpecBroken()) {
            return null;
        }

        $base = CharacterAbilityBase::of($rule->getSpec());
        if ($base === null || $base->getStudy() === null) {
            return null;
        }

        $path = $ability->getDomainCode();
        if (!$this->isPath($slice, $path)) {
            return null;
        }

        return [$base, $path];
    }

    /**
     * Прямые includes пути.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код пути.
     *
     * @return array<int, string> Коды.
     */
    private function included(CharacterRuleSlice $slice, string $code): array
    {
        $rule = $slice->findLive($code);
        if ($rule === null) {
            return [];
        }

        return $this->specReader->getIncludedPathCodes($rule);
    }
}
