import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';
import { ITEM_MODIFIER_PRICE_KEYWORD_PRIORITY } from '@/modules/Roleplay/Rule/Constant/Item/ITEM_MODIFIER_PRICE_KEYWORD_PRIORITY';
import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';
import type { ItemModifierApplies } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierSpec';
import type { ItemModifierEffect } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierSpec';
import type { ItemModifierPrice } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierSpec';
import type { ItemModifierSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierSpec';
import type { ItemModifierOp } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierOp';
import type { ItemModifierTypeSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierTypeSpec';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { ResistanceSlot } from '@/modules/Roleplay/Rule/Dto/Item/ResistanceSlot';
import type { WeaponProfile } from '@/modules/Roleplay/Rule/Dto/Item/WeaponProfile';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ItemModifierApplyScope } from '@/modules/Roleplay/Rule/Enum/Item/ItemModifierApplyScope';
import { CHARACTERISTIC_BASE_RANGE } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';

/**
 * Цена, применимость и эффективный спек модификаторов предмета (R29).
 * Цена на шаге — после ops этого модификатора (в т.ч. вес).
 */
export class ItemModifierService {
  isApplicable(applies: ItemModifierApplies | undefined, itemKeywordCodes: readonly string[]): boolean {
    if (!applies) return true;
    const codes = new Set(itemKeywordCodes);
    for (const code of applies.keyword_none ?? []) {
      if (codes.has(code)) return false;
    }
    for (const code of applies.keyword_all ?? []) {
      if (!codes.has(code)) return false;
    }
    const any = applies.keyword_any ?? [];
    if (any.length > 0 && !any.some((code) => codes.has(code))) return false;

    return true;
  }

  identityKey(ruleId: string, modifierRuleIds: readonly string[] | undefined): string {
    const sorted = [...(modifierRuleIds ?? [])].filter((id) => id.length > 0).sort();

    return `${ruleId}|${sorted.join(',')}`;
  }

  sameIdentity(
    leftRuleId: string | null,
    leftMods: readonly string[] | undefined,
    rightRuleId: string | null,
    rightMods: readonly string[] | undefined,
  ): boolean {
    if (leftRuleId === null || rightRuleId === null) return leftRuleId === rightRuleId && leftRuleId === null;

    return this.identityKey(leftRuleId, leftMods) === this.identityKey(rightRuleId, rightMods);
  }

  /**
   * Включить/выключить модификатор в наборе: exclusive-тип вытесняет другой модификатор того же типа.
   */
  toggleSelection(selected: readonly string[], modifierId: string, rules: readonly Rule[]): string[] {
    if (selected.includes(modifierId)) return selected.filter((id) => id !== modifierId);

    const incoming = rules.find((rule) => rule.id === modifierId);
    const incomingSpec =
      incoming?.type === 'item_modifier' ? (incoming.spec as ItemModifierSpec | undefined) : undefined;
    const typeCode = incomingSpec?.type_code ?? '';
    const typeRule = rules.find((rule) => rule.type === 'item_modifier_type' && rule.code === typeCode);
    const exclusive = typeRule ? ((typeRule.spec as ItemModifierTypeSpec | undefined)?.exclusive ?? true) : false;
    if (!exclusive || !typeCode) return [...selected, modifierId];

    const kept = selected.filter((id) => {
      const rule = rules.find((entry) => entry.id === id);
      const spec = rule?.type === 'item_modifier' ? (rule.spec as ItemModifierSpec | undefined) : undefined;

      return spec?.type_code !== typeCode;
    });

    return [...kept, modifierId];
  }

  computePrice(
    baseCostGm: number,
    price: ItemModifierPrice | undefined,
    itemKeywordCodes: readonly string[],
    weight: DimensionalNumberValue | null | undefined,
  ): number {
    const resolved = this.resolvePrice(price, itemKeywordCodes);
    const factor = resolved.factor ?? 1;
    const addGm = resolved.add_gm ?? 0;
    const per100 = resolved.add_gm_per_100g ?? 0;
    const weightGrams = this.realWeightGrams(weight);
    const fromWeight = per100 === 0 ? 0 : Math.round(weightGrams / 100) * per100;
    let cost = Math.round(baseCostGm * factor) + addGm + fromWeight;
    if (resolved.min_final_gm !== null && resolved.min_final_gm !== undefined) {
      cost = Math.max(cost, resolved.min_final_gm);
    }

    return cost;
  }

  formatPriceLabel(price: ItemModifierPrice | undefined, itemKeywordCodes: readonly string[] = []): string | null {
    const resolved = this.resolvePrice(price, itemKeywordCodes);
    if (
      resolved.factor === null &&
      resolved.add_gm === null &&
      resolved.add_gm_per_100g === null &&
      resolved.min_final_gm === null
    ) {
      return null;
    }
    const parts: string[] = [];
    if (resolved.factor !== null) parts.push(`×${resolved.factor}`);
    if (resolved.add_gm !== null) parts.push(`${resolved.add_gm >= 0 ? '+' : ''}${resolved.add_gm} гм`);
    if (resolved.add_gm_per_100g !== null) parts.push(`+${resolved.add_gm_per_100g} гм / 100 г`);
    if (resolved.min_final_gm !== null) parts.push(`минимум ${resolved.min_final_gm} гм`);

    return parts.join(' · ');
  }

  computeStack(
    baseCostGm: number,
    prices: readonly (ItemModifierPrice | undefined)[],
    itemKeywordCodes: readonly string[],
    weight: DimensionalNumberValue | null | undefined,
  ): number {
    let cost = baseCostGm;
    for (const price of prices) {
      cost = this.computePrice(cost, price, itemKeywordCodes, weight);
    }

    return cost;
  }

  applyStack(
    baseSpec: ItemSpec,
    modifiers: readonly Rule[],
    itemKeywordCodes: readonly string[],
  ): { spec: ItemSpec; cost: number } {
    const spec = cloneData(baseSpec);
    let cost = spec.cost_gm ?? 0;
    for (const rule of modifiers) {
      if (rule.type !== 'item_modifier') continue;
      const modifier = rule.spec as ItemModifierSpec | undefined;
      if (!modifier) continue;
      for (const effect of modifier.effects ?? []) {
        if (!this.effectMatches(effect, spec)) continue;
        for (const op of effect.ops ?? []) {
          this.applyOp(spec, op, this.scopeOf(effect.label), rule);
        }
      }
      cost = this.computePrice(cost, modifier.price, itemKeywordCodes, spec.weight);
    }

    return { spec, cost };
  }

  private effectMatches(effect: ItemModifierEffect, spec: ItemSpec): boolean {
    const label = (effect.label ?? '').trim();
    if (label.length === 0 || label === 'Общее') return true;
    if (label === 'Оружие') return spec.weapon != null;
    if (label === 'Щит') return spec.shield != null;
    if (label === 'Доспех') return spec.armor != null;
    if (label === 'Оружие/щит' || label === 'Оружие/Щит') return spec.weapon != null || spec.shield != null;

    return false;
  }

  private scopeOf(label: string | null | undefined): ItemModifierApplyScope {
    const normalized = (label ?? '').trim();
    if (normalized === 'Оружие') return 'weapon';
    if (normalized === 'Щит') return 'shield';
    if (normalized === 'Доспех') return 'armor';
    if (normalized === 'Оружие/щит' || normalized === 'Оружие/Щит') return 'all';

    return 'all';
  }

  private applyOp(spec: ItemSpec, op: ItemModifierOp, scope: ItemModifierApplyScope, rule: Rule): void {
    switch (op.type) {
      case 'weight':
        this.applyWeight(spec, op);

        return;
      case 'min_strength':
        this.applyMinStrength(spec, op.delta, scope);

        return;
      case 'durability':
        this.applyDurability(spec, op, scope);

        return;
      case 'block':
        this.applyBlock(spec, op, scope);

        return;
      case 'defense':
        this.applyDefense(spec, op, scope);

        return;
      case 'armor_reliability':
        this.applyArmorReliability(spec, op, scope);

        return;
      case 'max_agility':
        this.applyMaxAgility(spec, op, scope);

        return;
      case 'strength_penalty':
        this.applyStrengthPenalty(spec, op, scope);

        return;
      case 'action_strength':
        this.applyActionStrength(spec, op, scope, rule);

        return;
      case 'resistance':
        this.applyResistance(spec, op, scope);

        return;
    }
  }

  private applyWeight(spec: ItemSpec, op: Extract<ItemModifierOp, { type: 'weight' }>): void {
    if (!spec.weight) return;
    let kg = this.weightKg(spec.weight);
    if (op.factor !== undefined) kg *= op.factor;
    if (op.add_kg !== undefined) kg += op.add_kg;
    spec.weight = { base: kg, size: 0 };
  }

  private applyMinStrength(spec: ItemSpec, delta: number, scope: ItemModifierApplyScope): void {
    if (this.includesWeapon(scope) && spec.weapon?.min_strength) {
      spec.weapon.min_strength = this.modifyCharacteristic(spec.weapon.min_strength, delta);
    }
    if (this.includesShield(scope) && spec.shield?.min_strength) {
      spec.shield.min_strength = this.modifyCharacteristic(spec.shield.min_strength, delta);
    }
  }

  private applyDurability(
    spec: ItemSpec,
    op: Extract<ItemModifierOp, { type: 'durability' }>,
    scope: ItemModifierApplyScope,
  ): void {
    if (this.includesWeapon(scope) && spec.weapon?.durability) {
      spec.weapon.durability = this.adjustDimensional(spec.weapon.durability, op.delta, op.add_size, true);
    }
    if (this.includesShield(scope) && spec.shield?.durability) {
      spec.shield.durability = this.adjustDimensional(spec.shield.durability, op.delta, op.add_size, true);
    }
  }

  private applyBlock(
    spec: ItemSpec,
    op: Extract<ItemModifierOp, { type: 'block' }>,
    scope: ItemModifierApplyScope,
  ): void {
    if (this.includesWeapon(scope) && spec.weapon?.block_profile) {
      spec.weapon.block_profile.defense = this.scaleDefense(spec.weapon.block_profile.defense, op);
    }
    if (this.includesShield(scope) && spec.shield?.block) {
      spec.shield.block.defense = this.scaleDefense(spec.shield.block.defense, op);
    }
  }

  private applyDefense(
    spec: ItemSpec,
    op: Extract<ItemModifierOp, { type: 'defense' }>,
    scope: ItemModifierApplyScope,
  ): void {
    if (!this.includesArmor(scope) || !spec.armor) return;
    for (const slot of spec.armor.defense_slots) {
      slot.defense = this.scaleDefense(slot.defense, op);
    }
  }

  private applyArmorReliability(
    spec: ItemSpec,
    op: Extract<ItemModifierOp, { type: 'armor_reliability' }>,
    scope: ItemModifierApplyScope,
  ): void {
    if (!this.includesArmor(scope) || !spec.armor) return;
    for (const slot of spec.armor.defense_slots) {
      if (op.set !== undefined) slot.durability = op.set;
      else if (op.add !== undefined) slot.durability += op.add;
    }
  }

  private applyMaxAgility(
    spec: ItemSpec,
    op: Extract<ItemModifierOp, { type: 'max_agility' }>,
    scope: ItemModifierApplyScope,
  ): void {
    if (!this.includesArmor(scope) || spec.armor?.max_agility == null) return;
    spec.armor.max_agility = this.adjustDimensional(spec.armor.max_agility, op.delta, op.add_size, true);
  }

  private applyStrengthPenalty(
    spec: ItemSpec,
    op: Extract<ItemModifierOp, { type: 'strength_penalty' }>,
    scope: ItemModifierApplyScope,
  ): void {
    if (!this.includesArmor(scope) || !spec.armor) return;
    if (op.set !== undefined) {
      spec.armor.strength_penalty = op.set === 0 ? null : op.set;

      return;
    }
    if (op.add === undefined) return;
    const current = spec.armor.strength_penalty ?? 0;
    const next = current + op.add;
    spec.armor.strength_penalty = next === 0 ? null : next;
  }

  private applyActionStrength(
    spec: ItemSpec,
    op: Extract<ItemModifierOp, { type: 'action_strength' }>,
    scope: ItemModifierApplyScope,
    rule: Rule,
  ): void {
    const profiles: WeaponProfile[] = [];
    if (this.includesWeapon(scope)) profiles.push(...(spec.weapon?.weapon_profiles ?? []));
    if (this.includesShield(scope)) profiles.push(...(spec.shield?.weapon_profiles ?? []));
    for (const profile of profiles) {
      if (op.profiles && !op.profiles.includes(profile.type)) continue;
      if (
        op.damage_type_codes &&
        profile.damage.damage_type_code !== null &&
        !op.damage_type_codes.includes(profile.damage.damage_type_code)
      ) {
        continue;
      }
      const formula = op.field === 'damage' ? profile.damage.formula : profile.penetration;
      this.pushActionDelta(formula, op.delta, rule);
    }
  }

  private pushActionDelta(formula: Formula, delta: number, rule: Rule): void {
    if (formula.type !== 'actionCharacteristic') return;
    formula.modifier.push({ delta, source_code: rule.code, source_label: rule.name });
  }

  private applyResistance(
    spec: ItemSpec,
    op: Extract<ItemModifierOp, { type: 'resistance' }>,
    scope: ItemModifierApplyScope,
  ): void {
    const slots: ResistanceSlot[][] = [];
    if (this.includesWeapon(scope) && spec.weapon?.block_profile) {
      slots.push(spec.weapon.block_profile.resistances);
    }
    if (this.includesShield(scope) && spec.shield) {
      slots.push(spec.shield.block.resistances);
    }
    if (this.includesArmor(scope) && spec.armor) {
      slots.push(spec.armor.resistance_slots);
    }
    for (const list of slots) {
      this.mutateResistance(list, op);
    }
  }

  private mutateResistance(list: ResistanceSlot[], op: Extract<ItemModifierOp, { type: 'resistance' }>): void {
    let slot = list.find((entry) => entry.damage_type_code === op.damage_type_code);
    if (!slot) {
      slot = {
        damage_type_code: op.damage_type_code,
        value: { base: 0, size: 0 },
        durability: 1,
        source_code: null,
      };
      list.push(slot);
    }
    if (op.mode === 'add') {
      slot.value = { base: slot.value.base + op.value, size: slot.value.size };

      return;
    }
    if (op.mode === 'add_size') {
      slot.value = { base: slot.value.base, size: slot.value.size + op.value };

      return;
    }
    const floor = new DimensionalNumber({ base: op.value, size: 0 });
    if (new DimensionalNumber(slot.value).compare(floor) < 0) {
      slot.value = floor.value;
    }
  }

  private scaleDefense(
    value: DimensionalNumberValue,
    op: { factor?: number; add?: number; add_size?: number; min?: number },
  ): DimensionalNumberValue {
    let next = { ...value };
    if (op.factor !== undefined) next = { base: next.base * op.factor, size: next.size };
    if (op.add !== undefined) next = { base: next.base + op.add, size: next.size };
    if (op.add_size !== undefined) next = { base: next.base, size: next.size + op.add_size };
    if (op.min !== undefined && this.weightKg(next) < op.min) {
      next = { base: op.min, size: 0 };
    }

    return next;
  }

  private adjustDimensional(
    value: DimensionalNumberValue,
    delta: number | undefined,
    addSize: number | undefined,
    asCharacteristic: boolean,
  ): DimensionalNumberValue {
    let next = value;
    if (delta !== undefined) {
      next = asCharacteristic ? this.modifyCharacteristic(next, delta) : { base: next.base + delta, size: next.size };
    }
    if (addSize !== undefined) next = { base: next.base, size: next.size + addSize };

    return next;
  }

  private modifyCharacteristic(value: DimensionalNumberValue, delta: number): DimensionalNumberValue {
    return new DimensionalNumber(value).modify(delta, CHARACTERISTIC_BASE_RANGE).value;
  }

  private includesWeapon(scope: ItemModifierApplyScope): boolean {
    return scope === 'all' || scope === 'weapon';
  }

  private includesShield(scope: ItemModifierApplyScope): boolean {
    return scope === 'all' || scope === 'shield';
  }

  private includesArmor(scope: ItemModifierApplyScope): boolean {
    return scope === 'all' || scope === 'armor';
  }

  private resolvePrice(price: ItemModifierPrice | undefined, itemKeywordCodes: readonly string[]): ItemModifierPrice {
    if (!price) {
      return { factor: null, add_gm: null, add_gm_per_100g: null, min_final_gm: null };
    }

    const codes = new Set(itemKeywordCodes);
    let merged: ItemModifierPrice = {
      factor: price.factor,
      add_gm: price.add_gm,
      add_gm_per_100g: price.add_gm_per_100g,
      min_final_gm: price.min_final_gm,
    };
    const map = price.by_keyword ?? null;
    if (!map) return merged;

    for (const keyword of ITEM_MODIFIER_PRICE_KEYWORD_PRIORITY) {
      const entry = map[keyword];
      if (!entry || !codes.has(keyword)) continue;
      merged = {
        factor: entry.factor !== undefined ? entry.factor : merged.factor,
        add_gm: entry.add_gm !== undefined ? entry.add_gm : merged.add_gm,
        add_gm_per_100g: entry.add_gm_per_100g !== undefined ? entry.add_gm_per_100g : merged.add_gm_per_100g,
        min_final_gm: entry.min_final_gm !== undefined ? entry.min_final_gm : merged.min_final_gm,
      };
      break;
    }

    return merged;
  }

  private realWeightGrams(weight: DimensionalNumberValue | null | undefined): number {
    if (!weight) return 0;

    return Math.round(this.weightKg(weight) * 1000);
  }

  /** Килограммы без floor toNumber: у веса база дробная ({0.5|0} = 0.5 кг). */
  private weightKg(weight: DimensionalNumberValue): number {
    return weight.base * Math.pow(2, weight.size);
  }
}
