<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Roleplay\Character\Dto\CharacterCombatLayer;
use Mifrial\Roleplay\Character\Dto\CharacterDonorGrant;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ResistanceGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalFormula;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ScalarFormula;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Слои сопротивления из грантов купленных способностей.
 */
final class CharacterCombatGrantLayers
{
    /**
     * Создаёт чтение грантов.
     *
     * @param ICharacterFormulaContexts $contexts Контекст листа.
     * @param IFormulaEvaluations $evaluations Расчёт формул.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacterFormulaContexts $contexts,
        private readonly IFormulaEvaluations $evaluations,
    ) {
    }

    /**
     * Слои грантов resistance.
     *
     * @param CharacterRuleSlice $slice Ревизия.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы.
     *
     * @return array<int, CharacterCombatLayer> Слои.
     *
     * @throws CharacterInvalidException Если формула или spec битые.
     */
    public function collect(CharacterRuleSlice $slice, array $sheet, array $choices): array
    {
        $levels = $sheet['abilityLevels'] ?? null;
        if (!is_array($levels)) {
            $this->reject();
        }

        $layers = [];
        $donors = new CharacterDonorGrants();
        foreach ($levels as $code => $level) {
            array_push($layers, ...$this->ability($slice, $sheet, $choices, $donors, $code, $level));
        }

        return $layers;
    }

    /**
     * Гранты одной способности.
     *
     * @param CharacterRuleSlice $slice Ревизия.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы.
     * @param CharacterDonorGrants $donors Отбор блоков.
     * @param mixed $code Код.
     * @param mixed $level Уровень.
     *
     * @return array<int, CharacterCombatLayer> Слои.
     *
     * @throws CharacterInvalidException Если формула битая.
     */
    private function ability(
        CharacterRuleSlice $slice,
        array $sheet,
        array $choices,
        CharacterDonorGrants $donors,
        mixed $code,
        mixed $level,
    ): array {
        if (!is_string($code) || $code === '' || !is_int($level)) {
            $this->reject();
        }

        $rule = $slice->findLive($code);
        if ($rule === null) {
            return [];
        }

        if ($rule->isSpecBroken()) {
            $this->reject();
        }

        return $this->resistances(
            $donors->getForLevel($rule, $code, $level),
            $this->contexts->build($sheet, $this->parameters($choices, $code)),
        );
    }

    /**
     * Слои грантов resistance одного уровня.
     *
     * @param array<int, CharacterDonorGrant> $donors Гранты.
     * @param FormulaContext $context Контекст.
     *
     * @return array<int, CharacterCombatLayer> Слои.
     *
     * @throws CharacterInvalidException Если формула отказала.
     */
    private function resistances(array $donors, FormulaContext $context): array
    {
        $layers = [];
        foreach ($donors as $donor) {
            $grant = $donor->getGrant();
            if ($grant instanceof ResistanceGrant) {
                $layers[] = $this->layer($grant, $context);
            }
        }

        return $layers;
    }

    /**
     * Слой одного гранта.
     *
     * @param ResistanceGrant $grant Грант.
     * @param FormulaContext $context Контекст.
     *
     * @return CharacterCombatLayer Слой.
     *
     * @throws CharacterInvalidException Если параметра нет.
     */
    private function layer(ResistanceGrant $grant, FormulaContext $context): CharacterCombatLayer
    {
        return new CharacterCombatLayer(
            'resistance',
            $this->value($grant, $context),
            null,
            $grant->getSourceCode(),
            $grant->getDamageTypeCode(),
        );
    }

    /**
     * Пара гранта.
     *
     * @param ResistanceGrant $grant Грант.
     * @param FormulaContext $context Контекст.
     *
     * @return DimensionalNumber Пара.
     *
     * @throws CharacterInvalidException Если формула отказала.
     */
    private function value(ResistanceGrant $grant, FormulaContext $context): DimensionalNumber
    {
        $value = $grant->getValue();
        if ($value instanceof DimensionalNumber) {
            return $value;
        }

        try {
            if ($value instanceof DimensionalFormula) {
                return $this->evaluations->evaluateDimensional($value, $context);
            }

            if ($value instanceof ScalarFormula) {
                return new DimensionalNumber($this->evaluations->evaluateScalar($value, $context), 0);
            }
        } catch (RuleInvalidException $exception) {
            throw new CharacterInvalidException('Character combat layers are invalid', $exception);
        }

        $this->reject();
    }

    /**
     * Параметры способности из choices.abilities.
     *
     * @param array<string, mixed> $choices Выборы.
     * @param string $code Код способности.
     *
     * @return array<string, int> Код → целое.
     *
     * @throws CharacterInvalidException Если пара битая.
     */
    private function parameters(array $choices, string $code): array
    {
        $abilities = $choices['abilities'] ?? [];
        if (!is_array($abilities)) {
            $this->reject();
        }

        $parameters = [];
        foreach ($abilities as $ability) {
            if (is_array($ability) && ($ability['ruleCode'] ?? null) === $code) {
                $parameters = $this->pairs($ability['parameters'] ?? []);
            }
        }

        return $parameters;
    }

    /**
     * Пары параметров в целые.
     *
     * @param mixed $pairs Карта.
     *
     * @return array<string, int> Код → целое.
     *
     * @throws CharacterInvalidException Если пара битая.
     */
    private function pairs(mixed $pairs): array
    {
        if (!is_array($pairs)) {
            $this->reject();
        }

        $parameters = [];
        foreach ($pairs as $code => $pair) {
            if (!$this->pair($code, $pair)) {
                $this->reject();
            }

            $parameters[$code] = (new DimensionalNumber($pair['base'], $pair['size']))->toInteger();
        }

        return $parameters;
    }

    /**
     * Пара параметра — код и два целых.
     *
     * @param mixed $code Код.
     * @param mixed $pair Пара.
     *
     * @return bool true, если форма годится.
     */
    private function pair(mixed $code, mixed $pair): bool
    {
        return is_string($code)
            && is_array($pair)
            && is_int($pair['base'] ?? null)
            && is_int($pair['size'] ?? null);
    }

    /**
     * Отказ проекции.
     *
     * @return never
     *
     * @throws CharacterInvalidException Всегда.
     */
    private function reject(): never
    {
        throw new CharacterInvalidException('Character combat layers are invalid');
    }
}
