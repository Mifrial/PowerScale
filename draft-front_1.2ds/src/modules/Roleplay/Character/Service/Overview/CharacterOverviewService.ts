import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { CharacteristicSpec } from '@/modules/Roleplay/Rule/Dto/CharacteristicSpec';
import type { ResourceSpec } from '@/modules/Roleplay/Rule/Dto/ResourceSpec';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { ActionComponent } from '@/modules/Roleplay/Rule/Dto/Ability/ActionComponent';
import type { SpellDuration } from '@/modules/Roleplay/Rule/Dto/Ability/SpellDuration';
import type { CharacteristicGroup } from '@/modules/Roleplay/Rule/Enum/CharacteristicGroup';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacteristicValue } from '@/modules/Roleplay/Character/Dto/CharacteristicValue';
import type { CharacteristicModifier } from '@/modules/Roleplay/Character/Dto/CharacteristicModifier';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { CharacteristicOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacteristicOverview';
import type { DerivedCharacteristicOverview } from '@/modules/Roleplay/Character/Dto/Overview/DerivedCharacteristicOverview';
import type { CombatOverview } from '@/modules/Roleplay/Character/Dto/Overview/CombatOverview';
import type { ResourceOverview } from '@/modules/Roleplay/Character/Dto/Overview/ResourceOverview';
import type { ResourceLimitOverview } from '@/modules/Roleplay/Character/Dto/Overview/ResourceLimitOverview';
import type { ResourceValue } from '@/modules/Roleplay/Character/Dto/ResourceValue';
import type { MiscItemOverview } from '@/modules/Roleplay/Character/Dto/Overview/MiscItemOverview';
import type { AbilityOverview } from '@/modules/Roleplay/Character/Dto/Overview/AbilityOverview';
import type { InventoryItemOverview } from '@/modules/Roleplay/Character/Dto/Overview/InventoryItemOverview';
import type { OverviewModifier } from '@/modules/Roleplay/Character/Dto/Overview/OverviewModifier';
import type { DefenseOverview } from '@/modules/Roleplay/Character/Dto/Overview/DefenseOverview';
import type { DefenseArmorOverview } from '@/modules/Roleplay/Character/Dto/Overview/DefenseOverview';
import type { DefenseLineOverview } from '@/modules/Roleplay/Character/Dto/Overview/DefenseOverview';
import type { DefenseShieldOverview } from '@/modules/Roleplay/Character/Dto/Overview/DefenseOverview';
import type { DefenseTierOverview } from '@/modules/Roleplay/Character/Dto/Overview/DefenseOverview';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { WeaponProfile } from '@/modules/Roleplay/Rule/Dto/Item/WeaponProfile';
import type { FormulaContext } from '@/modules/Roleplay/Character/Dto/FormulaContext';
import { FormulaEvaluationService } from '@/modules/Roleplay/Character/Service/FormulaEvaluationService';
import { CHARACTERISTIC_BASE_RANGE } from '@/modules/Roleplay/Character/Constant/CHARACTERISTIC_BASE_RANGE';
import { moneyBreakdownLabel } from '@/modules/Roleplay/Character/Utils/moneyBreakdown';
import { CharacterReferenceService } from '@/modules/Roleplay/Character/Service/CharacterReferenceService';
import {
  derivedCharacteristicService,
  ACTION_POINTS_RESOURCE_CODE,
  DAMAGE_TYPE_FORMS,
  itemModifierService,
  formatStateEffectsService,
} from '@/modules/Roleplay/Rule/init';
import type { ItemLabels } from '@/modules/Roleplay/Character/Constant/ITEM_LABELS';
import { ITEM_LABELS } from '@/modules/Roleplay/Character/Constant/ITEM_LABELS';
import { stateRuntimeEffectsService } from '@/modules/Roleplay/Character/Service/Instance/stateRuntimeEffectsService';
import { liveActionPointsLimitService } from '@/modules/Roleplay/Character/Service/Instance/liveActionPointsLimitService';
import { racialInnateGearService } from '@/modules/Roleplay/Character/Service/Instance/racialInnateGearService';
import { weaponAttackRangeService } from '@/modules/Roleplay/Character/Service/Instance/weaponAttackRangeService';
import { DEFAULT_FALLOFF } from '@/modules/Roleplay/Character/Constant/Weapon/DEFAULT_FALLOFF';
import { WEAPON_PROFILE_LABELS } from '@/modules/Roleplay/Character/Constant/WEAPON_PROFILE_LABELS';
import { formulaLabel } from '@/modules/Roleplay/Character/Utils/formulaLabel';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { CharacterPoisonValue } from '@/modules/Roleplay/Character/Dto/CharacterPoisonValue';
import type { StateEntryOverview } from '@/modules/Roleplay/Character/Dto/Overview/StateOverview';
import type { StateSpec } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
import type { StateEffect } from '@/modules/Roleplay/Rule/Dto/State/StateEffect';
import type { StatePeriodicity } from '@/modules/Roleplay/Rule/Dto/State/Periodicity';
import type { StateDecay } from '@/modules/Roleplay/Rule/Dto/State/StateDecay';
import type { PoisonSpec } from '@/modules/Roleplay/Rule/Dto/Poison/PoisonSpec';
import type { ResolvedReference } from '@/modules/Roleplay/Character/Dto/Overview/ResolvedReference';

/**
 * Собирает display-модель вкладки «Обзор» из версии персонажа (ссылки + вычисленное)
 * и правил ревизии. Версия — источник фактов (итоги), правила — источник имён/формул/категорий.
 */
export class CharacterOverviewService {
  constructor(
    private readonly formula: FormulaEvaluationService = new FormulaEvaluationService(),
    private readonly itemLabels: ItemLabels = ITEM_LABELS,
    private readonly derivedCharacteristics = derivedCharacteristicService,
    private readonly itemModifiers = itemModifierService,
    private readonly stateEffects = formatStateEffectsService,
    private readonly runtimeEffects = stateRuntimeEffectsService,
    private readonly liveActionPoints = liveActionPointsLimitService,
    private readonly racialInnateGear = racialInnateGearService,
    private readonly weaponAttackRange = weaponAttackRangeService,
  ) {}

  build(version: CharacterVersion, rules: Rule[]): CharacterOverview {
    const { synced, reference, withStates, context } = this.prepared(version, rules);
    const resources = this.buildResources(synced, reference, rules, withStates);
    const abilities = this.buildAbilities(synced, reference);
    const inventory = this.buildInventory(synced, reference);

    return {
      characteristics: withStates.filter((overview) => overview.group !== 'combat' && overview.group !== 'base'),
      combat: this.buildCombat(synced, withStates, reference),
      resources,
      abilities,
      misc: this.buildMisc(synced),
      inventory,
      defense: this.buildDefense(synced, reference),
      attacks: this.buildAttacks(synced, reference, context),
      states: this.buildStates(synced, reference),
    };
  }

  /** Урон/пробитие профиля с силой действия, урезанной за дистанцию. */
  attackAtDistance(
    version: CharacterVersion,
    rules: Rule[],
    itemRuleCode: string,
    profileType: 'strike' | 'throw' | 'shoot',
    distanceIpari: number,
    profileIndex?: number,
    actionCharacteristicModifier = 0,
  ): AttackOverview | null {
    const { synced, reference, context } = this.prepared(version, rules);

    return (
      this.buildAttacks(synced, reference, context, {
        itemRuleCode,
        profileType,
        distanceIpari,
        actionCharacteristicModifier,
      }).find(
        (item) =>
          item.itemRuleCode === itemRuleCode &&
          item.profileType === profileType &&
          (profileIndex === undefined || item.profileIndex === profileIndex),
      ) ?? null
    );
  }

  private prepared(
    version: CharacterVersion,
    rules: Rule[],
  ): {
    synced: CharacterVersion;
    reference: CharacterReferenceService;
    withStates: CharacteristicOverview[];
    context: FormulaContext;
  } {
    const synced = this.racialInnateGear.applyRacialInnateGear(version, rules);
    const reference = new CharacterReferenceService(rules, synced.spaceCode, synced.rulesRevision);
    const abilityLevels = this.maxAbilityLevelsByRuleId(synced.abilities);
    const coreList = synced.characteristics.map((value) =>
      this.buildCharacteristicCore(value, reference, abilityLevels, synced),
    );
    const byCode = new Map<string, CharacteristicOverview>();
    for (const overview of coreList) {
      const rule = reference.ruleByCode(overview.ruleCode);
      if (rule) byCode.set(rule.code, overview);
    }
    this.recomputeDerivedValues(coreList, byCode);
    const allCharacteristics = coreList.map((overview) => {
      if (overview.derived === null) return overview;

      return { ...overview, derived: this.buildDerived(overview.derived.formula, byCode) };
    });
    const stateEffects = this.runtimeEffects.accumulateStateEffects(version.states, rules);
    const withStates = allCharacteristics.map((overview) => {
      const rule = reference.ruleByCode(overview.ruleCode);
      const amount = rule ? (stateEffects.characteristicDeltas.get(rule.code) ?? 0) : 0;
      if (!amount) return overview;
      const value = new DimensionalNumber(overview.value).modify(amount, CHARACTERISTIC_BASE_RANGE).value;

      return {
        ...overview,
        value,
        valueLabel: new DimensionalNumber(value).toString(),
        delta: overview.delta + amount,
      };
    });

    return { synced, reference, withStates, context: this.buildFormulaContext(withStates, synced, reference) };
  }

  private buildFormulaContext(
    coreList: CharacteristicOverview[],
    version: CharacterVersion,
    reference: CharacterReferenceService,
  ): FormulaContext {
    const characteristicValues = new Map<string, DimensionalNumberValue>();
    for (const overview of coreList) {
      const rule = reference.ruleByCode(overview.ruleCode);
      if (rule) characteristicValues.set(rule.code, overview.value);
    }

    const abilityLevels = new Map<string, number>();
    for (const ability of version.abilities) {
      const rule = reference.ruleByCode(ability.ruleCode);
      if (!rule) continue;
      const current = abilityLevels.get(rule.code) ?? 0;
      if (ability.level > current) abilityLevels.set(rule.code, ability.level);
    }

    return { characteristicValues, abilityLevels };
  }

  private buildCharacteristicCore(
    value: CharacteristicValue,
    reference: CharacterReferenceService,
    abilityLevels: Map<string, number>,
    version: CharacterVersion,
  ): CharacteristicOverview {
    const resolved = reference.resolve(value.ruleCode);
    const spec = this.characteristicSpecOf(resolved.rule);
    const formula = spec?.formula ?? null;
    const modifiers = value.modifiers
      .filter((modifier) => this.limitModifierIsLive(modifier, version))
      .map((modifier) => this.buildModifier(modifier, reference, abilityLevels));
    const permanent = this.aggregateModifiers(modifiers.filter((modifier) => modifier.scope === null));
    const delta = permanent.reduce((sum, modifier) => sum + modifier.delta, 0);
    const uncapped: DimensionalNumberValue = new DimensionalNumber(value.base).modify(
      delta,
      CHARACTERISTIC_BASE_RANGE,
    ).value;
    const computedValue = this.applyCharacteristicLimits(uncapped, permanent);

    return {
      ruleCode: value.ruleCode,
      name: resolved.name,
      shortName: null,
      base: value.base,
      baseLabel: new DimensionalNumber(value.base).toString(),
      value: computedValue,
      valueLabel: new DimensionalNumber(computedValue).toString(),
      delta,
      href: resolved.href,
      isResolved: resolved.isResolved,
      group: this.groupOf(spec),
      subtitle: null,
      modifiers: permanent,
      conditionalModifiers: modifiers.filter((modifier) => modifier.scope !== null),
      derived: formula === null ? null : { formula, label: null, bases: [] },
    };
  }

  /** Потолок экипировки не в delta: значение — min(база+моды, самый жёсткий limit). */
  private applyCharacteristicLimits(
    value: DimensionalNumberValue,
    modifiers: OverviewModifier[],
  ): DimensionalNumberValue {
    let current = value;
    for (const modifier of modifiers) {
      if (modifier.limit == null) continue;
      if (new DimensionalNumber(current).compare(new DimensionalNumber(modifier.limit)) > 0) {
        current = modifier.limit;
      }
    }

    return current;
  }

  /** Потолок с предмета, который уже снят, не показываем и не применяем. */
  private limitModifierIsLive(modifier: CharacteristicModifier, version: CharacterVersion): boolean {
    if (modifier.limit == null) return true;
    if (modifier.sourceRuleCode == null) return true;

    return version.inventory.some((item) => item.equipped && item.ruleCode === modifier.sourceRuleCode);
  }

  /** Производные — min/max живых баз (после потолков), не снимок в версии. */
  private recomputeDerivedValues(
    coreList: CharacteristicOverview[],
    byCode: Map<string, CharacteristicOverview>,
  ): void {
    for (const overview of coreList) {
      if (overview.derived === null) continue;
      const next = this.derivedCharacteristics.evaluateDerivedValue(
        overview.derived.formula,
        (code) => byCode.get(code)?.value,
      );
      if (next === null) continue;
      overview.value = next;
      overview.valueLabel = new DimensionalNumber(next).toString();
    }
  }

  private groupOf(spec: CharacteristicSpec | null): CharacteristicGroup {
    return spec?.group ?? 'primary';
  }

  private buildDerived(formula: string, byCode: Map<string, CharacteristicOverview>): DerivedCharacteristicOverview {
    const bases = this.derivedCodes(formula)
      .map((code) => byCode.get(code))
      .filter((base): base is CharacteristicOverview => base !== undefined);

    const label = bases.length >= 2 ? `${this.derivedOperatorLabel(formula)} →` : null;

    return { formula, label, bases };
  }

  private derivedOperatorLabel(formula: string): string {
    const match = formula.trim().match(/^(min|max)\(/i);

    return match?.[1].toLowerCase() === 'max' ? 'Максимальная из' : 'Минимальная из';
  }

  private derivedCodes(formula: string): string[] {
    const match = formula.match(/^(min|max)\(\s*([^,]+)\s*,\s*([^)]+)\)$/i);
    if (!match) return [];

    return [match[2].trim(), match[3].trim()];
  }

  private buildModifier(
    modifier: CharacteristicModifier,
    reference: CharacterReferenceService,
    abilityLevels: Map<string, number>,
  ): OverviewModifier {
    const source = modifier.sourceRuleCode === null ? null : reference.resolve(modifier.sourceRuleCode);
    const target = reference.ruleByCode(modifier.target);

    return {
      source: modifier.sourceLabel ?? source?.name ?? 'Персонаж',
      sourceRuleCode: modifier.sourceRuleCode,
      sourceHref: source?.href ?? null,
      sourceResolved: source?.isResolved ?? false,
      sourceRole: source?.rule ? this.sourceRoleOf(source.rule.type) : null,
      sourceLevel: modifier.sourceRuleCode === null ? null : (abilityLevels.get(modifier.sourceRuleCode) ?? null),
      delta: modifier.delta,
      target: target?.name ?? modifier.target,
      targetHref: target ? reference.href(target.code) : null,
      scope: modifier.scope,
      limit: modifier.limit ?? null,
      limitFormula: modifier.limitFormula ?? null,
    };
  }

  private sourceRoleOf(ruleType: string): string {
    switch (ruleType) {
      case 'ability':
        return 'от мастерства';
      case 'item':
        return 'от предмета';
      case 'race':
        return 'от расы';
      case 'species':
        return 'от вида';
      default:
        return 'от правила';
    }
  }

  /**
   * Модификаторы одного источника не складываются: из группы берётся один с наибольшим плюсом
   * и один с наибольшим минусом. Группа — конкретный источник (sourceRuleCode); модификаторы
   * от разных источников суммируются.
   */
  private aggregateModifiers(modifiers: OverviewModifier[]): OverviewModifier[] {
    const groups = new Map<string, OverviewModifier[]>();
    for (const modifier of modifiers) {
      const key = modifier.sourceRuleCode ?? 'прочее';
      const group = groups.get(key);
      if (group) group.push(modifier);
      else groups.set(key, [modifier]);
    }

    const result: OverviewModifier[] = [];
    for (const group of groups.values()) {
      let maxPositive = group[0];
      let maxNegative = group[0];
      for (const modifier of group) {
        if (modifier.delta >= maxPositive.delta) maxPositive = modifier;
        if (modifier.delta < maxNegative.delta) maxNegative = modifier;
      }
      if (!result.includes(maxPositive)) result.push(maxPositive);
      if (maxNegative !== maxPositive && !result.includes(maxNegative)) result.push(maxNegative);
    }

    return result;
  }

  private buildCombat(
    version: CharacterVersion,
    allCharacteristics: CharacteristicOverview[],
    reference: CharacterReferenceService,
  ): CombatOverview | null {
    const statOf = (code: string): CharacteristicOverview | undefined =>
      allCharacteristics.find((overview) => reference.ruleByCode(overview.ruleCode)?.code === code);
    const meleeStat = statOf('melee-combat');
    const rangedStat = statOf('ranged-combat');
    const proficiencyLevels = this.proficiencyLevelsOf(version, reference);

    const melee = meleeStat
      ? {
          stat: { ...meleeStat, shortName: 'Общее' },
          weapons: this.combatWeaponTiles(version, meleeStat, reference, 'melee', proficiencyLevels),
        }
      : null;
    const ranged = rangedStat
      ? {
          stat: { ...rangedStat, shortName: 'Общее' },
          weapons: this.combatWeaponTiles(version, rangedStat, reference, 'ranged', proficiencyLevels),
        }
      : null;
    if (!melee && !ranged) return null;

    return { melee, ranged };
  }

  /** Уровень «Владения оружием» по семьям: код/имя семьи → максимальный уровень среди экземпляров. */
  private proficiencyLevelsOf(version: CharacterVersion, reference: CharacterReferenceService): Map<string, number> {
    const result = new Map<string, number>();
    for (const ability of version.abilities) {
      const rule = reference.ruleByCode(ability.ruleCode);
      const spec = rule?.type === 'ability' ? (rule.spec as { domain_ref?: string | null } | undefined) : undefined;
      if (spec?.domain_ref !== 'weapon-family') continue;
      const family = ability.domainCode ?? ability.domain;
      if (!family) continue;
      const current = result.get(family) ?? 0;
      if (ability.level > current) result.set(family, ability.level);
    }

    return result;
  }

  /**
   * Тайлы мастерства экипированного оружия секции: база стата + бонус владения семьи.
   * Бонус владения в базовый стат не суммируется (R15) — только на тайле конкретного оружия.
   */
  private combatWeaponTiles(
    version: CharacterVersion,
    stat: CharacteristicOverview,
    reference: CharacterReferenceService,
    combat: 'melee' | 'ranged',
    proficiencyLevels: Map<string, number>,
  ): CharacteristicOverview[] {
    const result: CharacteristicOverview[] = [];
    for (const item of version.inventory) {
      if (!item.equipped || item.ruleCode === null) continue;
      const rule = reference.ruleByCode(item.ruleCode);
      const spec = this.effectiveSpecOf(item, reference);
      if (!spec?.weapon) continue;
      // Ближний бой — удары; дальний бой — метание и выстрелы (R14).
      const hasMelee = spec.weapon.weapon_profiles.some((profile) => profile.type === 'strike');
      const hasRanged = spec.weapon.weapon_profiles.some(
        (profile) => profile.type === 'throw' || profile.type === 'shoot',
      );
      if (combat === 'melee' && !hasMelee) continue;
      if (combat === 'ranged' && !hasRanged) continue;

      const familyCode = spec.proficiency_family_code ?? null;
      const bonus = familyCode === null ? 0 : (proficiencyLevels.get(familyCode) ?? 0);
      result.push(this.buildCombatWeaponTile(rule?.name ?? item.ruleCode, bonus, stat, combat));
    }

    return result;
  }

  private buildCombatWeaponTile(
    weaponName: string,
    bonus: number,
    stat: CharacteristicOverview,
    combat: 'melee' | 'ranged',
  ): CharacteristicOverview {
    const weaponModifier: OverviewModifier = {
      source: 'Владение оружием',
      sourceRuleCode: null,
      sourceHref: null,
      sourceResolved: true,
      sourceRole: 'от владения оружием',
      sourceLevel: bonus > 0 ? bonus : null,
      delta: bonus,
      target: stat.name,
      targetHref: stat.href,
      scope: null,
    };
    const aggregated = this.aggregateModifiers([...stat.modifiers, weaponModifier]);
    const delta = aggregated.reduce((sum, modifier) => sum + modifier.delta, 0);
    const value: DimensionalNumberValue = new DimensionalNumber(stat.base).modify(
      delta,
      CHARACTERISTIC_BASE_RANGE,
    ).value;

    return {
      // Секция в ruleCode, чтобы одинаковое оружие ближнего/дальнего боя не сливалось (избранное/броски).
      ruleCode: `combat:${combat}:${weaponName}`,
      name: weaponName,
      shortName: weaponName,
      base: stat.base,
      baseLabel: stat.baseLabel,
      value,
      valueLabel: new DimensionalNumber(value).toString(),
      delta,
      href: stat.href,
      isResolved: stat.isResolved,
      group: 'combat',
      subtitle: null,
      modifiers: aggregated,
      conditionalModifiers: stat.conditionalModifiers,
      derived: null,
    };
  }

  private buildResources(
    version: CharacterVersion,
    reference: CharacterReferenceService,
    rules: Rule[],
    characteristics: CharacteristicOverview[],
  ): ResourceOverview[] {
    const resources: ResourceOverview[] = [];

    for (const resource of version.resources) {
      const rule = reference.ruleByCode(resource.ruleCode);
      let max = this.resourceMax(resource);
      if (rule?.code === ACTION_POINTS_RESOURCE_CODE) {
        const values = new Map<string, DimensionalNumberValue>();
        for (const characteristic of characteristics) {
          const characteristicRule = reference.ruleByCode(characteristic.ruleCode);
          if (characteristicRule) values.set(characteristicRule.code, characteristic.value);
        }
        const live = this.liveActionPoints.liveActionPointsLimit(version, rules, values);
        if (live !== null) max = { base: live, size: resource.base.size };
      }
      const autoAdd = rule?.type === 'resource' && (rule.spec as ResourceSpec | undefined)?.auto_add === true;
      // Лимит 0: авто-ресурс (ОД) рендерится всегда (персонаж не может действовать, но ресурс есть);
      // не-авто ресурс с лимитом 0 — у персонажа отсутствует (D38), не показываем.
      if (max.base === 0 && !autoAdd) continue;

      const resolved = reference.resolve(resource.ruleCode);

      resources.push({
        ruleCode: resource.ruleCode,
        name: resolved.name,
        current: resource.current,
        currentLabel: new DimensionalNumber(resource.current).toString(),
        max,
        maxLabel: new DimensionalNumber(max).toString(),
        base: resource.base,
        baseLabel: new DimensionalNumber(resource.base).toString(),
        bonuses: this.buildResourceBonuses(resource, reference),
        href: resolved.href,
        isResolved: resolved.isResolved,
      });
    }

    return resources;
  }

  private resourceMax(resource: ResourceValue): DimensionalNumberValue {
    const delta = resource.bonuses.reduce((sum, bonus) => sum + bonus.delta, 0);

    return { base: Math.max(0, resource.base.base + delta), size: resource.base.size };
  }

  private buildResourceBonuses(resource: ResourceValue, reference: CharacterReferenceService): ResourceLimitOverview[] {
    return resource.bonuses.map((bonus) => {
      const sourceRule = bonus.sourceRuleCode === null ? null : reference.ruleByCode(bonus.sourceRuleCode);

      return {
        source: sourceRule?.name ?? bonus.sourceLabel ?? bonus.sourceRuleCode ?? '',
        sourceRuleCode: bonus.sourceRuleCode,
        sourceHref: bonus.sourceRuleCode === null ? null : reference.href(bonus.sourceRuleCode),
        delta: bonus.delta,
      };
    });
  }

  private maxAbilityLevelsByRuleId(abilities: CharacterVersion['abilities']): Map<string, number> {
    const levels = new Map<string, number>();
    for (const ability of abilities) {
      const current = levels.get(ability.ruleCode) ?? 0;
      if (ability.level > current) levels.set(ability.ruleCode, ability.level);
    }

    return levels;
  }

  private buildAbilities(version: CharacterVersion, reference: CharacterReferenceService): AbilityOverview[] {
    const collapsed = this.collapseAbilityRows(version.abilities, reference);
    const existingKeys = new Set(collapsed.map((ability) => this.abilityInstanceKey(ability, reference)));
    for (const rule of reference.rules()) {
      const spec = this.abilitySpecOf(rule);
      if (
        !spec ||
        spec.type === 'group' ||
        spec.multiple === true ||
        existingKeys.has(rule.code) ||
        !Object.values(spec.zones ?? {}).some((cost) => cost?.kind === 'automatic')
      ) {
        continue;
      }
      collapsed.push({ ruleCode: rule.code, level: 1 });
      existingKeys.add(rule.code);
    }

    return collapsed.map((ability) => {
      const resolved = reference.resolve(ability.ruleCode);
      const spec = this.abilitySpecOf(resolved.rule);
      const type = spec?.type ?? null;
      const domainLabel = this.abilityDomainLabel(ability, reference);

      let actionOdCost: DimensionalNumberValue | number | null = null;
      let spellCastCost: DimensionalNumberValue | number | null = null;
      let spellDifficulty: DimensionalNumberValue | null = null;
      let spellDurationLabel: string | null = null;

      if (spec?.type === 'action') {
        actionOdCost = this.actionPointsCost(spec.action_components);
      } else if (spec?.type === 'spell') {
        spellCastCost = this.actionPointsCost(spec.action_components);
        spellDifficulty = spec.spell.difficulty;
        spellDurationLabel = this.spellDurationLabel(spec.spell.duration);
      }

      return {
        ruleCode: ability.ruleCode,
        instanceKey: this.abilityInstanceKey(ability, reference),
        name: resolved.name,
        domainLabel,
        level: ability.level,
        hasParameters: ability.parameters !== undefined && Object.keys(ability.parameters).length > 0,
        type,
        description: resolved.rule?.description ?? '',
        keywordIds: resolved.rule?.keywordIds ?? [],
        actionOdCost,
        spellCastCost,
        spellDifficulty,
        spellDurationLabel,
        href: resolved.href,
        isResolved: resolved.isResolved,
      };
    });
  }

  /** Одна строка на способность; у multiple — на экземпляр домена. Уровень — максимум среди дублей. */
  private collapseAbilityRows(
    abilities: CharacterVersion['abilities'],
    reference: CharacterReferenceService,
  ): CharacterVersion['abilities'] {
    const byKey = new Map<string, CharacterVersion['abilities'][number]>();
    for (const ability of abilities) {
      const key = this.abilityInstanceKey(ability, reference);
      const current = byKey.get(key);
      if (!current || ability.level > current.level) byKey.set(key, ability);
    }

    return [...byKey.values()];
  }

  private abilityInstanceKey(
    ability: CharacterVersion['abilities'][number],
    reference: CharacterReferenceService,
  ): string {
    const spec = this.abilitySpecOf(reference.ruleByCode(ability.ruleCode));
    if (spec && spec.type !== 'group' && spec.multiple === true) {
      return `${ability.ruleCode}:${ability.domainCode ?? ''}:${ability.domain ?? ''}`;
    }

    return ability.ruleCode;
  }

  private abilityDomainLabel(
    ability: CharacterVersion['abilities'][number],
    reference: CharacterReferenceService,
  ): string | null {
    if (ability.domain) return ability.domain;
    if (!ability.domainCode) return null;

    return reference.ruleByCode(ability.domainCode)?.name ?? ability.domainCode;
  }

  /** Сумма стоимости в ОД по компонентам действия (resource_code action-points). */
  private actionPointsCost(components: ActionComponent[]): DimensionalNumberValue | number | null {
    const actionPoints = components.filter(
      (component): component is Extract<ActionComponent, { type: 'resource' }> =>
        component.type === 'resource' && component.resource_code === 'action-points',
    );
    if (actionPoints.length === 0) return null;
    if (actionPoints.length === 1) {
      const amount = actionPoints[0].amount;

      return typeof amount === 'object' && 'type' in amount ? null : amount;
    }

    let total = new DimensionalNumber({ base: 0, size: 0 });
    for (const component of actionPoints) {
      if (typeof component.amount === 'object' && 'type' in component.amount) return null;
      const amount = typeof component.amount === 'number' ? { base: component.amount, size: 0 } : component.amount;
      total = total.add(new DimensionalNumber(amount));
    }

    return total.value;
  }

  /** Компактный label длительности заклинания. */
  private spellDurationLabel(duration: SpellDuration): string {
    if (duration.type === 'instant') return 'Мгновенное';

    const base = duration.type === 'refreshable' ? 'Обновляемое' : 'Поддерживаемое';
    if (!duration.limit) return base;

    const unitLabel = ({ turn: 'ход', minute: 'мин', hour: 'час' } as Record<string, string>)[duration.limit.unit];

    return `${base} (${this.formatCostAmount(duration.limit.value)} ${unitLabel ?? duration.limit.unit})`;
  }

  private formatCostAmount(amount: DimensionalNumberValue | number): string {
    if (typeof amount === 'number') return String(amount);

    return new DimensionalNumber(amount).toString();
  }

  private buildMisc(version: CharacterVersion): MiscItemOverview[] {
    const olHave = version.points.olTotal - version.points.olSpent;
    // ОР без лимита (orTotal null) — остаток не считается, показывается «потрачено X» без итога.
    const orHave =
      version.points.orTotal === null ? version.points.orSpent : version.points.orTotal - version.points.orSpent;
    const orSubtitle =
      version.points.orTotal === null
        ? `потрачено ${version.points.orSpent}`
        : `потрачено ${version.points.orSpent} / всего ${version.points.orTotal}`;

    return [
      {
        code: 'os',
        label: 'Очки создания',
        valueLabel: String(version.points.osSpent),
        subtitle: 'потрачено при создании',
      },
      {
        code: 'ol',
        label: 'Очки личности',
        valueLabel: String(olHave),
        subtitle: `потрачено ${version.points.olSpent} / всего ${version.points.olTotal}`,
      },
      {
        code: 'or',
        label: 'Очки развития',
        valueLabel: String(orHave),
        subtitle: orSubtitle,
      },
      {
        code: 'money',
        label: 'Деньги',
        valueLabel: `${version.money} гм`,
        subtitle: moneyBreakdownLabel(version.money),
      },
    ];
  }

  private buildInventory(version: CharacterVersion, reference: CharacterReferenceService): InventoryItemOverview[] {
    return version.inventory.map((item) => {
      // Кастомный «предмет мастера» (ruleCode null): имя/описание задаёт мастер, правила нет.
      if (item.ruleCode === null) {
        return {
          id: item.id,
          ruleCode: null,
          name: item.name ?? 'Предмет мастера',
          categoryLabel: this.itemLabels.category.other,
          quantity: item.quantity,
          equipped: item.equipped,
          durabilityLeft: item.durabilityLeft ?? null,
          note: item.description ?? item.note ?? null,
          href: null,
          isResolved: false,
          modifierNames: [],
        };
      }
      const resolved = reference.resolve(item.ruleCode);
      const spec = this.itemSpecOf(resolved.rule);
      const modifierNames = (item.modifierRuleCodes ?? [])
        .map((id) => reference.resolve(id).name)
        .filter((name) => name.length > 0);

      return {
        id: item.id,
        ruleCode: item.ruleCode,
        name: resolved.name,
        categoryLabel: this.categoryLabelOf(spec),
        quantity: item.quantity,
        equipped: item.equipped,
        durabilityLeft: item.durabilityLeft ?? null,
        note: item.note ?? null,
        href: resolved.href,
        isResolved: resolved.isResolved,
        modifierNames,
      };
    });
  }

  private categoryLabelOf(spec: ItemSpec | null): string {
    if (spec?.weapon) return this.itemLabels.subtype.weapon;
    if (spec?.armor) return this.itemLabels.subtype.armor;
    if (spec?.shield) return this.itemLabels.subtype.shield;

    return this.itemLabels.category[spec?.category ?? 'other'];
  }

  /**
   * Состояния персонажа после объединения повторов одного правила.
   * sum/max — одна запись на правило (объединённое значение), independent —
   * по записи на каждый повтор (каждая Рана со своим кровотечением).
   */
  private buildStates(version: CharacterVersion, reference: CharacterReferenceService): StateEntryOverview[] {
    const grouped = new Map<string, CharacterStateValue[]>();
    for (const entry of version.states) {
      const list = grouped.get(entry.stateRuleCode);
      if (list) list.push(entry);
      else grouped.set(entry.stateRuleCode, [entry]);
    }

    const result: StateEntryOverview[] = [];
    for (const [ruleCode, entries] of grouped) {
      const resolved = reference.resolve(ruleCode);
      const rule = resolved.rule;
      const spec = rule?.type === 'state' ? (rule.spec as StateSpec) : null;

      // Отравление: блок poison на записи. Каждое навешенное отравление — своя строка,
      // имя/иконка могут браться из правила-яда, остальное — из параметров применения.
      if (spec?.value_type === 'flag' && entries.some((entry) => entry.poison)) {
        for (const [index, entry] of entries.entries()) {
          if (!entry.poison) continue;
          result.push(this.buildPoisonState(ruleCode, index, entry.poison, resolved, reference, spec));
        }
        continue;
      }

      if (!spec) {
        result.push({
          id: ruleCode,
          ruleCode,
          name: resolved.name,
          iconCode: null,
          valueLabel: null,
          count: entries.length,
          aggregation: 'sum',
          dotLabel: null,
          href: resolved.href ?? '',
          isResolved: resolved.isResolved,
        });
        continue;
      }

      if (spec.aggregation === 'independent') {
        for (const [index, entry] of entries.entries()) {
          result.push({
            id: `${ruleCode}#${index}`,
            ruleCode,
            name: resolved.name,
            iconCode: spec.icon_code ?? null,
            valueLabel: this.stateValueLabel(spec, entry),
            count: 1,
            aggregation: spec.aggregation,
            dotLabel: this.dotLabelOf(spec, reference),
            href: resolved.href ?? '',
            isResolved: resolved.isResolved,
          });
        }
        continue;
      }

      result.push({
        id: ruleCode,
        ruleCode,
        name: resolved.name,
        iconCode: spec.icon_code ?? null,
        valueLabel: this.combineStateValue(spec, entries),
        count: entries.length,
        aggregation: spec.aggregation,
        dotLabel: this.dotLabelOf(spec, reference),
        href: resolved.href ?? '',
        isResolved: resolved.isResolved,
      });
    }

    return result;
  }

  /** Строка отравления: имя/иконка из правила-да (если есть), параметры из poison-блока. */
  private buildPoisonState(
    stateRuleCode: string,
    index: number,
    poison: CharacterPoisonValue,
    stateResolved: ResolvedReference,
    reference: CharacterReferenceService,
    stateSpec: StateSpec,
  ): StateEntryOverview {
    const isLinked = poison.poisonRuleCode !== null && poison.poisonRuleCode !== undefined;
    const poisonResolved = isLinked ? reference.resolve(poison.poisonRuleCode ?? '') : null;
    const poisonSpec = poisonResolved?.rule?.type === 'poison' ? (poisonResolved.rule.spec as PoisonSpec) : null;

    const damageTypeCode = poison.damage_type_code ?? poisonSpec?.damage_type_code ?? '';
    const strength = poison.strength ?? poisonSpec?.default_strength ?? null;
    const periodicity = poison.periodicity ?? poisonSpec?.default_periodicity;
    const decay = poison.decay ?? poisonSpec?.default_decay;

    return {
      id: `${stateRuleCode}#poison-${index}`,
      ruleCode: poisonResolved?.ruleCode ?? stateRuleCode,
      name: poisonResolved?.name ?? stateResolved.name,
      iconCode: poisonSpec?.icon_code ?? stateSpec.icon_code ?? null,
      valueLabel: strength == null ? null : new DimensionalNumber(strength).toString(),
      count: 1,
      aggregation: 'independent',
      dotLabel: this.poisonDotLabel(damageTypeCode, strength, periodicity, decay, reference),
      href: poisonResolved?.href ?? stateResolved.href ?? '',
      isResolved: poisonResolved ? poisonResolved.isResolved : stateResolved.isResolved,
    };
  }

  /** Профиль отравления одной строкой: «Урон: 3↑ яд 1 типа, каждые 2 хода, затухание 1». */
  private poisonDotLabel(
    damageTypeCode: string,
    strength: DimensionalNumberValue | null,
    periodicity: StatePeriodicity | undefined,
    decay: StateDecay | undefined,
    reference: CharacterReferenceService,
  ): string | null {
    if (strength == null) return null;

    const damageTypeLabel = reference.ruleByCode(damageTypeCode)?.name ?? damageTypeCode;
    const parts = [`Урон: ${new DimensionalNumber(strength).toString()} ${damageTypeLabel.toLowerCase()}`];
    parts.push(this.stateEffects.periodicityLabel(periodicity));
    if (decay)
      parts.push(
        `затухание ${this.stateEffects.decayLabel(decay, (code) => reference.ruleByCode(code)?.name ?? code)}`,
      );

    return parts.join(', ');
  }

  private stateValueLabel(spec: StateSpec, entry: CharacterStateValue): string | null {
    if (spec.value_type === 'flag') return null;
    if (spec.value_type === 'number') return entry.value == null ? null : String(entry.value);
    if (entry.dimensionalValue == null) return null;

    return new DimensionalNumber(entry.dimensionalValue).toString();
  }

  /** Объединённое значение повторов по aggregation (sum/max). */
  private combineStateValue(spec: StateSpec, entries: CharacterStateValue[]): string | null {
    if (spec.value_type === 'flag') return null;
    if (spec.value_type === 'number') {
      const values = entries.map((entry) => entry.value ?? 0);
      const combined = spec.aggregation === 'max' ? Math.max(...values) : values.reduce((sum, v) => sum + v, 0);

      return String(combined);
    }

    const values = entries.map((entry) => new DimensionalNumber(entry.dimensionalValue ?? { base: 0, size: 0 }));
    let combined = values[0];
    for (let index = 1; index < values.length; index++) {
      combined =
        spec.aggregation === 'max'
          ? values[index].toNumber() > combined.toNumber()
            ? values[index]
            : combined
          : combined.add(values[index]);
    }

    return new DimensionalNumber(combined.value).toString();
  }

  /** Профиль урона со временем (первый DOT-эффект спеки) одной строкой. */
  private dotLabelOf(spec: StateSpec, reference: CharacterReferenceService): string | null {
    const dot = (spec.effects ?? []).find(
      (effect): effect is Extract<StateEffect, { type: 'damage_over_time' }> => effect.type === 'damage_over_time',
    );
    if (!dot) return null;

    return this.stateEffects.dotEffectLabel(dot, (code) => reference.ruleByCode(code)?.name ?? code);
  }

  private buildDefense(version: CharacterVersion, reference: CharacterReferenceService): DefenseOverview | null {
    const armor: DefenseArmorOverview[] = [];
    let shield: DefenseShieldOverview | null = null;

    for (const item of version.inventory) {
      if (!item.equipped || item.ruleCode === null) continue;
      const rule = reference.ruleByCode(item.ruleCode);
      const spec = this.effectiveSpecOf(item, reference);
      if (!spec) continue;
      if (spec.armor) {
        const lines: DefenseLineOverview[] = [];
        for (const slot of spec.armor.defense_slots) {
          const defense = new DimensionalNumber(slot.defense);
          lines.push({
            kind: 'defense',
            value: defense.toNumber(),
            valueLabel: defense.toString(),
            durability: slot.durability,
            sourceCode: slot.source_code,
            sourceLabel: this.sourceLabelOf(slot.source_code, reference),
            damageTypeLabel: null,
            damageTypeDative: null,
            damageTypeCode: null,
          });
        }
        for (const slot of spec.armor.resistance_slots) {
          const resistance = new DimensionalNumber(slot.value);
          const damageType = slot.damage_type_code === null ? null : reference.ruleByCode(slot.damage_type_code);
          lines.push({
            kind: 'resistance',
            value: resistance.toNumber(),
            valueLabel: resistance.toString(),
            durability: slot.durability,
            sourceCode: slot.source_code,
            sourceLabel: this.sourceLabelOf(slot.source_code, reference),
            damageTypeLabel: damageType?.name ?? null,
            damageTypeDative:
              slot.damage_type_code === null ? null : (DAMAGE_TYPE_FORMS[slot.damage_type_code]?.dative ?? null),
            damageTypeCode: slot.damage_type_code,
          });
        }
        const overview: DefenseArmorOverview = {
          itemRuleCode: item.ruleCode,
          itemName: rule?.name ?? item.ruleCode,
          href: reference.href(item.ruleCode),
          lines,
          tiers: [],
        };
        overview.tiers = this.defenseTiersOf([overview]);
        armor.push(overview);
      }

      if (spec.shield && shield === null) {
        shield = {
          itemRuleCode: item.ruleCode,
          itemName: rule?.name ?? item.ruleCode,
          href: reference.href(item.ruleCode),
          defense: new DimensionalNumber(spec.shield.block.defense).toString(),
          efficiency: new DimensionalNumber(spec.shield.block.efficiency).toString(),
          efficiencyValue: spec.shield.block.efficiency,
        };
      }
    }

    if (armor.length === 0 && shield === null) return null;

    const tiers = this.defenseTiersOf(armor);

    return {
      armor,
      constantDefense: this.constantDefenseOf(armor),
      tiers,
      shield,
    };
  }

  /**
   * Ступени защиты по надёжности. Слой с надёжностью N игнорируется, если РУ атаки ≥ N.
   * Ступень threshold — защита слоёв с надёжностью ≥ threshold. Внутри ступени — как
   * constantDefense: по источникам максимум, источники суммируются. Ступени по возрастанию threshold.
   */
  private defenseTiersOf(armor: DefenseArmorOverview[]): DefenseTierOverview[] {
    const thresholds = new Set<number>();
    for (const item of armor) {
      for (const line of item.lines) {
        if (line.kind !== 'defense') continue;
        thresholds.add(line.durability);
      }
    }

    return Array.from(thresholds)
      .sort((a, b) => a - b)
      .map((threshold) => ({ threshold, defense: this.defenseValueAt(armor, threshold) }));
  }

  private sourceLabelOf(sourceCode: string | null, reference: CharacterReferenceService): string | null {
    if (sourceCode === null) return null;

    return reference.ruleByCode(sourceCode)?.name ?? null;
  }

  /**
   * Защиты от одного источника не суммируются: из группы (source_code) берётся максимум,
   * группы разных источников складываются. Без source_code источник — сам предмет доспеха.
   */
  private constantDefenseOf(armor: DefenseArmorOverview[]): number {
    return this.defenseValueAt(armor, 0);
  }

  /** Совокупная защита по слоям с надёжностью ≥ minDurability (максимум по источнику, суммы по источникам). */
  private defenseValueAt(armor: DefenseArmorOverview[], minDurability: number): number {
    const groups = new Map<string, number>();
    for (const item of armor) {
      for (const line of item.lines) {
        if (line.kind !== 'defense') continue;
        if (line.durability < minDurability) continue;
        const key = line.sourceCode ?? item.itemRuleCode;
        const current = groups.get(key) ?? 0;
        if (line.value > current) groups.set(key, line.value);
      }
    }

    let total = 0;
    for (const value of groups.values()) total += value;

    return total;
  }

  private buildAttacks(
    version: CharacterVersion,
    reference: CharacterReferenceService,
    context: FormulaContext,
    atDistance?: {
      itemRuleCode: string;
      profileType: 'strike' | 'throw' | 'shoot';
      distanceIpari: number;
      profileIndex?: number;
      actionCharacteristicModifier?: number;
    },
  ): AttackOverview[] {
    const attacks: AttackOverview[] = [];

    for (const item of version.inventory) {
      if (!item.equipped || item.ruleCode === null) continue;
      const rule = reference.ruleByCode(item.ruleCode);
      const spec = this.effectiveSpecOf(item, reference);
      if (!spec?.weapon) continue;

      for (const [profileIndex, profile] of spec.weapon.weapon_profiles.entries()) {
        const distanceIpari =
          atDistance &&
          atDistance.itemRuleCode === item.ruleCode &&
          atDistance.profileType === profile.type &&
          (atDistance.profileIndex === undefined || atDistance.profileIndex === profileIndex)
            ? atDistance.distanceIpari
            : null;
        attacks.push(
          this.buildAttack(
            item.ruleCode,
            rule?.name ?? item.ruleCode,
            profile,
            reference,
            context,
            distanceIpari,
            profileIndex,
            atDistance?.actionCharacteristicModifier ?? 0,
          ),
        );
      }
    }

    return attacks;
  }

  private buildAttack(
    itemRuleCode: string,
    itemName: string,
    profile: WeaponProfile,
    reference: CharacterReferenceService,
    context: FormulaContext,
    distanceIpari: number | null = null,
    profileIndex?: number,
    actionCharacteristicModifier = 0,
  ): AttackOverview {
    const zeroCtx = this.weaponAttackRange.profileFormulaContext(
      profile,
      context,
      this.formula,
      0,
      actionCharacteristicModifier,
    );
    const minDistance = this.formula.evaluate(profile.distance, zeroCtx);
    const range = profile.range === null ? null : this.formula.evaluate(profile.range, zeroCtx);
    const reach = range ?? minDistance;
    const falloff = profile.falloff ?? DEFAULT_FALLOFF;
    const ranged = profile.type === 'throw' || profile.type === 'shoot';
    const penalty =
      ranged && distanceIpari != null
        ? this.weaponAttackRange.actionStrengthSizePenalty(distanceIpari, reach, falloff)
        : 0;
    const evalCtx = penalty
      ? this.weaponAttackRange.profileFormulaContext(
          profile,
          context,
          this.formula,
          penalty,
          actionCharacteristicModifier,
        )
      : zeroCtx;
    const accuracy = profile.accuracy;
    const damageValue = this.formula.evaluateDimensional(profile.damage.formula, evalCtx);
    const damage = new DimensionalNumber(damageValue).toString();
    const penetrationValue = this.formula.evaluateDimensional(profile.penetration, evalCtx);
    const penetration = new DimensionalNumber(penetrationValue).toString();
    const forms = profile.damage.damage_type_code === null ? null : DAMAGE_TYPE_FORMS[profile.damage.damage_type_code];

    return {
      itemRuleCode,
      itemName,
      itemHref: reference.href(itemRuleCode),
      profileType: profile.type,
      profileIndex,
      profileTypeLabel: WEAPON_PROFILE_LABELS[profile.type],
      distanceLabel: range === null ? String(minDistance) : `${minDistance}/${range}`,
      minDistance,
      reach,
      falloff,
      accuracyLabel: `${new DimensionalNumber(accuracy).toString()} точность`,
      accuracy,
      damageLabel: forms === null ? damage : `${damage} ${forms.genitive}`,
      penetrationLabel: `${penetration} пробития`,
      damageFormula: formulaLabel(profile.damage.formula, (code) => reference.ruleByCode(code)?.name ?? null, true),
      penetrationFormula: formulaLabel(profile.penetration, (code) => reference.ruleByCode(code)?.name ?? null, true),
      isResolved: true,
      damageTypeCode: profile.damage.damage_type_code,
      damage: damageValue,
      penetration: penetrationValue,
    };
  }
  private effectiveSpecOf(item: InventoryItem, reference: CharacterReferenceService): ItemSpec | null {
    if (item.ruleCode === null) return null;
    const spec = this.itemSpecOf(reference.ruleByCode(item.ruleCode));
    if (!spec) return null;
    const modifiers = (item.modifierRuleCodes ?? [])
      .map((id) => reference.ruleByCode(id))
      .filter((rule): rule is Rule => rule !== null);

    return this.itemModifiers.applyStack(spec, modifiers, []).spec;
  }

  private itemSpecOf(rule: Rule | null): ItemSpec | null {
    if (!rule) return null;

    return this.isItemSpec(rule.spec) ? rule.spec : null;
  }

  private characteristicSpecOf(rule: Rule | null): CharacteristicSpec | null {
    if (!rule) return null;

    return this.isCharacteristicSpec(rule.spec) ? rule.spec : null;
  }

  private abilitySpecOf(rule: Rule | null): AbilitySpec | null {
    if (rule?.type !== 'ability') return null;

    return rule.spec as AbilitySpec;
  }

  private isItemSpec(spec: RuleSpec | undefined): spec is ItemSpec {
    return spec !== undefined && 'category' in spec;
  }

  private isCharacteristicSpec(spec: RuleSpec | undefined): spec is CharacteristicSpec {
    return spec !== undefined && 'type' in spec && spec.type === 'characteristic';
  }
}
