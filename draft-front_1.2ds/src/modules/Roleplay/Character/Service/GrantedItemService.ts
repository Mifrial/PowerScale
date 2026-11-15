import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { AbilityParameter } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityParameter';
import type { AbilitySpecBase } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecBase';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { raceSpecService } from '@/modules/Roleplay/Rule/init';

type AbilityParameters = Record<string, DimensionalNumberValue | number>;

type GrantedBuild = {
  abilities: CharacterAbility[];
  inventory: InventoryItem[];
  /** Раса сборки: автоматические ссылки расы тоже дают предметы (`Даёт способности`). */
  raceRuleCode?: string | null;
};

/** Донор предмета: выбранная способность или автоматическая ссылка расы. */
interface Donor {
  level: number;
  /** Выбранные значения параметров (у ссылки расы — её automatic-значения). */
  parameters?: AbilityParameters;
  /** Бесплатная база расы: добирает параметры, которых нет в выбранном экземпляре. */
  fallback?: Record<string, DimensionalNumberValue>;
}

interface ItemBinding {
  itemCode: string;
  parameters: Record<string, DimensionalNumberValue>;
  payload: Record<string, number>;
}

/** Материализует предметы из грантов способностей и держит их параметры в актуальном виде. */
export class GrantedItemService {
  /**
   * Строки, полученные грантом предмета с привязкой параметров, создаются и обновляются
   * из живого параметра донора. Строка без активного донора удаляется.
   */
  apply<T extends GrantedBuild>(build: T, rules: Rule[]): T {
    const byCode = new Map(rules.map((rule) => [rule.code, rule]));
    const bindings = this.bindings(build, byCode);
    const inventory = build.inventory.map((item) => ({ ...item }));

    let nextId = inventory.reduce((max, item) => Math.max(max, item.id), 0);
    const kept: InventoryItem[] = [];
    for (const item of inventory) {
      const donor = item.grantedBy?.ruleCode;
      if (!donor) {
        kept.push(item);
        continue;
      }
      const binding = bindings.get(donor);
      if (!binding || binding.itemCode !== item.ruleCode) {
        continue;
      }
      kept.push({
        ...item,
        equipped: true,
        parameters: binding.parameters,
        ...(Object.keys(binding.payload).length > 0
          ? { payload: { ...(item.payload ?? {}), ...binding.payload } }
          : {}),
      });
    }

    for (const [donor, binding] of bindings) {
      if (kept.some((item) => item.grantedBy?.ruleCode === donor)) continue;
      const existing = kept.find((item) => item.ruleCode === binding.itemCode && !item.grantedBy);
      if (existing) {
        existing.equipped = true;
        existing.parameters = binding.parameters;
        existing.grantedBy = { ruleCode: donor };
        if (Object.keys(binding.payload).length > 0) {
          existing.payload = { ...(existing.payload ?? {}), ...binding.payload };
        }
        continue;
      }
      nextId += 1;
      kept.push({
        id: nextId,
        ruleCode: binding.itemCode,
        quantity: 1,
        equipped: true,
        parameters: binding.parameters,
        grantedBy: { ruleCode: donor },
        ...(Object.keys(binding.payload).length > 0 ? { payload: binding.payload } : {}),
      });
    }

    return { ...build, inventory: kept };
  }

  /** Привязки параметров активных грантов: донор → предмет и значения параметров. */
  private bindings(build: GrantedBuild, byCode: Map<string, Rule>): Map<string, ItemBinding> {
    const automatic = this.automaticParameters(build.raceRuleCode ?? null, byCode);
    const donors = new Map<string, Donor>();
    for (const [code, parameters] of automatic) {
      donors.set(code, { level: 1, parameters, fallback: parameters });
    }
    for (const ability of build.abilities) {
      if (ability.level < 1) continue;
      const current = donors.get(ability.ruleCode);
      if (current && ability.level < current.level) continue;
      donors.set(ability.ruleCode, {
        level: ability.level,
        parameters: ability.parameters,
        fallback: automatic.get(ability.ruleCode),
      });
    }

    const result = new Map<string, ItemBinding>();
    for (const [code, donor] of donors) {
      const spec = byCode.get(code)?.spec as AbilitySpecBase | undefined;
      if (!spec) continue;
      for (const block of spec.grants ?? []) {
        if (block.level > donor.level) continue;
        for (const grant of block.grants) {
          if (grant.type !== 'item' || (!grant.parameters && !grant.payload)) continue;
          result.set(code, this.bindingOf(grant, spec, donor));
        }
      }
    }

    return result;
  }

  /** Автоматические ссылки расы: код способности → значения параметров дара. */
  private automaticParameters(
    raceRuleCode: string | null,
    byCode: Map<string, Rule>,
  ): Map<string, Record<string, DimensionalNumberValue>> {
    const result = new Map<string, Record<string, DimensionalNumberValue>>();
    for (const ref of raceSpecService.automaticAbilities(raceRuleCode, byCode)) {
      result.set(ref.ability_code, ref.parameters ?? {});
    }

    return result;
  }

  /** Привязка гранта предмета: значения из выбранного экземпляра, затем база расы, затем default. */
  private bindingOf(grant: Extract<Grant, { type: 'item' }>, spec: AbilitySpecBase, donor: Donor): ItemBinding {
    const parameters: Record<string, DimensionalNumberValue> = {};
    for (const [instanceCode, parameterCode] of Object.entries(grant.parameters ?? {})) {
      const value = this.parameterValue(donor, spec, parameterCode);
      if (value) parameters[instanceCode] = value;
    }
    const payload: Record<string, number> = {};
    for (const [key, parameterCode] of Object.entries(grant.payload ?? {})) {
      const value = this.parameterValue(donor, spec, parameterCode);
      if (value) payload[key] = DimensionalNumber.from(value).toNumber();
    }

    return { itemCode: grant.item_code, parameters, payload };
  }

  /** Значение параметра: выбранное, automatic-база расы или default spec. */
  private parameterValue(
    donor: Donor,
    spec: AbilitySpecBase,
    code: string,
  ): DimensionalNumberValue | null {
    const chosen = donor.parameters?.[code];
    if (typeof chosen === 'number') return { base: chosen, size: 0 };
    if (chosen) return chosen;
    const fallback = donor.fallback?.[code];
    if (fallback) return fallback;
    const parameter = (spec.parameters ?? []).find((entry: AbilityParameter) => entry.code === code);
    if (!parameter) return null;
    const value = parameter.default;

    return typeof value === 'number' ? { base: value, size: 0 } : value;
  }
}

export const grantedItemService = new GrantedItemService();
