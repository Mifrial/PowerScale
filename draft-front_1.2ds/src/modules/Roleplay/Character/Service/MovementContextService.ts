import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { CharacteristicNumber } from '@/modules/Roleplay/Rule/init';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { raceSpecService } from '@/modules/Roleplay/Rule/init';

export class MovementContextService {
  constructor(private readonly races = raceSpecService) {}
  resolveMovementStep(
    version?: CharacterVersion,
    rules?: Rule[],
    explicitStep?: DimensionalNumberValue,
  ): DimensionalNumberValue {
    if (explicitStep) return explicitStep;
    let step = new DimensionalNumber({ base: 1, size: 0 });
    if (!version || !rules) return step.value;
    const abilityLevels = new Set(version.abilities.map((ability) => ability.ruleId));
    const rulesByCode = new Map(rules.map((rule) => [rule.code, rule]));
    const raceRule = rules.find((rule) => rule.id === version.raceRuleId);
    const raceSpec =
      raceRule?.type === 'race' || raceRule?.type === 'species'
        ? (raceRule.spec as
            | {
                parent_race_code?: string | null;
                abilities?: { ability_code: string; automatic?: boolean }[];
              }
            | undefined)
        : undefined;
    const automaticRacialAbilities = [
      ...(raceSpec?.abilities ?? []),
      ...this.races.collectInheritedAbilities(raceSpec?.parent_race_code ?? null, rulesByCode),
    ].filter((ability) => ability.automatic);
    const automaticAbilityCodes = automaticRacialAbilities.map((ability) => ability.ability_code);
    for (const abilityCode of [...abilityLevels, ...automaticAbilityCodes]) {
      const rule = rulesByCode.get(abilityCode) ?? rules.find((entry) => entry.id === abilityCode);
      const delta =
        rule?.type === 'ability'
          ? ((rule.spec as { movement_step_size_delta?: number } | undefined)?.movement_step_size_delta ?? 0)
          : 0;
      if (delta) step = new DimensionalNumber({ base: step.value.base, size: step.value.size + delta });
    }

    return step.value;
  }

  strengthWeightGap(
    characteristicValues: ReadonlyMap<string, DimensionalNumberValue>,
    strengthCode: string,
    weightCode: string,
  ): number {
    const strength = characteristicValues.get(strengthCode);
    const weight = characteristicValues.get(weightCode);
    if (!strength || !weight) return 0;

    return Math.trunc(CharacteristicNumber.from(strength).modifyDiffTo(new DimensionalNumber(weight)) / 3);
  }

  speed(
    movementStep: DimensionalNumberValue,
    baseSteps: number,
    gapMultiplier: number,
    strengthWeightGap: number,
  ): DimensionalNumberValue {
    return {
      base: movementStep.base * (baseSteps + gapMultiplier * strengthWeightGap),
      size: movementStep.size,
    };
  }
}
