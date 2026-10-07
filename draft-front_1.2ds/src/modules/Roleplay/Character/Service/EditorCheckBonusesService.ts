import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { EditorCheckBonus } from '@/modules/Roleplay/Character/Dto/Editor/EditorCheckBonus';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { ScalarFormula } from '@/modules/Roleplay/Rule/Dto/Ability/ScalarFormula';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { FormulaEvaluationService } from '@/modules/Roleplay/Character/Service/FormulaEvaluationService';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/init';
import { abilityCheckAdvantagesService } from '@/modules/Roleplay/Character/Service/Instance/abilityCheckAdvantagesService';
import { itemCheckAdvantagesService } from '@/modules/Roleplay/Character/Service/Instance/itemCheckAdvantagesService';

export class EditorCheckBonusesService {
  private readonly formula = new FormulaEvaluationService();

  constructor(
    private readonly aggregate = aggregateSourceDeltasService,
    private readonly abilityChecks = abilityCheckAdvantagesService,
    private readonly itemChecks = itemCheckAdvantagesService,
  ) {}
  build(
    version: Pick<CharacterVersion, 'abilities' | 'inventory'> | null | undefined,
    rules: Rule[],
    keywords: readonly Keyword[] = [],
  ): EditorCheckBonus[] {
    if (!version) return [];

    return rules
      .filter((rule) => rule.type === 'check')
      .map((check) => {
        const modifiers = [
          ...this.abilityChecks.checkAdvantageModifiersFromAbilities(version, rules, {
            kind: 'check',
            code: check.code,
          }),
          ...this.itemChecks.checkAdvantageModifiersFromItems(
            version,
            rules,
            {
              kind: 'check',
              code: check.code,
            },
            keywords,
          ),
          ...this.characteristicModifiersFromAbilities(version, rules, check.code),
        ];
        const aggregated = this.aggregate.aggregateSourceDeltas(modifiers).map((modifier) => ({
          sourceRuleCode: modifier.source_code,
          sourceLabel: modifier.source_label,
          delta: modifier.delta,
        }));
        const delta = aggregated.reduce((sum, modifier) => sum + modifier.delta, 0);

        return {
          checkCode: check.code,
          checkName: check.name,
          delta,
          modifiers: aggregated,
        };
      })
      .filter((bonus) => bonus.delta !== 0);
  }

  private characteristicModifiersFromAbilities(
    version: Pick<CharacterVersion, 'abilities'>,
    rules: Rule[],
    checkCode: string,
  ): AdvantageModifier[] {
    const modifiers: AdvantageModifier[] = [];
    for (const ability of version.abilities) {
      if (ability.level < 1) continue;
      const rule = rules.find((candidate) => candidate.code === ability.ruleCode);
      if (!rule || rule.type !== 'ability') continue;
      const spec = rule.spec as AbilitySpec | undefined;
      if (!spec || spec.type === 'group') continue;

      for (const entry of spec.grants ?? []) {
        if (entry.level > ability.level) continue;
        for (const grant of entry.grants) {
          if (grant.type !== 'characteristic_modify' || !grant.check_codes?.includes(checkCode)) continue;
          if (grant.permanent === false && entry.level !== ability.level) continue;
          const delta = this.formulaValue(grant.amount, ability.parameters, version.abilities, rules);
          if (delta === 0) continue;
          modifiers.push({
            source_code: rule.code,
            source_label: rule.name,
            delta,
          });
        }
      }
    }

    return modifiers;
  }

  private formulaValue(
    formula: ScalarFormula,
    parameters: CharacterVersion['abilities'][number]['parameters'],
    abilities: CharacterVersion['abilities'],
    rules: Rule[],
  ): number {
    const abilityLevels = new Map<string, number>();
    for (const ability of abilities) {
      const code = rules.find((rule) => rule.code === ability.ruleCode)?.code;
      if (code) abilityLevels.set(code, ability.level);
    }

    if (this.formula.readsCharacteristics(formula)) {
      throw new Error('Формула размера характеристики недоступна в контексте проверки');
    }

    return this.formula.evaluate(formula, {
      characteristicValues: new Map(),
      abilityLevels,
      parameterValues: (code) => this.parameterScalar(parameters?.[code]),
    });
  }

  private parameterScalar(raw: number | DimensionalNumberValue | undefined): number | undefined {
    if (typeof raw === 'number') return raw;
    if (raw?.size === 0) return raw.base;
    if (raw) throw new Error('Размерный параметр не входит в скалярную формулу');

    return undefined;
  }
}
