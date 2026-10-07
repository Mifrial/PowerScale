import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';
import { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/Service/Instance/aggregateSourceDeltasService';
import {
  ITEM_MODIFIER_CRAFT_KEYWORD_FACTOR,
  ITEM_MODIFIER_CRAFT_QUALITY_TYPE,
  ITEM_MODIFIER_IMPROVISED_CODE,
} from '@/modules/Roleplay/Rule/Constant/Item/ITEM_MODIFIER_CRAFT_QUALITY';
import { ITEM_MODIFIER_PRICE_KEYWORD_PRIORITY } from '@/modules/Roleplay/Rule/Constant/Item/ITEM_MODIFIER_PRICE_KEYWORD_PRIORITY';
import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';
import type { ItemModifierApplies } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierApplies';
import type { ItemModifierPrice } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierPrice';
import type { ItemModifierSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierSpec';
import type { ItemModifierOp } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierOp';
import type { ItemModifierOperation } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierOperation';
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
  keywordCodes(rule: Rule, keywords: readonly { id: number; code: string }[]): string[] {
    const byId = new Map(keywords.map((keyword) => [keyword.id, keyword.code]));

    return (rule.keywordIds ?? []).map((id) => byId.get(id)).filter((code): code is string => Boolean(code));
  }

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

  identityKey(ruleCode: string, modifierRuleCodes: readonly string[] | undefined): string {
    const sorted = [...(modifierRuleCodes ?? [])].filter((id) => id.length > 0).sort();

    return `${ruleCode}|${sorted.join(',')}`;
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

    const incoming = rules.find((rule) => rule.code === modifierId);
    const incomingSpec =
      incoming?.type === 'item_modifier' ? (incoming.spec as ItemModifierSpec | undefined) : undefined;
    const typeCode = incomingSpec?.type_code ?? '';
    const typeRule = rules.find((rule) => rule.type === 'item_modifier_type' && rule.code === typeCode);
    const exclusive = typeRule ? ((typeRule.spec as ItemModifierTypeSpec | undefined)?.exclusive ?? true) : false;
    if (!exclusive || !typeCode) return [...selected, modifierId];

    const kept = selected.filter((id) => {
      const rule = rules.find((entry) => entry.code === id);
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

  /**
   * Признаки предмета после keyword-ops всего стека (сначала add, потом remove).
   * Нужны для применимости набора (оковка даёт metal → серебро).
   */
  effectiveKeywordCodes(itemKeywordCodes: readonly string[], modifiers: readonly Rule[]): string[] {
    const keywords = new Set(itemKeywordCodes);
    for (const rule of modifiers) {
      if (rule.type !== 'item_modifier') continue;
      const modifier = rule.spec as ItemModifierSpec | undefined;
      for (const operation of modifier?.operations ?? []) {
        if (operation.type !== 'keyword' || !this.operationMatches(operation, itemKeywordCodes)) continue;
        for (const code of operation.add ?? []) keywords.add(code);
      }
    }
    for (const rule of modifiers) {
      if (rule.type !== 'item_modifier') continue;
      const modifier = rule.spec as ItemModifierSpec | undefined;
      for (const operation of modifier?.operations ?? []) {
        if (operation.type !== 'keyword' || !this.operationMatches(operation, itemKeywordCodes)) continue;
        for (const code of operation.remove ?? []) keywords.delete(code);
      }
    }

    return [...keywords];
  }

  applyStack(
    baseSpec: ItemSpec,
    modifiers: readonly Rule[],
    itemKeywordCodes: readonly string[],
  ): { spec: ItemSpec; cost: number; keywordCodes: string[] } {
    const spec = cloneData(baseSpec);
    let cost = spec.cost_gm ?? 0;
    const zeroImprovised = this.isImprovisedZeroPrice(modifiers);
    for (const rule of modifiers) {
      if (rule.type !== 'item_modifier') continue;
      const modifier = rule.spec as ItemModifierSpec | undefined;
      if (!modifier) continue;
      this.applyOperations(spec, modifier.operations ?? [], itemKeywordCodes, rule);
      if (zeroImprovised) continue;
      cost = this.computeScaledPrice(cost, modifier, itemKeywordCodes, spec.weight, modifiers);
    }
    if (zeroImprovised) cost = 0;
    if (spec.advantages?.length) spec.advantages = aggregateSourceDeltasService.aggregateSourceDeltas(spec.advantages);

    return { spec, cost, keywordCodes: this.effectiveKeywordCodes(itemKeywordCodes, modifiers) };
  }

  private applyOperations(
    spec: ItemSpec,
    operations: readonly ItemModifierOperation[],
    itemKeywordCodes: readonly string[],
    rule: Rule,
  ): void {
    const matched = operations.filter((operation) => this.operationMatches(operation, itemKeywordCodes));
    const collapsed = this.collapseOperations(matched.filter((operation) => this.isCollapsedOperation(operation)));
    const plain = matched.filter((operation) => !this.isCollapsedOperation(operation));
    for (const operation of [...collapsed, ...plain]) {
      this.applyScoped(spec, operation, rule);
    }
  }

  private operationMatches(operation: ItemModifierOperation, itemKeywordCodes: readonly string[]): boolean {
    return this.isApplicable(operation.when, itemKeywordCodes);
  }

  private isCollapsedOperation(operation: ItemModifierOperation): boolean {
    if (operation.type === 'strength_penalty' && operation.set !== undefined) return false;

    return (
      operation.type === 'weight' ||
      operation.type === 'block' ||
      operation.type === 'defense' ||
      operation.type === 'min_strength' ||
      operation.type === 'durability' ||
      operation.type === 'max_agility' ||
      operation.type === 'strength_penalty' ||
      operation.type === 'action_strength'
    );
  }

  private collapseOperations(operations: readonly ItemModifierOperation[]): ItemModifierOperation[] {
    const groups = new Map<string, ItemModifierOperation[]>();
    let unique = 0;
    for (const operation of operations) {
      const source = operation.source_code ?? `\0${unique}`;
      unique += 1;
      const key = `${this.partKey(operation)}|${operation.type}|${this.actionKey(operation)}|${source}`;
      const group = groups.get(key) ?? [];
      group.push(operation);
      groups.set(key, group);
    }

    return [...groups.values()].map((group) => this.collapseGroup(group));
  }

  private partKey(operation: ItemModifierOperation): string {
    return this.componentScopes(operation).join(',');
  }

  private actionKey(operation: ItemModifierOperation): string {
    if (operation.type !== 'action_strength') return '';

    return `${operation.field}|${(operation.profiles ?? []).join(',')}|${(operation.damage_type_codes ?? []).join(',')}`;
  }

  private collapseGroup(group: ItemModifierOperation[]): ItemModifierOperation {
    const bySource = new Map<string, ItemModifierOperation[]>();
    group.forEach((operation, index) => {
      const source = operation.source_code ?? `\0${index}`;
      const rows = bySource.get(source) ?? [];
      rows.push(operation);
      bySource.set(source, rows);
    });
    let factor = 1;
    let delta = 0;
    let size = 0;
    for (const rows of bySource.values()) {
      const one = this.collapseSource(rows);
      factor *= one.factor;
      delta += one.delta;
      size += one.size;
    }
    const first = group[0];
    if (!first) return group[0];

    return this.withCollapsedNumbers(first, factor, delta, size);
  }

  private collapseSource(rows: readonly ItemModifierOperation[]): { factor: number; delta: number; size: number } {
    let bonus: number | null = null;
    let penalty: number | null = null;
    let plus: number | null = null;
    let minus: number | null = null;
    let sizePlus: number | null = null;
    let sizeMinus: number | null = null;
    for (const operation of rows) {
      bonus = this.strongerBonus(bonus, this.operationFactor(operation));
      penalty = this.strongerPenalty(penalty, this.operationFactor(operation));
      plus = this.strongerPlus(plus, this.operationDelta(operation));
      minus = this.strongerMinus(minus, this.operationDelta(operation));
      sizePlus = this.strongerPlus(sizePlus, this.operationSize(operation));
      sizeMinus = this.strongerMinus(sizeMinus, this.operationSize(operation));
    }

    return {
      factor: (bonus ?? 1) * (penalty ?? 1),
      delta: (plus ?? 0) + (minus ?? 0),
      size: (sizePlus ?? 0) + (sizeMinus ?? 0),
    };
  }

  private strongerBonus(current: number | null, factor: number | null): number | null {
    if (factor === null || factor <= 1) return current;
    if (current === null || factor > current) return factor;

    return current;
  }

  private strongerPenalty(current: number | null, factor: number | null): number | null {
    if (factor === null || factor >= 1) return current;
    if (current === null || factor < current) return factor;

    return current;
  }

  private strongerPlus(current: number | null, delta: number): number | null {
    if (delta <= 0) return current;
    if (current === null || delta > current) return delta;

    return current;
  }

  private strongerMinus(current: number | null, delta: number): number | null {
    if (delta >= 0) return current;
    if (current === null || delta < current) return delta;

    return current;
  }

  private operationFactor(operation: ItemModifierOperation): number | null {
    if (operation.type === 'weight' || operation.type === 'block' || operation.type === 'defense') {
      return operation.factor ?? null;
    }

    return null;
  }

  private operationDelta(operation: ItemModifierOperation): number {
    if (operation.type === 'weight') return operation.add_kg ?? 0;
    if (operation.type === 'block' || operation.type === 'defense' || operation.type === 'strength_penalty') {
      return operation.add ?? 0;
    }
    if (operation.type === 'min_strength' || operation.type === 'action_strength') return operation.delta;
    if (operation.type === 'durability' || operation.type === 'max_agility') return operation.delta ?? 0;

    return 0;
  }

  private operationSize(operation: ItemModifierOperation): number {
    if (
      operation.type === 'block' ||
      operation.type === 'defense' ||
      operation.type === 'durability' ||
      operation.type === 'max_agility'
    ) {
      return operation.add_size ?? 0;
    }

    return 0;
  }

  private withCollapsedNumbers(
    operation: ItemModifierOperation,
    factor: number,
    delta: number,
    size: number,
  ): ItemModifierOperation {
    if (operation.type === 'weight') {
      return { ...operation, factor: factor === 1 ? undefined : factor, add_kg: delta === 0 ? undefined : delta };
    }
    if (operation.type === 'block' || operation.type === 'defense') {
      return {
        ...operation,
        factor: factor === 1 ? undefined : factor,
        add: delta === 0 ? undefined : delta,
        add_size: size === 0 ? undefined : size,
      };
    }
    if (operation.type === 'min_strength' || operation.type === 'action_strength') {
      return { ...operation, delta };
    }
    if (operation.type === 'durability' || operation.type === 'max_agility') {
      return { ...operation, delta: delta === 0 ? undefined : delta, add_size: size === 0 ? undefined : size };
    }
    if (operation.type === 'strength_penalty') {
      return { ...operation, add: delta === 0 ? undefined : delta };
    }

    return operation;
  }

  private componentScopes(operation: ItemModifierOperation): ItemModifierApplyScope[] {
    if (operation.type === 'weight') return ['all'];
    const codes = [...(operation.when?.keyword_all ?? []), ...(operation.when?.keyword_any ?? [])];
    const scopes: ItemModifierApplyScope[] = [];
    if (codes.includes('weapon')) scopes.push('weapon');
    if (codes.includes('shield-item')) scopes.push('shield');
    if (codes.includes('armor-item')) scopes.push('armor');
    if (scopes.length === 0 || scopes.length === 3) return ['all'];

    return scopes;
  }

  private applyScoped(spec: ItemSpec, operation: ItemModifierOperation, rule: Rule): void {
    const scopes = this.componentScopes(operation);
    const keywords = new Set<string>();
    if (operation.type === 'weight' || operation.type === 'block' || scopes.length === 1) {
      this.applyOp(spec, operation, scopes[0] ?? 'all', rule, keywords);

      return;
    }
    for (const scope of scopes) {
      this.applyOp(spec, operation, scope, rule, keywords);
    }
  }

  private applyOp(
    spec: ItemSpec,
    op: ItemModifierOperation,
    scope: ItemModifierApplyScope,
    rule: Rule,
    keywords: Set<string>,
  ): void {
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
        this.applyBlock(spec, op);

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
        this.applyActionStrength(spec, op, scope);

        return;
      case 'resistance':
        this.applyResistance(spec, op, scope);

        return;
      case 'keyword':
        for (const code of op.add ?? []) keywords.add(code);
        for (const code of op.remove ?? []) keywords.delete(code);

        return;
      case 'min_action_cost':
        if (this.includesWeapon(scope) && spec.weapon) {
          spec.weapon.min_action_cost = Math.max(spec.weapon.min_action_cost ?? 0, op.min);
        }

        return;
      case 'magic_conductor':
        spec.magic_conductor = Math.max(spec.magic_conductor ?? 0, op.value);

        return;
      case 'advantage':
        spec.advantages = [
          ...(spec.advantages ?? []),
          { source_code: op.source_code, source_label: rule.name, delta: op.delta },
        ];

        return;
      case 'check_advantage':
        spec.check_advantages = [
          ...(spec.check_advantages ?? []),
          {
            delta: op.delta,
            characteristic_codes: [...op.characteristic_codes],
            includes_hit: op.includes_hit,
          },
        ];

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

  private applyBlock(spec: ItemSpec, op: Extract<ItemModifierOp, { type: 'block' }>): void {
    if (!spec.block_profile) return;
    spec.block_profile.defense = this.scaleDefense(spec.block_profile.defense, op);
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
    op: Extract<ItemModifierOperation, { type: 'action_strength' }>,
    scope: ItemModifierApplyScope,
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
      this.pushActionDelta(formula, op.delta, op.source_code ?? null);
    }
  }

  private pushActionDelta(formula: Formula, delta: number, sourceCode: string | null): void {
    if (formula.type !== 'actionCharacteristic') return;
    formula.modifier.push({ delta, source_code: sourceCode, source_label: null });
  }

  private applyResistance(
    spec: ItemSpec,
    op: Extract<ItemModifierOp, { type: 'resistance' }>,
    scope: ItemModifierApplyScope,
  ): void {
    const slots: ResistanceSlot[][] = [];
    if ((this.includesWeapon(scope) || this.includesShield(scope)) && spec.block_profile) {
      slots.push(spec.block_profile.resistances);
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
        durability: null,
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

  private computeScaledPrice(
    baseCostGm: number,
    modifier: ItemModifierSpec,
    itemKeywordCodes: readonly string[],
    weight: DimensionalNumberValue | null | undefined,
    stack: readonly Rule[],
  ): number {
    const next = this.computePrice(baseCostGm, modifier.price, itemKeywordCodes, weight);
    const delta = next - baseCostGm;
    const scale = this.priceScaleFor(modifier, delta, itemKeywordCodes, stack);
    if (scale === 1) return next;

    return baseCostGm + Math.round(delta * scale);
  }

  private priceScaleFor(
    modifier: ItemModifierSpec,
    delta: number,
    itemKeywordCodes: readonly string[],
    stack: readonly Rule[],
  ): number {
    let scale = 1;
    if (modifier.type_code === ITEM_MODIFIER_CRAFT_QUALITY_TYPE) {
      const codes = new Set(itemKeywordCodes);
      if (codes.has('very-hard-to-craft')) scale *= ITEM_MODIFIER_CRAFT_KEYWORD_FACTOR['very-hard-to-craft'];
      else if (codes.has('hard-to-craft')) scale *= ITEM_MODIFIER_CRAFT_KEYWORD_FACTOR['hard-to-craft'];
      else if (codes.has('easy-to-craft')) scale *= ITEM_MODIFIER_CRAFT_KEYWORD_FACTOR['easy-to-craft'];
    }
    for (const rule of stack) {
      if (rule.type !== 'item_modifier') continue;
      const spec = rule.spec as ItemModifierSpec | undefined;
      const extra = spec?.price_scale;
      if (!extra || extra.type_code !== modifier.type_code) continue;
      if (extra.increasing_only && delta <= 0) continue;
      scale *= extra.factor;
    }

    return scale;
  }

  /** Импровизированное одно (или только с модами, уменьшающими размер) — цена 0. */
  private isImprovisedZeroPrice(modifiers: readonly Rule[]): boolean {
    const itemModifiers = modifiers.filter((rule) => rule.type === 'item_modifier');
    if (!itemModifiers.some((rule) => rule.code === ITEM_MODIFIER_IMPROVISED_CODE)) return false;

    return itemModifiers.every(
      (rule) => rule.code === ITEM_MODIFIER_IMPROVISED_CODE || this.isSizeReducingModifier(rule),
    );
  }

  private isSizeReducingModifier(rule: Rule): boolean {
    const spec = rule.spec as ItemModifierSpec | undefined;
    if (!spec) return false;
    const price = spec.price;
    if ((price?.factor ?? 1) !== 1) return false;
    if ((price?.add_gm ?? 0) !== 0) return false;
    if ((price?.add_gm_per_100g ?? 0) !== 0) return false;
    if (price?.min_final_gm != null) return false;
    const ops = spec.operations ?? [];
    if (ops.length === 0) return false;

    return ops.every((op) => {
      if (op.type !== 'durability' && op.type !== 'block' && op.type !== 'defense' && op.type !== 'max_agility') {
        return false;
      }
      if (op.add_size === undefined || op.add_size >= 0) return false;
      if ('delta' in op && op.delta !== undefined) return false;
      if ('factor' in op && op.factor !== undefined) return false;
      if ('add' in op && op.add !== undefined) return false;

      return true;
    });
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
