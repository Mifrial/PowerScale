import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { CharacterEditorModel } from '@/modules/Roleplay/Character/Dto/Editor/CharacterEditorModel';
import type { CharacterSnapshot } from '@/modules/Roleplay/Character/Dto/Editor/CharacterSnapshot';
import type { EditorAbility } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbility';
import type { EditorAbilityGroup } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityGroup';
import type { EditorAbilityLevel } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityLevel';
import type {
  EditorAbilityParameter,
  EditorParameterStep,
} from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityParameter';
import type { EditorAbilityZone } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityZone';
import type { EditorBudget } from '@/modules/Roleplay/Character/Dto/Editor/EditorBudget';
import type { EditorBudgets } from '@/modules/Roleplay/Character/Dto/Editor/EditorBudgets';
import type { EditorCharacteristic } from '@/modules/Roleplay/Character/Dto/Editor/EditorCharacteristic';
import type { EditorRace } from '@/modules/Roleplay/Character/Dto/Editor/EditorRace';
import type { CharacteristicModifier } from '@/modules/Roleplay/Character/Dto/CharacteristicModifier';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { FormulaContext } from '@/modules/Roleplay/Character/Dto/FormulaContext';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { AbilityCost } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityCost';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement';
import type { AbilityParameter } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityParameter';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { RaceSpec } from '@/modules/Roleplay/Rule/Dto/Race/RaceSpec';
import type { CharacteristicSpec } from '@/modules/Roleplay/Rule/Dto/CharacteristicSpec';
import type { RaceAbilityRef } from '@/modules/Roleplay/Rule/Dto/Race/RaceAbilityRef';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { CharacterSenseValue } from '@/modules/Roleplay/Character/Dto/CharacterSenseValue';
import type { ResourceValue } from '@/modules/Roleplay/Character/Dto/ResourceValue';
import type { ResourceLimitBonus } from '@/modules/Roleplay/Character/Dto/ResourceLimitBonus';
import type { EditorPersonality } from '@/modules/Roleplay/Character/Dto/Editor/EditorPersonality';
import type { Age } from '@/modules/Roleplay/Rule/Dto/Age/Age';
import type { AgeSpec } from '@/modules/Roleplay/Rule/Dto/Age/AgeSpec';
import type { AgeRange } from '@/modules/Roleplay/Rule/Dto/Race/AgeRange';
import type { ResourceSpec } from '@/modules/Roleplay/Rule/Dto/ResourceSpec';
import type { SenseSpec } from '@/modules/Roleplay/Rule/Dto/SenseSpec';
import { RaceSpecService } from '@/modules/Roleplay/Rule/Service/Spec/RaceSpecService';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { formulaLabel } from '@/modules/Roleplay/Character/Utils/formulaLabel';
import { CharacteristicNumber } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';
import { CharacterReferenceService } from '@/modules/Roleplay/Character/Service/CharacterReferenceService';
import { FormulaEvaluationService } from '@/modules/Roleplay/Character/Service/FormulaEvaluationService';
import { RequirementEvaluator } from '@/modules/Roleplay/Character/Service/RequirementEvaluator';
import { derivedCharacteristicService } from '@/modules/Roleplay/Rule/Service/Instance/derivedCharacteristicService';
import type { ParsedDerivedFormula } from '@/modules/Roleplay/Rule/Dto/ParsedDerivedFormula';
import { mechanicEngine } from '@/modules/Roleplay/Rule/init';
import { PURCHASE_SURCHARGE_EVENT } from '@/modules/Roleplay/Rule/Service/Mechanic/Handlers/PurchaseSurchargeHandler';
import { racialInnateGearService } from '@/modules/Roleplay/Character/Service/Instance/racialInnateGearService';
import {
  ATTRACTIVENESS_MAX,
  ATTRACTIVENESS_MIN,
  ATTRACTIVENESS_STATE_CODE,
} from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';
import { itemModifierService } from '@/modules/Roleplay/Rule/Service/Instance/itemModifierService';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';
import { DOMAIN_REF_RULE_TYPES } from '@/modules/Roleplay/Rule/Constant/Ability/DOMAIN_REF_RULE_TYPES';
import { DOMAIN_STATIC_OPTIONS } from '@/modules/Roleplay/Rule/Constant/Ability/DOMAIN_STATIC_OPTIONS';
import { checkResolutionService } from '@/modules/Roleplay/Rule/Service/Instance/checkResolutionService';
import { weaponProficiencyService } from '@/modules/Roleplay/Character/Service/Instance/weaponProficiencyService';
import type { MechanicState } from '@/modules/Roleplay/Rule/Dto/MechanicState';
import type { CharacterMechanicContext } from '@/modules/Roleplay/Rule/Dto/CharacterMechanicContext';

/**
 * Расчётное ядро редактора персонажа: из выборов (CharacterBuild) и правил ревизии строит
 * view-model (характеристики, бюджеты, доступность способностей) и выводит CharacterVersion
 * для сохранения. Фронт — активные расчёты; бэк при сохранении только валидирует (ТР §7).
 */
export class CharacterEditorService {
  constructor(
    private readonly formula: FormulaEvaluationService = new FormulaEvaluationService(),
    private readonly raceSpecService = new RaceSpecService(),
  ) {}

  build(
    build: CharacterBuild,
    rules: Rule[],
    config: CharacterCreationConfig,
    keywords: Keyword[] = [],
    mechanics: Mechanic[] = [],
  ): CharacterEditorModel {
    const reference = new CharacterReferenceService(rules, build.spaceCode, build.rulesRevision);
    const race = this.buildRace(build, reference);
    const senses = this.buildSenses(build, reference);
    const characteristics = this.buildCharacteristics(build, reference, senses, keywords);
    const resources = this.buildResources(build, reference, characteristics, keywords);
    const budgets = this.buildBudgets(build, config, race, reference, keywords, mechanics);
    const abilities = this.buildAbilities(build, race, characteristics, resources, reference, keywords, rules);

    return {
      race,
      characteristics,
      senses,
      resources,
      abilities,
      groups: this.buildGroups(rules, abilities),
      budgets,
      personality: this.buildPersonality(build, reference, keywords),
    };
  }

  /** Выводит версию для сохранения из выборов: точки и характеристики считаются, остальное сохраняется. */
  toVersion(
    build: CharacterBuild,
    rules: Rule[],
    config: CharacterCreationConfig,
    keywords: Keyword[] = [],
    mechanics: Mechanic[] = [],
  ): CharacterVersion {
    const synced = racialInnateGearService.applyRacialInnateGear(build, rules);
    const reference = new CharacterReferenceService(rules, build.spaceCode, build.rulesRevision);
    const race = this.buildRace(synced, reference);
    const senses = this.buildSenses(synced, reference);
    const characteristics = this.buildCharacteristics(synced, reference, senses, keywords);
    const resources = this.buildResources(synced, reference, characteristics, keywords);
    const budgets = this.buildBudgets(synced, config, race, reference, keywords, mechanics);

    return {
      name: synced.name,
      shortDescription: synced.shortDescription,
      fullDescription: synced.fullDescription,
      spaceCode: synced.spaceCode,
      rulesRevision: synced.rulesRevision,
      raceRuleId: synced.raceRuleId,
      characteristics: characteristics.map((value) => ({
        ruleId: value.ruleId,
        base: value.base,
        modifiers: value.modifiers,
      })),
      resources,
      abilities: this.withAutomaticAbilities(synced.abilities, rules),
      points: {
        osSpent: budgets.os.spent,
        olSpent: budgets.ol.spent,
        olTotal: budgets.ol.total ?? synced.olTotal,
        orSpent: budgets.or.spent,
        orTotal: config.orTotal,
      },
      money: synced.money,
      ageYears: synced.ageYears,
      inventory: synced.inventory,
      states: this.buildDerivedStates(synced, reference, keywords),
      senses: senses.map((value) => ({
        ruleId: value.ruleId,
        value: value.value,
        modifiers: value.modifiers,
        status: value.status,
        radius: value.radius,
      })),
      budgets: { osTotal: config.osTotal, moneyBudget: config.moneyBudget },
    };
  }

  private withAutomaticAbilities(
    abilities: CharacterVersion['abilities'],
    rules: Rule[],
  ): CharacterVersion['abilities'] {
    const result = [...abilities];
    const existingRuleIds = new Set(result.map((ability) => ability.ruleId));

    for (const rule of rules) {
      if (rule.type !== 'ability' || existingRuleIds.has(rule.id)) continue;
      const spec = rule.spec as AbilitySpec | undefined;
      if (!spec || spec.type === 'group') continue;
      const isAutomatic = Object.values(spec.zones ?? {}).find((cost) => cost?.kind === 'automatic') !== undefined;
      if (!isAutomatic) continue;
      result.push({ ruleId: rule.id, level: 1 });
      existingRuleIds.add(rule.id);
    }

    return result;
  }

  private buildRace(build: CharacterBuild, reference: CharacterReferenceService): EditorRace {
    if (build.raceRuleId === null) return { ruleId: null, name: null, costOs: 0 };

    const rule = reference.ruleById(build.raceRuleId);
    const spec = rule?.type === 'race' ? (rule.spec as RaceSpec | undefined) : undefined;

    return { ruleId: rule?.id ?? null, name: rule?.name ?? null, costOs: spec?.cost_os ?? 0 };
  }

  private buildCharacteristics(
    build: CharacterBuild,
    reference: CharacterReferenceService,
    senses: CharacterSenseValue[] = [],
    keywords: Keyword[] = [],
  ): EditorCharacteristic[] {
    // Производные характеристики (type 'characteristic' с формулой): их базы — из формулы,
    // своего значения у производной нет (модификаторы к ней применяются к базам).
    const derivedFormulas = new Map<string, ParsedDerivedFormula>();
    for (const rule of reference.rules()) {
      if (rule.type !== 'characteristic') continue;
      const formula = (rule.spec as { formula?: string | null } | undefined)?.formula;
      if (!formula) continue;
      const parsed = derivedCharacteristicService.parseDerivedFormula(formula);
      if (parsed) derivedFormulas.set(rule.code, parsed);
    }

    // 1) Базы из расы (fixed — фикс.; purchased — минимум или закупленный уровень).
    const bases = new Map<string, DimensionalNumberValue>();
    const ruleIds = new Map<string, string>();
    this.applyRaceCharacteristics(build, reference, bases, ruleIds);

    // 2) Дары «characteristic» (дать характеристику) задают базу и переопределяют расовую
    //    fixed-базу той же характеристики (напр. «Врождённая Магия X» поверх «Магия 4↓» у Ахтара).
    this.forEachActiveGrant(build, reference, (grant) => {
      if (grant.type !== 'characteristic') return;
      ruleIds.set(grant.characteristic_code, this.ruleIdOfCode(reference, grant.characteristic_code));
      bases.set(grant.characteristic_code, grant.value);
    });

    // 2.1) Автоматические характеристики (спека automatic, «Автоматическое получение»): база по
    //      умолчанию 3 средних (или значение из спеки), если раса (fixed/купленная база) или дар
    //      не задали свою. Приоритет у расы/дара — инъекция только заполняет отсутствующие.
    for (const rule of reference.rules()) {
      if (rule.type !== 'characteristic') continue;
      const spec = rule.spec as { automatic?: boolean | { value: DimensionalNumberValue } } | undefined;
      const automatic = spec?.automatic;
      if (!automatic) continue;
      if (bases.has(rule.code)) continue;
      const autoValue = typeof automatic === 'object' ? automatic.value : { base: 3, size: 0 };
      bases.set(rule.code, autoValue);
      ruleIds.set(rule.code, rule.id);
    }

    // 3) Модификаторы «characteristic_modify»: агрегация по роли источника (макс+ и мин− в роли).
    //    Модификатор к производной применяется к её базам (attention+reaction / memory+reasoning).
    const deltas = new Map<string, { role: string | null; sourceRuleId: string | null; delta: number }[]>();
    // Условные модификаторы (scope): в значение не входят, показываются в попапе как «условно: …».
    const scoped = new Map<
      string,
      { role: string | null; sourceRuleId: string | null; delta: number; scope: string }[]
    >();
    this.forEachActiveGrant(build, reference, (grant) => {
      if (grant.type !== 'characteristic_modify') return;
      const delta = this.formula.evaluate(grant.amount, this.formulaContext(build, bases, reference, keywords));
      const source = grant.source_code === null ? null : reference.ruleByCode(grant.source_code);
      const entry = {
        role: source === null ? null : this.sourceRoleOf(source.type),
        sourceRuleId: source?.id ?? null,
        delta,
      };
      const targets = derivedFormulas.get(grant.characteristic_code)?.codes ?? [grant.characteristic_code];
      if (grant.check_codes?.length) {
        for (const target of targets) {
          const scopes = grant.check_codes
            .map((checkCode) => reference.ruleByCode(checkCode)?.name ?? checkCode)
            .join(', ');
          const list = scoped.get(target);
          const scopedEntry = { ...entry, scope: scopes };
          if (list) list.push(scopedEntry);
          else scoped.set(target, [scopedEntry]);
        }

        return;
      }
      for (const target of targets) {
        const list = deltas.get(target);
        if (list) list.push(entry);
        else deltas.set(target, [entry]);
      }
    });

    // 3.2) Дары способностей с вычисляемым уровнем (агрегаты «Развитие X» D108, производные D109
    //      «Навыки боя»): уровень вычисляется по взятым методам/опыту (см. derivedAbilityLevels),
    //      грант «+уровень к характеристике» применяется как у выбранной способности. Способности
    //      нет в build.abilities — обрабатываем правила напрямую.
    this.forEachAggregateGrant(build, reference, keywords, (grant) => {
      if (grant.type !== 'characteristic_modify') return;
      const delta = this.formula.evaluate(grant.amount, this.formulaContext(build, bases, reference, keywords));
      const source = grant.source_code === null ? null : reference.ruleByCode(grant.source_code);
      const entry = {
        role: source === null ? null : this.sourceRoleOf(source.type),
        sourceRuleId: source?.id ?? null,
        delta,
      };
      const targets = derivedFormulas.get(grant.characteristic_code)?.codes ?? [grant.characteristic_code];
      if (grant.check_codes?.length) {
        for (const target of targets) {
          const scopes = grant.check_codes
            .map((checkCode) => reference.ruleByCode(checkCode)?.name ?? checkCode)
            .join(', ');
          const list = scoped.get(target);
          const scopedEntry = { ...entry, scope: scopes };
          if (list) list.push(scopedEntry);
          else scoped.set(target, [scopedEntry]);
        }

        return;
      }
      for (const target of targets) {
        const list = deltas.get(target);
        if (list) list.push(entry);
        else deltas.set(target, [entry]);
      }
    });

    // 3.1) Эффекты возрастной ступени — real-модификаторы «от возраста», применяются live.
    //      Условные эффекты (scope) в значение не входят — копятся отдельно для попапа.
    const ageContext = this.ageContextOf(build, reference);
    if (ageContext) {
      for (const effect of ageContext.age.effects ?? []) {
        const targets = derivedFormulas.get(effect.characteristic_code)?.codes ?? [effect.characteristic_code];
        for (const target of targets) {
          if (effect.scope) {
            const list = scoped.get(target);
            if (list)
              list.push({
                role: 'от возраста',
                sourceRuleId: ageContext.ageRule.id,
                delta: effect.delta,
                scope: effect.scope,
              });
            else
              scoped.set(target, [
                { role: 'от возраста', sourceRuleId: ageContext.ageRule.id, delta: effect.delta, scope: effect.scope },
              ]);
          } else {
            const list = deltas.get(target);
            const entry = { role: 'от возраста', sourceRuleId: ageContext.ageRule.id, delta: effect.delta };
            if (list) list.push(entry);
            else deltas.set(target, [entry]);
          }
        }
      }
    }

    // 3.3) Экипированное снаряжение: штраф к Силе доспеха — обычный модификатор к Силе.
    for (const item of build.inventory) {
      const itemSpec = this.effectiveEquippedSpec(item, reference, keywords);
      const penalty = itemSpec?.armor?.strength_penalty;
      if (penalty === undefined || penalty === null || penalty === 0) continue;
      const entry = { role: 'от предмета', sourceRuleId: item.ruleId, delta: penalty };
      const strength = deltas.get('strength');
      if (strength) strength.push(entry);
      else deltas.set('strength', [entry]);
    }

    // 3.4) base_from: база характеристики = база другой + её модификаторы с указанными источниками.
    //      Пример — «Вес»: база = база Силы с врождёнными модификаторами (source 'innate').
    for (const rule of reference.rules()) {
      if (rule.type !== 'characteristic') continue;
      const spec = rule.spec as CharacteristicSpec | undefined;
      const baseFrom = spec?.base_from;
      if (!baseFrom) continue;
      const donorBase = bases.get(baseFrom.characteristic_code);
      if (!donorBase) continue;
      const sourceRuleIds = new Set(baseFrom.source_codes.map((code) => this.ruleIdOfCode(reference, code)));
      const sourceEntries = (deltas.get(baseFrom.characteristic_code) ?? []).filter(
        (entry) => entry.sourceRuleId !== null && sourceRuleIds.has(entry.sourceRuleId),
      );
      const delta = this.aggregateModifiers(baseFrom.characteristic_code, sourceEntries).reduce(
        (sum, modifier) => sum + modifier.delta,
        0,
      );
      bases.set(rule.code, CharacteristicNumber.from(donorBase).modifyWith(delta).value);
      ruleIds.set(rule.code, rule.id);
    }

    // 4) Чувства: наибольшее значение среди чувств применяется как модификатор к Внимательности.
    const bestSense = senses.reduce<CharacterSenseValue | null>(
      (best, sense) => (best === null || sense.value > best.value ? sense : best),
      null,
    );
    if (bestSense !== null && bestSense.value !== 0) {
      const attention = deltas.get('attention');
      const entry = { role: 'от чувства', sourceRuleId: bestSense.ruleId, delta: bestSense.value };
      if (attention) attention.push(entry);
      else deltas.set('attention', [entry]);
    }

    const result = [...bases.entries()].map(([code, base]) => {
      const modifiers = this.aggregateModifiers(code, deltas.get(code) ?? []);
      const scopedModifiers = (scoped.get(code) ?? []).map((entry) => ({
        sourceRuleId: entry.sourceRuleId,
        sourceLabel: null,
        delta: entry.delta,
        target: code,
        scope: entry.scope,
      }));
      const delta = modifiers.reduce((sum, modifier) => sum + modifier.delta, 0);

      return {
        ruleId: ruleIds.get(code) ?? code,
        code,
        name: reference.ruleByCode(code)?.name ?? code,
        base,
        delta,
        value: CharacteristicNumber.from(base).modifyWith(delta).value,
        modifiers: [...modifiers, ...scopedModifiers],
      };
    });

    // 4.1) Экипированное снаряжение: потолки характеристик (доспех max_agility → Ловкость,
    //      доспех/щит characteristic_limits → по кодам). Несколько ограничений — самый жёсткий.
    const finalValues = new Map(result.map((characteristic) => [characteristic.code, characteristic.value]));
    const equipmentContext = this.formulaContext(build, finalValues, reference, keywords);
    const limitResolveName = (code: string): string | null => reference.ruleByCode(code)?.name ?? null;
    const caps = new Map<string, { cap: DimensionalNumberValue; sourceRuleId: string; limitFormula: string | null }>();
    for (const item of build.inventory) {
      const itemSpec = this.effectiveEquippedSpec(item, reference, keywords);
      if (!itemSpec || item.ruleId === null) continue;

      const rawCaps: { characteristic_code: string; cap: DimensionalNumberValue; limitFormula: string | null }[] = [];
      if (itemSpec.armor?.max_agility != null) {
        rawCaps.push({ characteristic_code: 'dexterity', cap: itemSpec.armor.max_agility, limitFormula: null });
      }
      // Потолок, основанный на отсутствующей характеристике (у персонажа нет её базы), не применяется:
      // «reaction ≤ strength − 3» без Силы не должен ужимать Реакцию в отрицательные значения.
      for (const limit of itemSpec.armor?.characteristic_limits ?? []) {
        if (limit.limit.type === 'characteristic' && !finalValues.has(limit.limit.characteristic_code)) continue;
        rawCaps.push({
          characteristic_code: limit.characteristic_code,
          cap: this.formula.evaluateDimensional(limit.limit, equipmentContext),
          limitFormula: formulaLabel(limit.limit, limitResolveName),
        });
      }
      for (const limit of itemSpec.shield?.characteristic_limits ?? []) {
        if (limit.limit.type === 'characteristic' && !finalValues.has(limit.limit.characteristic_code)) continue;
        rawCaps.push({
          characteristic_code: limit.characteristic_code,
          cap: this.formula.evaluateDimensional(limit.limit, equipmentContext),
          limitFormula: formulaLabel(limit.limit, limitResolveName),
        });
      }
      for (const raw of rawCaps) {
        const existing = caps.get(raw.characteristic_code);
        if (!existing || new DimensionalNumber(raw.cap).compare(new DimensionalNumber(existing.cap)) < 0) {
          caps.set(raw.characteristic_code, {
            cap: raw.cap,
            sourceRuleId: item.ruleId,
            limitFormula: raw.limitFormula,
          });
        }
      }
    }
    for (const characteristic of result) {
      const capEntry = caps.get(characteristic.code);
      if (!capEntry) continue;
      if (new DimensionalNumber(characteristic.value).compare(new DimensionalNumber(capEntry.cap)) <= 0) continue;
      characteristic.value = capEntry.cap;
      characteristic.modifiers.push({
        sourceRuleId: capEntry.sourceRuleId,
        sourceLabel: null,
        delta: 0,
        target: characteristic.code,
        scope: null,
        limit: capEntry.cap,
        limitFormula: capEntry.limitFormula,
      });
    }

    // 5) Производные: значение = min/max финальных значений баз; своего значения/модификаторов нет.
    const values = new Map(result.map((characteristic) => [characteristic.code, characteristic.value]));
    for (const [code, parsed] of derivedFormulas) {
      const value = derivedCharacteristicService.evaluateDerivedValue(parsed, (baseCode) => values.get(baseCode));
      if (value === null) continue;
      result.push({
        ruleId: ruleIds.get(code) ?? this.ruleIdOfCode(reference, code),
        code,
        name: reference.ruleByCode(code)?.name ?? code,
        base: value,
        delta: 0,
        value,
        modifiers: [],
      });
    }

    return result;
  }

  /**
   * Чувства: правила type 'sense' со значением — модификатором к Внимательности. Значение чувства
   * агрегируется из активных грантов `sense_modify` по общему правилу источников (макс+ и мин− у одного
   * источника, разные источники суммируются). Чувство без грантов = 0 (нормальное).
   */
  private buildSenses(build: CharacterBuild, reference: CharacterReferenceService): CharacterSenseValue[] {
    const byCode = new Map<string, Rule>();
    for (const rule of reference.rules()) {
      if (rule.type === 'sense') byCode.set(rule.code, rule);
    }

    const deltas = new Map<string, { role: string | null; sourceRuleId: string | null; delta: number }[]>();
    this.forEachActiveGrant(build, reference, (grant) => {
      if (grant.type !== 'sense_modify') return;
      const delta = this.formula.evaluate(grant.amount, this.formulaContext(build, new Map(), reference));
      const source = grant.source_code === null ? null : reference.ruleByCode(grant.source_code);
      const entry = {
        role: source === null ? null : this.sourceRoleOf(source.type),
        sourceRuleId: source?.id ?? null,
        delta,
      };
      const list = deltas.get(grant.sense_code);
      if (list) list.push(entry);
      else deltas.set(grant.sense_code, [entry]);
    });

    return [...byCode.entries()].map(([code, rule]) => {
      const modifiers = this.aggregateModifiers(code, deltas.get(code) ?? []);
      const value = modifiers.reduce((sum, modifier) => sum + modifier.delta, 0);
      const spec = rule.spec as SenseSpec;

      return { ruleId: rule.id, value, modifiers, status: spec.status, radius: spec.radius };
    });
  }

  /**
   * Ресурсы персонажа. Авто-добавляемые (ResourceSpec.auto_add, сейчас — ОД): лимит = limit.base
   * + сумма adjustments (формулы по характеристикам) + дары resource_limit_change; каждый вклад —
   * бонус/штраф с источником (попап ресурса). current — из сохранённого build.resources
   * (фолбэк — полный лимит), кламп к [0, лимит]. Не-авто ресурсы строятся только из активных грантов.
   */
  private buildResources(
    build: CharacterBuild,
    reference: CharacterReferenceService,
    characteristics: EditorCharacteristic[],
    keywords: Keyword[],
  ): ResourceValue[] {
    const characteristicValues = new Map(characteristics.map((c) => [c.code, c.value]));
    const context = this.formulaContext(build, characteristicValues, reference, keywords);
    const storedByRuleId = new Map(build.resources.map((resource) => [resource.ruleId, resource]));

    const result: ResourceValue[] = [];
    const grantedResources = new Map<string, { base: DimensionalNumberValue; bonuses: ResourceLimitBonus[] }>();
    this.forEachActiveGrant(build, reference, (grant) => {
      if (grant.type === 'resource') {
        const resourceRule = reference.ruleByCode(grant.resource_code);
        if (!resourceRule || grantedResources.has(resourceRule.id)) return;
        const base = typeof grant.limit === 'number' ? { base: grant.limit, size: 0 } : grant.limit;
        grantedResources.set(resourceRule.id, { base, bonuses: [] });

        return;
      }

      if (grant.type !== 'resource_limit_change') return;
      const resourceRule = reference.ruleByCode(grant.resource_code);
      const granted = resourceRule ? grantedResources.get(resourceRule.id) : undefined;
      if (!granted) return;
      granted.bonuses.push({
        sourceRuleId: grant.source_code === null ? null : (reference.ruleByCode(grant.source_code)?.id ?? null),
        sourceLabel: null,
        delta: this.formula.evaluate(grant.amount, context),
      });
    });

    for (const rule of reference.rules()) {
      if (rule.type !== 'resource') continue;
      const spec = rule.spec as ResourceSpec | undefined;
      if (spec?.auto_add !== true || !spec.limit) continue;

      const stored = storedByRuleId.get(rule.id);
      const rawBase = spec.limit.base;
      const base: DimensionalNumberValue = typeof rawBase === 'number' ? { base: rawBase, size: 0 } : rawBase;
      const bonuses: ResourceLimitBonus[] = [];
      let delta = 0;

      for (const adjustment of spec.limit.adjustments) {
        const amount = this.formula.evaluate(adjustment.value, context);
        delta += amount;
        bonuses.push({
          sourceRuleId: this.ruleIdOfCode(reference, adjustment.source_code),
          sourceLabel: null,
          delta: amount,
        });
      }
      // Дары resource_limit_change (например «+2 ОД от Человек») — бонусы к лимиту.
      this.forEachActiveGrant(build, reference, (grant) => {
        if (grant.type !== 'resource_limit_change') return;
        const resourceRule = reference.ruleByCode(grant.resource_code);
        if (resourceRule?.id !== rule.id) return;
        const amount = this.formula.evaluate(grant.amount, context);
        const source = grant.source_code === null ? null : reference.ruleByCode(grant.source_code);
        delta += amount;
        bonuses.push({ sourceRuleId: source?.id ?? null, sourceLabel: null, delta: amount });
      });

      // Лимит = база + дельты (сдвиг базы без смены размера, D38); минимум 0 («не может действовать»).
      const limit = { base: Math.max(0, base.base + delta), size: base.size };
      result.push({
        ruleId: rule.id,
        current: this.clampCurrentToLimit(stored?.current ?? limit, limit),
        base,
        bonuses,
      });
    }

    // Неавтоматический ресурс существует только пока его даёт активный грант.
    for (const [ruleId, granted] of grantedResources) {
      if (result.some((entry) => entry.ruleId === ruleId)) continue;
      const stored = storedByRuleId.get(ruleId);
      const limitBase = Math.max(0, granted.base.base + granted.bonuses.reduce((sum, bonus) => sum + bonus.delta, 0));
      result.push({
        ruleId,
        current: this.clampCurrentToLimit(stored?.current ?? { base: limitBase, size: granted.base.size }, {
          base: limitBase,
          size: granted.base.size,
        }),
        base: granted.base,
        bonuses: granted.bonuses,
      });
    }

    return result;
  }

  /** current не выше лимита и не ниже 0 (лимит мог упасть после правки характеристик/состояний). */
  private clampCurrentToLimit(current: DimensionalNumberValue, limit: DimensionalNumberValue): DimensionalNumberValue {
    return { base: Math.max(0, Math.min(limit.base, current.base)), size: current.size };
  }

  private applyRaceCharacteristics(
    build: CharacterBuild,
    reference: CharacterReferenceService,
    bases: Map<string, DimensionalNumberValue>,
    ruleIds: Map<string, string>,
  ): void {
    if (build.raceRuleId === null) return;
    const rule = reference.ruleById(build.raceRuleId);
    const spec = rule?.type === 'race' ? (rule.spec as RaceSpec | undefined) : undefined;
    for (const characteristic of spec?.characteristics ?? []) {
      ruleIds.set(characteristic.characteristic_code, this.ruleIdOfCode(reference, characteristic.characteristic_code));
      if (characteristic.mode === 'fixed') {
        bases.set(characteristic.characteristic_code, characteristic.base);
        continue;
      }
      const purchase = build.characteristicPurchases.find(
        (p) => p.characteristicCode === characteristic.characteristic_code,
      );
      const level = characteristic.purchase?.find((l) => l.cost === (purchase?.cost ?? 0));
      bases.set(characteristic.characteristic_code, level?.value ?? characteristic.base);
    }
  }

  /** Фиксированные базы характеристик расы (mode 'fixed'): бесплатный уровень характеристики. */
  private raceFixedBases(
    build: CharacterBuild,
    reference: CharacterReferenceService,
  ): Map<string, DimensionalNumberValue> {
    const result = new Map<string, DimensionalNumberValue>();
    if (build.raceRuleId === null) return result;
    const rule = reference.ruleById(build.raceRuleId);
    const spec = rule?.type === 'race' ? (rule.spec as RaceSpec | undefined) : undefined;
    for (const characteristic of spec?.characteristics ?? []) {
      if (characteristic.mode === 'fixed') result.set(characteristic.characteristic_code, characteristic.base);
    }

    return result;
  }

  /** Код характеристики дара (грант characteristic_parameter); null — способность не дар характеристики. */
  private parameterGrantCode(spec: AbilitySpec): string | null {
    if (spec.type === 'group') return null;
    const grants = spec.grants?.flatMap((entry) => entry.grants ?? []) ?? [];
    const parameter = grants.find((grant) => grant.type === 'characteristic_parameter');

    return parameter?.type === 'characteristic_parameter' ? parameter.characteristic_code : null;
  }

  private buildBudgets(
    build: CharacterBuild,
    config: CharacterCreationConfig,
    race: EditorRace,
    reference: CharacterReferenceService,
    keywords: Keyword[] = [],
    mechanics: Mechanic[] = [],
  ): EditorBudgets {
    const spentByZone = new Map<string, number>();
    const rules = this.rulesOf(reference);
    const parameterAutoValues = this.racialAutomaticValues(build, reference, rules);
    const raceFixedBases = this.raceFixedBases(build, reference);
    // Уровни даров-навыков (D100): за подаренные уровни ОР не списываются, при апгрейде
    // дарованного навыка списывается только разница сверх подаренного уровня.
    const giftedLevels = this.giftedAbilityLevels(build, reference);
    for (const ability of build.abilities) {
      const rule = reference.ruleById(ability.ruleId);
      const spec = rule?.type === 'ability' ? (rule.spec as AbilitySpec | undefined) : undefined;
      if (!spec || !rule) continue;
      if (spec.type === 'group') continue;
      const zoneCode = ability.zone ?? this.purchasableZoneOf(spec);
      if (!zoneCode) continue;
      const cost = this.weaponFamilyCostOf(rule, ability, reference) ?? spec.zones[zoneCode];
      if (!cost) continue;
      const resolveParameter = (code: string) =>
        this.parameterCostValue(build, rule, spec, code, parameterAutoValues, raceFixedBases);
      const total = this.totalCostAtLevel(cost, ability.level, resolveParameter);
      const giftedLevel = ability.gifted ? 1 : spec.multiple === true ? 0 : (giftedLevels.get(rule.code) ?? 0);
      let paid = total;
      if (giftedLevel > 0) {
        paid = ability.level > giftedLevel ? total - this.totalCostAtLevel(cost, giftedLevel, resolveParameter) : 0;
      }
      const spent = (spentByZone.get(zoneCode) ?? 0) + paid;
      spentByZone.set(zoneCode, spent);
    }

    // Пассивные механики на пересборе шага «Основа»: события движка механик пишут
    // доплату и ui-аннотации (за общие черты) в аккумуляторы контекста шага.
    const mechanicState = this.mechanicStateOf(build, reference, keywords);
    const mechanicContext: CharacterMechanicContext = {
      ...mechanicState,
      osSurchargeTotal: 0,
      surchargeItems: [],
    };
    mechanicEngine.runEvent(
      PURCHASE_SURCHARGE_EVENT,
      mechanicContext,
      mechanicEngine.resolveActive(this.rulesOf(reference), mechanics),
    );
    const mechanicDelta = mechanicContext.osSurchargeTotal;
    const surchargeItems = mechanicContext.surchargeItems.map((item) => ({ ...item }));
    const osSurcharge = surchargeItems.length > 0 ? { total: mechanicDelta, items: surchargeItems } : undefined;

    const characteristicPurchases =
      race.ruleId !== null ? build.characteristicPurchases.reduce((sum, purchase) => sum + purchase.cost, 0) : 0;
    const effectiveMoney = this.effectiveMoneyBudget(config, this.activeMoneyGrants(build, reference));
    const moneySpent = effectiveMoney === null ? 0 : Math.max(0, effectiveMoney - build.money);
    const age = this.ageContextOf(build, reference)?.age ?? null;

    return {
      os: this.budget(
        config.osTotal,
        race.costOs + characteristicPurchases + (spentByZone.get('os') ?? 0) + mechanicDelta,
      ),
      ol: this.budget(age?.ol ?? null, spentByZone.get('ol') ?? 0),
      or: this.budget(config.orTotal, spentByZone.get('or') ?? 0),
      money: this.budget(effectiveMoney, moneySpent),
      osSurcharge,
    };
  }

  /** Активные дары «стартовый капитал» (деньги): из выбранных особенностей богатства. */
  private activeMoneyGrants(
    build: CharacterBuild,
    reference: CharacterReferenceService,
  ): Extract<Grant, { type: 'money' }>[] {
    const result: Extract<Grant, { type: 'money' }>[] = [];
    this.forEachActiveGrant(build, reference, (grant) => {
      if (grant.type === 'money') result.push(grant);
    });

    return result;
  }

  /**
   * Эффективный лимит денег игры: особенность богатства меняет стартовый капитал.
   * Положительные (max) — фикс. сумма или % от лимита (большее); Нищий (min) — меньшее.
   * При отсутствии лимита (moneyBudget = null) — только фикс. сумма.
   */
  private effectiveMoneyBudget(
    config: CharacterCreationConfig,
    grants: Extract<Grant, { type: 'money' }>[],
  ): number | null {
    const grant = grants[0];
    if (!grant) return config.moneyBudget;
    const limit = config.moneyBudget;
    if (limit === null) return grant.fixed;

    const byPercent = Math.round((grant.percent / 100) * limit);

    return grant.apply === 'max' ? Math.max(grant.fixed, byPercent) : Math.min(grant.fixed, byPercent);
  }

  /** Возрастной контекст: правило 'age' ревизии + ступень по годам персонажа и таблице лет расы. */
  private ageContextOf(
    build: CharacterBuild,
    reference: CharacterReferenceService,
  ): { ageRule: Rule; age: Age } | null {
    // ageYears допускает runtime-undefined (старый/битый черновик) — трактуем как «возраст не выбран».
    if (build.ageYears == null) return null;
    const ageRule = reference.rules().find((rule) => rule.type === 'age');
    if (!ageRule) return null;
    const spec = ageRule.spec as AgeSpec | undefined;
    if (spec?.type !== 'age') return null;

    const stepName = this.ageStepNameOf(build, reference);
    if (!stepName) return null;
    const age = spec.ages.find((entry) => entry.name === stepName);
    if (!age) return null;

    return { ageRule, age };
  }

  /** Имя ступени по годам: первый диапазон [ageStart, ageEnd); за диапазонами — «Старый». */
  private ageStepNameOf(build: CharacterBuild, reference: CharacterReferenceService): string | null {
    const years = build.ageYears;
    if (years == null) return null;
    const ranges = this.ageYearsOf(build, reference);
    if (!ranges) return null;

    const range = ranges.find((entry) => years >= entry.ageStart && years < entry.ageEnd);

    return range?.age ?? 'Старый';
  }

  /** Таблица лет «годы → ступень» вида/расы, поднимаясь по parent_race_code. */
  private ageYearsOf(build: CharacterBuild, reference: CharacterReferenceService): AgeRange[] | null {
    if (build.raceRuleId === null) return null;
    let current = reference.ruleById(build.raceRuleId);
    let guard = 0;
    while (current && guard++ < 20) {
      if (current.type === 'race' || current.type === 'species') {
        const spec = current.spec as { age_years?: AgeRange[] } | undefined;
        if (spec?.age_years?.length) return spec.age_years;
      }
      const parent = (current.spec as { parent_race_code?: string | null } | undefined)?.parent_race_code;
      if (!parent) break;
      current = reference.ruleByCode(parent) ?? null;
    }

    return null;
  }

  /** Сводка «Личности» для модели редактора: возраст, ОЛ/лимит, особенности богатства. */
  private buildPersonality(
    build: CharacterBuild,
    reference: CharacterReferenceService,
    keywords: Keyword[],
  ): EditorPersonality {
    const ageRule = reference.rules().find((rule) => rule.type === 'age');
    const ageContext = this.ageContextOf(build, reference);
    const wealthRuleIds = this.wealthRuleIds(reference, keywords);
    const ageScale = this.ageScaleOf(ageRule, build, reference);
    const hasAgeTable = (this.ageYearsOf(build, reference) ?? []).length > 0;

    return {
      hasAgeRule: ageRule !== undefined,
      ageYears: build.ageYears,
      ageName: ageContext?.age.name ?? null,
      ol: ageContext?.age.ol ?? null,
      featureLimit: ageContext?.age.featureLimit ?? null,
      wealthRuleIds: [...wealthRuleIds],
      ageScale,
      defaultAgeYears: this.defaultAgeYearsOf(ageScale, hasAgeTable),
    };
  }

  /**
   * Дефолт возраста: минимум средней ступени шкалы, отсортированной по началу диапазона.
   * Только когда у расы есть таблица лет (иначе шкала «вырожденная» и средняя ступень бессмысленна).
   */
  private defaultAgeYearsOf(
    ageScale: { name: string; min: number; max: number | null }[],
    hasAgeTable: boolean,
  ): number | null {
    if (!hasAgeTable || ageScale.length === 0) return null;
    const sorted = [...ageScale].sort((a, b) => a.min - b.min);
    const middle = sorted[Math.floor(sorted.length / 2)];

    return middle?.min ?? null;
  }

  /**
   * Шкала возраста в порядке ступеней правила: диапазон лет каждой ступени из таблицы вида.
   * Ступени без диапазона («Старый») — открытый диапазон от последнего максимума таблицы.
   */
  private ageScaleOf(
    ageRule: Rule | undefined,
    build: CharacterBuild,
    reference: CharacterReferenceService,
  ): { name: string; min: number; max: number | null }[] {
    if (!ageRule) return [];
    const spec = ageRule.spec as AgeSpec | undefined;
    if (spec?.type !== 'age') return [];

    const ranges = this.ageYearsOf(build, reference) ?? [];
    const rangeByAge = new Map(ranges.map((entry) => [entry.age, entry]));
    const lastMax = ranges.length ? ranges[ranges.length - 1].ageEnd : 0;

    return spec.ages.map((age) => {
      const range = rangeByAge.get(age.name);
      if (range) return { name: age.name, min: range.ageStart, max: range.ageEnd };

      return { name: age.name, min: lastMax, max: null };
    });
  }

  /** ruleId правил-способностей с признаком «Богатство» (особенности богатства). */
  private wealthRuleIds(reference: CharacterReferenceService, keywords: Keyword[]): Set<string> {
    const keywordCodes = new Map(keywords.map((keyword) => [keyword.id, keyword.code]));
    const result = new Set<string>();
    for (const rule of reference.rules()) {
      if (rule.type !== 'ability') continue;
      const isWealth = (rule.keywordIds ?? []).some((id) => keywordCodes.get(id) === 'wealth');
      if (isWealth) result.add(rule.id);
    }

    return result;
  }

  /** Правила ревизии из reference-сервиса (для диспетчера механик). */
  private rulesOf(reference: CharacterReferenceService): Rule[] {
    return reference.rules();
  }

  /** Read-only снимок для механик: уровни способностей, признаки, расовые способности. */
  private mechanicStateOf(
    build: CharacterBuild,
    reference: CharacterReferenceService,
    keywords: Keyword[],
  ): MechanicState {
    const abilityLevels = new Map<string, number>();
    const abilityKeywords = new Map<string, Set<string>>();
    const keywordCodes = new Map<number, string>(keywords.map((keyword) => [keyword.id, keyword.code]));

    for (const ability of build.abilities) {
      const rule = reference.ruleById(ability.ruleId);
      if (!rule) continue;
      // У множественного навыка несколько экземпляров (записей) — уровень для механик = max.
      const current = abilityLevels.get(rule.code) ?? 0;
      if (ability.level > current) abilityLevels.set(rule.code, ability.level);
      const set = new Set<string>();
      for (const keywordId of rule.keywordIds ?? []) {
        const code = keywordCodes.get(keywordId);
        if (code) set.add(code);
      }
      abilityKeywords.set(rule.code, set);
    }

    // Дары-навыки особенностей (D100) и производные уровни (D109) видят требования.
    for (const [code, level] of this.giftedAbilityLevels(build, reference)) {
      const current = abilityLevels.get(code) ?? 0;
      if (level > current) abilityLevels.set(code, level);
    }
    for (const [code, level] of this.derivedAbilityLevels(build, reference, keywords)) {
      if (level > 0) abilityLevels.set(code, level);
    }

    const racialAbilityCodes = this.racialAbilityCodes(build, reference, this.rulesOf(reference));

    return { abilityLevels, abilityKeywords, racialAbilityCodes };
  }

  private budget(total: number | null, spent: number): EditorBudget {
    return { total, spent, exceeded: total !== null && spent > total };
  }

  private buildAbilities(
    build: CharacterBuild,
    race: EditorRace,
    characteristics: EditorCharacteristic[],
    resources: ResourceValue[],
    reference: CharacterReferenceService,
    keywords: Keyword[],
    rules: Rule[],
  ): EditorAbility[] {
    const snapshot = this.buildSnapshot(build, race, characteristics, resources, reference, keywords);
    const evaluator = new RequirementEvaluator();
    const racialCodes = this.racialAbilityCodes(build, reference, rules);
    const racialAutomaticCodes = this.racialAutomaticCodes(build, reference, rules);
    const parameterCaps = this.racialParameterCaps(build, reference, rules);
    const parameterAutoValues = this.racialAutomaticValues(build, reference, rules);
    const raceFixedBases = this.raceFixedBases(build, reference);
    const keywordCodes = new Map(keywords.map((keyword) => [keyword.id, keyword.code]));
    const chosenParameters = this.chosenParametersByCode(build, reference);
    // Дары-навыки (D100) и производные уровни (D109) — одинаковы для всех способностей.
    const giftedLevels = this.giftedAbilityLevels(build, reference);
    const derivedLevels = this.derivedAbilityLevels(build, reference, keywords);

    const result: EditorAbility[] = [];
    for (const rule of rules) {
      if (rule.type !== 'ability') continue;
      const spec = rule.spec as AbilitySpec | undefined;
      if (!spec) continue;
      if (spec.type === 'group') continue;

      const zones: EditorAbilityZone[] = [];
      for (const [zoneCode, cost] of Object.entries(spec.zones ?? {})) {
        if (!cost) continue;
        zones.push({
          zoneCode,
          kind: cost.kind,
          maxLevel: this.maxLevelOf(cost),
          levelCosts: this.levelCosts(cost, (code) =>
            this.parameterCostValue(build, rule, spec, code, parameterAutoValues, raceFixedBases),
          ),
        });
      }
      const maxLevel = Math.max(1, ...zones.map((zone) => zone.maxLevel));
      // Экземпляры множественного навыка (записи одного ruleId; у не-multiple — один-единственный).
      const instances = build.abilities.filter((ability) => ability.ruleId === rule.id);
      const chosen = instances[0];
      const instanceLevel = instances.reduce((max, instance) => (instance.level > max ? instance.level : max), 0);
      const multiple = spec.multiple === true;
      // Бесплатна по зоне (automatic) или дана расой автоматически (automatic: true).
      const automatic = zones.some((zone) => zone.kind === 'automatic') || racialAutomaticCodes.has(rule.code);
      const racial = racialCodes.has(rule.code);
      // Видна в каталоге этапа: общая черта (common), предоставленная расой, черта характеристик
      // (свой блок «Характеристики» — EditorInnateMatrix) или уже взятая; иначе скрыта.
      const hasCommon = (rule.keywordIds ?? []).some((id) => keywordCodes.get(id) === 'common');
      const characteristic = (rule.keywordIds ?? []).some((id) => keywordCodes.get(id) === 'characteristic');
      const visible = hasCommon || racial || characteristic || instances.length > 0;
      // Получена даром-навыком особенности (D100): уровень из гранта, снять нельзя.
      const giftedLevel = giftedLevels.get(rule.code) ?? 0;
      const gifted = giftedLevel > 0;
      // Производный уровень (D109) и агрегат «Развитие X» (D108): не покупаются,
      // уровень вычисляется (напр. «Ближний бой», «Развитие восприятия»).
      const derivedLevel = spec.derived_level || spec.aggregate ? (derivedLevels.get(rule.code) ?? 0) : 0;
      // Улучшение (parent_ability_code): требование родителя синтезируется автоматически
      // (взять улучшение можно только если родительская способность взята) и объединяется
      // с прописанными в карточке требованиями уровня.
      const parentRequirement: Requirement | null = spec.parent_ability_code
        ? { type: 'has_ability', ability_code: spec.parent_ability_code, min_level: 1 }
        : null;

      const levels: EditorAbilityLevel[] = [];
      // Взятие уровня N подразумевает владение уровнями 1..N, поэтому требования
      // накапливаются: для уровня N проверяются требования всех уровней до N включительно.
      // У множественных навыков has_ability-требования домен-скоупированы (см. instances ниже).
      const accumulatedRequirements: Requirement[] = [];
      for (let level = 1; level <= maxLevel; level++) {
        const requirements = spec.requirements?.find((entry) => entry.level === level)?.requirements ?? [];
        accumulatedRequirements.push(...requirements);
        const all = parentRequirement ? [parentRequirement, ...accumulatedRequirements] : accumulatedRequirements;
        const hasRequirements = all.length > 0;
        levels.push({
          level,
          met: !hasRequirements || evaluator.evaluateAll(all, snapshot),
          reason: hasRequirements ? evaluator.firstFailure(all, snapshot) : null,
        });
      }

      // Пер-экземплярные требования множественного навыка: has_ability проверяется по экземплярам
      // с тем же доменом («Письменность того же языка», требование родителя-улучшения — тоже).
      const instanceLevelsOf = (domain: string): EditorAbilityLevel[] => {
        const result: EditorAbilityLevel[] = [];
        const accumulated: Requirement[] = [];
        for (let level = 1; level <= maxLevel; level++) {
          const requirements = spec.requirements?.find((entry) => entry.level === level)?.requirements ?? [];
          accumulated.push(...requirements);
          const all = parentRequirement ? [parentRequirement, ...accumulated] : accumulated;
          const hasRequirements = all.length > 0;
          result.push({
            level,
            met: !hasRequirements || evaluator.evaluateAll(all, snapshot, domain),
            reason: hasRequirements ? evaluator.firstFailure(all, snapshot, domain) : null,
          });
        }

        return result;
      };

      result.push({
        ruleId: rule.id,
        code: rule.code,
        name: rule.name,
        type: spec.type,
        description: rule.description,
        processSteps: spec.type === 'process' ? spec.process.steps : [],
        keywordIds: rule.keywordIds ?? [],
        zones,
        // Уровень: у множественного навыка — max по экземплярам; иначе выбранный,
        // либо от дара-навыка (D100), либо производный (D109/D108).
        level: multiple
          ? Math.max(instanceLevel, giftedLevel, derivedLevel)
          : (chosen?.level ?? (giftedLevel || derivedLevel)),
        levels,
        automatic,
        gifted,
        giftedLevel,
        // Производный уровень/агрегат: вычисляется автоматически, вручную не выбирается.
        derived: spec.derived_level != null || spec.aggregate != null,
        racial,
        visible,
        characteristic,
        characteristicCode: this.characteristicCodeOf(spec),
        groupCode: (spec as { group_code?: string | null }).group_code ?? null,
        parentCode: spec.parent_ability_code ?? null,
        multiple,
        domainRef: spec.domain_ref ?? null,
        instances: multiple
          ? instances.map((instance) => ({
              domain: instance.domain ?? '',
              domainCode: instance.domainCode ?? null,
              level: instance.level,
              levels: instanceLevelsOf(instance.domain ?? ''),
            }))
          : [],
        // Домен одиночной способности (domain_ref без multiple): из единственной записи.
        domain: !multiple && spec.domain_ref ? (chosen?.domain ?? null) : null,
        domainCode: !multiple && spec.domain_ref ? (chosen?.domainCode ?? null) : null,
        domainOptions: this.instanceDomainOptions(rules, build, spec),
        parameters: this.editorParameters(
          rule,
          spec,
          chosen,
          automatic,
          parameterCaps,
          parameterAutoValues,
          chosenParameters,
          raceFixedBases,
        ),
      });
    }

    return result;
  }

  /** Параметры «X» покупки способности (для параметрических зон): диапазон с учётом потолка расы. */
  private editorParameters(
    rule: Rule,
    spec: AbilitySpec,
    chosen: CharacterAbility | undefined,
    automatic: boolean,
    caps: Map<string, Record<string, DimensionalNumberValue>>,
    autoValues: Map<string, Record<string, DimensionalNumberValue>>,
    chosenParameters: Map<string, Record<string, number>> = new Map(),
    raceFixedBases: Map<string, DimensionalNumberValue> = new Map(),
  ): EditorAbilityParameter[] {
    if (spec.type === 'group') return [];
    const zoneCode = this.purchasableZoneOf(spec);
    const purchaseCost = zoneCode ? spec.zones[zoneCode] : undefined;
    if (
      !purchaseCost ||
      (purchaseCost.kind !== 'parameter' &&
        purchaseCost.kind !== 'parameter_table' &&
        purchaseCost.kind !== 'parameter_sum_tables')
    ) {
      return [];
    }

    const codes =
      purchaseCost.kind === 'parameter_sum_tables'
        ? (spec.parameters ?? [])
            .filter((parameter) => parameter.resolution === 'purchase')
            .map((parameter) => parameter.code)
        : [purchaseCost.parameter_code];

    const currentByCode = new Map<string, number>();
    for (const code of codes) {
      const raw = chosen?.parameters?.[code];
      currentByCode.set(code, raw === undefined ? 0 : this.toNumber(raw));
    }
    const poolUsed = [...currentByCode.values()].reduce((sum, value) => sum + value, 0);

    const result: EditorAbilityParameter[] = [];
    for (const code of codes) {
      const parameter = spec.parameters?.find((entry) => entry.code === code);
      if (!parameter) continue;

      const specMin = this.dimOf(parameter, 'min', 1);
      const specMax = this.dimOf(parameter, 'max', parameter.default);
      const cap = caps.get(rule.code)?.[parameter.code];
      const autoValue = autoValues.get(rule.code)?.[parameter.code];
      let min = specMin;
      let max = cap !== undefined && this.dimLessThan(cap, specMax) ? cap : specMax;

      const grantCode = this.parameterGrantCode(spec);
      const raceFixed = grantCode === null ? null : (raceFixedBases.get(grantCode) ?? null);
      let freeStepCost = 0;
      const tableCosts =
        purchaseCost.kind === 'parameter_table'
          ? purchaseCost.costs
          : purchaseCost.kind === 'parameter_sum_tables'
            ? purchaseCost.tables[code]
            : undefined;
      if (raceFixed !== null) {
        if (this.dimLessThan(min, raceFixed)) min = raceFixed;
        if (tableCosts) freeStepCost = tableCosts[new DimensionalNumber(raceFixed).toString()] ?? 0;
      }

      if (parameter.linked) {
        const other = chosenParameters.get(parameter.linked.ability_code)?.[parameter.linked.parameter_code] ?? 0;
        max = this.dimMax(parameter, max, other + parameter.linked.max_delta);
        min = this.dimMin(parameter, min, other - parameter.linked.max_delta);
      }

      if (purchaseCost.kind === 'parameter_sum_tables') {
        const current = currentByCode.get(code) ?? 0;
        const remaining = purchaseCost.max_level - (poolUsed - current);
        max = this.dimMax(parameter, max, remaining);
      }

      let value: DimensionalNumberValue;
      const chosenValue = chosen?.parameters?.[parameter.code];
      if (chosenValue !== undefined) {
        value = typeof chosenValue === 'number' ? { base: chosenValue, size: 0 } : chosenValue;
      } else if (automatic) {
        value = autoValue ?? cap ?? this.dimOf(parameter, 'default', 1);
      } else if (raceFixed !== null) {
        value = raceFixed;
      } else {
        value = { base: 0, size: 0 };
      }

      const steps = tableCosts ? this.parameterSteps(tableCosts, min, max) : [];

      result.push({
        code: parameter.code,
        label: parameter.label,
        description: parameter.description,
        value,
        min,
        max,
        perUnit: purchaseCost.kind === 'parameter' ? purchaseCost.per_unit : 0,
        costs: tableCosts,
        steps,
        cappedByRace: cap !== undefined,
        freeValue:
          raceFixed !== null
            ? this.toNumber(raceFixed)
            : automatic
              ? this.toNumber(autoValue ?? { base: 0, size: 0 })
              : 0,
        freeStepCost,
      });
    }

    return result;
  }

  /** Ступени табличной цены: значения (размерные) из ключей costs в возрастающем порядке, в пределах [min, max]. */
  private parameterSteps(
    costs: Record<string, number>,
    min: DimensionalNumberValue,
    max: DimensionalNumberValue,
  ): EditorParameterStep[] {
    return Object.entries(costs)
      .map(([key, cost]) => ({ value: DimensionalNumber.parse(key), cost }))
      .filter((step) => !this.dimLessThan(step.value, min) && !this.dimLessThan(max, step.value))
      .sort((a, b) => this.dimRank(a.value) - this.dimRank(b.value));
  }

  /** Числовой ранг размерного значения для сравнения/сортировки (base × 2^size, без округления). */
  private dimRank(value: DimensionalNumberValue): number {
    return value.base * Math.pow(2, value.size);
  }

  private dimLessThan(a: DimensionalNumberValue, b: DimensionalNumberValue): boolean {
    return this.dimRank(a) < this.dimRank(b);
  }

  /** Сжатие максимума параметра: не выше связанного + delta (по базе/рангу, спека задаёт размерность). */
  private dimMax(
    parameter: AbilityParameter,
    current: DimensionalNumberValue,
    capByDelta: number,
  ): DimensionalNumberValue {
    const value = this.toNumber(current);
    const bound = { base: Math.min(value, capByDelta), size: 0 };

    return this.dimLessThan(bound, current) ? bound : current;
  }

  private dimMin(
    parameter: AbilityParameter,
    current: DimensionalNumberValue,
    capByDelta: number,
  ): DimensionalNumberValue {
    const value = this.toNumber(current);
    const bound = { base: Math.max(value, capByDelta), size: 0 };

    return this.dimLessThan(current, bound) ? bound : current;
  }

  /** Код характеристики, на которую влияет черта (грант characteristic_modify/characteristic_parameter); null — нет. */
  private characteristicCodeOf(spec: AbilitySpec): string | null {
    if (spec.type === 'group') return null;
    const grants = spec.grants?.flatMap((entry) => entry.grants ?? []) ?? [];
    const modify = grants.find(
      (grant) => grant.type === 'characteristic_modify' || grant.type === 'characteristic_parameter',
    );
    if (modify?.type === 'characteristic_modify') return modify.characteristic_code;
    if (modify?.type === 'characteristic_parameter') return modify.characteristic_code;

    return null;
  }

  private dimOf(
    parameter: AbilityParameter,
    key: 'min' | 'max' | 'default',
    fallback: DimensionalNumberValue | number,
  ): DimensionalNumberValue {
    const raw = parameter[key];
    if (raw === undefined) return typeof fallback === 'number' ? { base: fallback, size: 0 } : fallback;

    return typeof raw === 'number' ? { base: raw, size: 0 } : raw;
  }

  /** Группирующие правила (type 'group') с их участниками из плоского списка способностей. */
  private buildGroups(rules: Rule[], abilities: EditorAbility[]): EditorAbilityGroup[] {
    const byGroupCode = new Map<string, EditorAbility[]>();
    for (const ability of abilities) {
      if (!ability.groupCode) continue;
      const list = byGroupCode.get(ability.groupCode);
      if (list) list.push(ability);
      else byGroupCode.set(ability.groupCode, [ability]);
    }

    const result: EditorAbilityGroup[] = [];
    for (const rule of rules) {
      if (rule.type !== 'ability') continue;
      const spec = rule.spec as { type?: string; selectLimit?: number } | undefined;
      if (spec?.type !== 'group') continue;
      result.push({
        ruleId: rule.id,
        code: rule.code,
        name: rule.name,
        description: rule.description,
        selectLimit: spec.selectLimit ?? -1,
        members: byGroupCode.get(rule.code) ?? [],
      });
    }

    return result;
  }

  private buildSnapshot(
    build: CharacterBuild,
    race: EditorRace,
    characteristics: EditorCharacteristic[],
    resources: ResourceValue[],
    reference: CharacterReferenceService,
    keywords: Keyword[],
  ): CharacterSnapshot {
    const abilityLevels = new Map<string, number>();
    const abilityKeywords = new Map<string, Set<string>>();
    const abilityInstances = new Map<string, { domain: string; domainCode: string | null; level: number }[]>();
    const keywordCodes = new Set<string>();

    const addKeywords = (rule: Rule | null | undefined): void => {
      if (!rule) return;
      for (const keywordId of rule.keywordIds ?? []) {
        const keyword = keywords.find((entry) => entry.id === keywordId);
        if (keyword) keywordCodes.add(keyword.code);
      }
    };

    if (race.ruleId !== null) addKeywords(reference.ruleById(race.ruleId));

    for (const ability of build.abilities) {
      const rule = reference.ruleById(ability.ruleId);
      if (!rule) continue;
      // У множественного навыка несколько экземпляров (записей) — уровень для требований = max.
      const current = abilityLevels.get(rule.code) ?? 0;
      if (ability.level > current) abilityLevels.set(rule.code, ability.level);
      const set = new Set<string>();
      for (const keywordId of rule.keywordIds ?? []) {
        const keyword = keywords.find((entry) => entry.id === keywordId);
        if (keyword) set.add(keyword.code);
      }
      abilityKeywords.set(rule.code, set);
      addKeywords(rule);
      // Экземпляры множественных навыков (для домен-скоупированных требований «того же языка»).
      if (ability.domain != null) {
        const list = abilityInstances.get(rule.code) ?? [];
        list.push({ domain: ability.domain, domainCode: ability.domainCode ?? null, level: ability.level });
        abilityInstances.set(rule.code, list);
      }
    }

    // Дары-навыки особенностей (D100) и производные уровни (D109): требования и механики видят их.
    for (const [code, level] of this.giftedAbilityLevels(build, reference)) {
      const current = abilityLevels.get(code) ?? 0;
      if (level > current) abilityLevels.set(code, level);
      const giftedRule = reference.ruleByCode(code);
      if (giftedRule) addKeywords(giftedRule);
    }
    for (const [code, level] of this.derivedAbilityLevels(build, reference, keywords)) {
      if (level > 0) abilityLevels.set(code, level);
    }

    // Уровни владения оружием по семьям: из «Владение оружием» (domain_ref 'weapon-family').
    const proficiencyLevels = weaponProficiencyService.weaponProficiencyLevels(build.abilities, reference.rules());
    // Тэги → семейства оружия с этим тэгом: вычисляются из предметов (proficiency_family_code + keywordIds).
    const familyTags = this.weaponFamilyTagsOf(reference, keywords);

    const characteristicValues = new Map<string, DimensionalNumberValue>();
    for (const characteristic of characteristics) {
      characteristicValues.set(characteristic.code, characteristic.value);
    }

    // Лимиты ресурсов из вычисленных ресурсов (в т.ч. авто-Од): лимит = база + сумма бонусов (мин 0).
    // Код ресурса — из правила; неизвестный код остаётся без лимита (требование «есть ресурс» не пройдёт).
    const resourceCodeByRuleId = new Map<string, string>();
    for (const rule of reference.rules()) {
      if (rule.type === 'resource') resourceCodeByRuleId.set(rule.id, rule.code);
    }
    const resourceLimits = new Map<string, number | DimensionalNumberValue>();
    for (const resource of resources) {
      const code = resourceCodeByRuleId.get(resource.ruleId);
      if (!code) continue;
      const delta = resource.bonuses.reduce((sum, bonus) => sum + bonus.delta, 0);
      resourceLimits.set(code, { base: Math.max(0, resource.base.base + delta), size: resource.base.size });
    }
    // Дары ресурсов, которых в ревизии нет (или не-авто): лимит из грантов (как раньше).
    this.forEachActiveGrant(build, reference, (grant) => {
      if (grant.type === 'resource' || grant.type === 'resource_limit_change') {
        if (resourceLimits.has(grant.resource_code)) return;
      }
      if (grant.type === 'resource') {
        resourceLimits.set(grant.resource_code, grant.limit);
      } else if (grant.type === 'resource_limit_change') {
        const delta = this.formula.evaluate(
          grant.amount,
          this.formulaContext(build, characteristicValues, reference, keywords),
        );
        const existing = resourceLimits.get(grant.resource_code);
        resourceLimits.set(grant.resource_code, (existing === undefined ? 0 : this.toNumber(existing)) + delta);
      }
    });

    const abilityNames = new Map<string, string>();
    const characteristicNames = new Map<string, string>();
    const resourceNames = new Map<string, string>();
    for (const rule of reference.rules()) {
      if (rule.type === 'ability') abilityNames.set(rule.code, rule.name);
      else if (rule.type === 'characteristic') characteristicNames.set(rule.code, rule.name);
      else if (rule.type === 'resource' || rule.type === 'points') resourceNames.set(rule.code, rule.name);
    }
    const keywordNames = new Map(keywords.map((keyword) => [keyword.code, keyword.name]));

    return {
      abilityLevels,
      abilityKeywords,
      abilityInstances,
      weaponProficiencyLevels: proficiencyLevels,
      weaponFamilyTags: familyTags,
      characteristicValues,
      resourceLimits,
      keywordCodes,
      abilityNames,
      keywordNames,
      characteristicNames,
      resourceNames,
    };
  }

  /** Референсы способностей, доступных расе (свои + наследуемые от видов). */
  private racialAbilityRefs(
    build: CharacterBuild,
    reference: CharacterReferenceService,
    rules: Rule[],
  ): RaceAbilityRef[] {
    if (build.raceRuleId === null) return [];

    const raceRule = reference.ruleById(build.raceRuleId);
    const spec = raceRule?.type === 'race' ? (raceRule.spec as RaceSpec | undefined) : undefined;
    if (!spec) return [];

    const rulesByCode = new Map(rules.map((rule) => [rule.code, rule]));

    return [
      ...(spec.abilities ?? []),
      ...this.raceSpecService.collectInheritedAbilities(spec.parent_race_code, rulesByCode),
    ];
  }

  /** Коды способностей, доступных расе (свои + наследуемые от видов). */
  private racialAbilityCodes(build: CharacterBuild, reference: CharacterReferenceService, rules: Rule[]): Set<string> {
    return new Set(this.racialAbilityRefs(build, reference, rules).map((ref) => ref.ability_code));
  }

  /** Коды способностей, которые раса даёт бесплатно (automatic: true, свои + наследуемые). */
  private racialAutomaticCodes(
    build: CharacterBuild,
    reference: CharacterReferenceService,
    rules: Rule[],
  ): Set<string> {
    return new Set(
      this.racialAbilityRefs(build, reference, rules)
        .filter((ref) => ref.automatic)
        .map((ref) => ref.ability_code),
    );
  }

  /** Потолки параметров «X» способностей у расы (код способности → код параметра → значение). */
  private racialParameterCaps(
    build: CharacterBuild,
    reference: CharacterReferenceService,
    rules: Rule[],
  ): Map<string, Record<string, DimensionalNumberValue>> {
    const caps = new Map<string, Record<string, DimensionalNumberValue>>();
    for (const ref of this.racialAbilityRefs(build, reference, rules)) {
      if (!ref.parameters) continue;
      for (const [code, value] of Object.entries(ref.parameters)) {
        const existing = caps.get(ref.ability_code);
        if (existing && existing[code]) {
          const current = this.toNumber(existing[code]);
          const next = this.toNumber(value);
          if (next > current) existing[code] = value;
        } else if (existing) {
          existing[code] = value;
        } else {
          caps.set(ref.ability_code, { [code]: value });
        }
      }
    }

    return caps;
  }

  /**
   * Автоматические значения параметров «X» у расы (automatic: true): код способности → параметр → значение.
   * Служат бесплатной базой: докупка сверх этого значения оплачивается (Аэрон: «Сопротивление магии 2» + опции 3–5).
   */
  private racialAutomaticValues(
    build: CharacterBuild,
    reference: CharacterReferenceService,
    rules: Rule[],
  ): Map<string, Record<string, DimensionalNumberValue>> {
    const values = new Map<string, Record<string, DimensionalNumberValue>>();
    for (const ref of this.racialAbilityRefs(build, reference, rules)) {
      if (!ref.automatic || !ref.parameters) continue;
      for (const [code, value] of Object.entries(ref.parameters)) {
        const existing = values.get(ref.ability_code);
        if (existing && existing[code]) {
          const current = this.toNumber(existing[code]);
          const next = this.toNumber(value);
          if (next > current) existing[code] = value;
        } else if (existing) {
          existing[code] = value;
        } else {
          values.set(ref.ability_code, { [code]: value });
        }
      }
    }

    return values;
  }

  /**
   * Выбранные значения параметров способностей персонажа по коду правила: для связей параметров
   * («Врождённая Стойкость X» зависит от «Врождённой Силы X»).
   */
  private chosenParametersByCode(
    build: CharacterBuild,
    reference: CharacterReferenceService,
  ): Map<string, Record<string, number>> {
    const result = new Map<string, Record<string, number>>();
    for (const ability of build.abilities) {
      const rule = reference.ruleById(ability.ruleId);
      if (!rule) continue;
      if (!ability.parameters) continue;
      const entries: Record<string, number> = {};
      for (const [code, value] of Object.entries(ability.parameters)) {
        entries[code] = this.toNumber(value);
      }
      result.set(rule.code, entries);
    }

    return result;
  }

  private buildDerivedStates(
    build: CharacterBuild,
    reference: CharacterReferenceService,
    keywords: Keyword[],
  ): CharacterStateValue[] {
    const rule = reference.ruleByCode(ATTRACTIVENESS_STATE_CODE);
    if (!rule || rule.type !== 'state') return build.states;
    let total = 0;
    this.forEachActiveGrant(build, reference, (grant) => {
      if (grant.type !== 'state_modify' || grant.state_code !== ATTRACTIVENESS_STATE_CODE) return;
      total += this.formula.evaluate(grant.amount, this.formulaContext(build, new Map(), reference, keywords));
    });
    const value = Math.min(ATTRACTIVENESS_MAX, Math.max(ATTRACTIVENESS_MIN, total));

    return [...build.states.filter((state) => state.stateRuleId !== rule.id), { stateRuleId: rule.id, value }];
  }

  /**
   * Обходит активные дары выбранных и автоматических расовых способностей. Дар уровня L:
   * permanent (по умолчанию) действует на всех уровнях >= L, non-permanent — строго на уровне L.
   */
  private forEachActiveGrant(
    build: CharacterBuild,
    reference: CharacterReferenceService,
    callback: (grant: Grant, rule: Rule) => void,
  ): void {
    for (const ability of build.abilities) {
      if (ability.level < 1) continue;
      const rule = reference.ruleById(ability.ruleId);
      if (!rule) continue;
      const spec = rule.type === 'ability' ? (rule.spec as AbilitySpec | undefined) : undefined;
      if (!spec || spec.type === 'group') continue;

      for (const entry of spec.grants ?? []) {
        if (entry.level > ability.level) continue;
        for (const grant of entry.grants) {
          const permanent = grant.permanent !== false;
          if (!permanent && entry.level !== ability.level) continue;
          callback(this.resolveGrant(grant, ability, spec), rule);
        }
      }
    }

    // Автоматические расовые способности: дары применяются как на уровне 1.
    // Если способность уже выбрана персонажем (докупка сверх авто-значения) — грант уже применён выше,
    // иначе автоматический грант задвоится.
    const rules = this.rulesOf(reference);
    const chosenRuleIds = new Set(build.abilities.map((ability) => ability.ruleId));
    for (const ref of this.racialAbilityRefs(build, reference, rules)) {
      if (!ref.automatic) continue;
      const rule = reference.ruleByCode(ref.ability_code);
      if (!rule) continue;
      if (chosenRuleIds.has(rule.id)) continue;
      const spec = rule.type === 'ability' ? (rule.spec as AbilitySpec | undefined) : undefined;
      if (!spec || spec.type === 'group') continue;

      for (const entry of spec.grants ?? []) {
        for (const grant of entry.grants) {
          const permanent = grant.permanent !== false;
          if (!permanent && entry.level !== 1) continue;
          callback(this.resolveGrant(grant, { parameters: ref.parameters }, spec), rule);
        }
      }
    }
  }

  /**
   * Обходит дары способностей с вычисляемым уровнем, которых нет в build.abilities:
   * агрегаты «Развитие X» (D108) и производные навыки (D109, напр. «Навыки боя»). Уровень
   * вычисляется по взятым методам/опыту (derivedAbilityLevels), а гранты уровня ≤ level
   * применяются как у выбранной способности.
   */
  private forEachAggregateGrant(
    build: CharacterBuild,
    reference: CharacterReferenceService,
    keywords: Keyword[],
    callback: (grant: Grant, rule: Rule) => void,
  ): void {
    const derivedLevels = this.derivedAbilityLevels(build, reference, keywords);
    for (const rule of reference.rules()) {
      if (rule.type !== 'ability') continue;
      const spec = rule.spec as AbilitySpec | undefined;
      if (!spec || spec.type === 'group') continue;
      const computed = spec.aggregate || spec.derived_level;
      if (!computed) continue;
      const level = derivedLevels.get(rule.code) ?? 0;
      if (level < 1) continue;

      for (const entry of spec.grants ?? []) {
        if (entry.level > level) continue;
        for (const grant of entry.grants) {
          const permanent = grant.permanent !== false;
          if (!permanent && entry.level !== level) continue;
          callback(this.resolveGrant(grant, { parameters: undefined }, spec), rule);
        }
      }
    }
  }

  /** Резолвит параметрические гранты: 'resistance', 'characteristic_modify', 'characteristic_parameter'. */
  private resolveGrant(grant: Grant, ability: Pick<CharacterAbility, 'parameters'>, spec: AbilitySpec): Grant {
    if (grant.type === 'resistance') {
      const value = grant.value;
      if (typeof value !== 'object' || !('type' in value) || value.type !== 'parameter') return grant;
      const x = this.parameterValueOf(spec, ability.parameters, value.parameter_code);

      return { ...grant, value: { base: x * value.per_unit, size: 0 } };
    }

    if (grant.type === 'characteristic_modify') {
      const amount = grant.amount;
      if (typeof amount !== 'object' || !('type' in amount)) return grant;
      if (amount.type === 'parameter') {
        const x = this.parameterValueOf(spec, ability.parameters, amount.parameter_code);

        return { ...grant, amount: { type: 'fixed', value: x * amount.per_unit } };
      }
      if (amount.type === 'parameter_floor_div') {
        const x = this.parameterValueOf(spec, ability.parameters, amount.parameter_code);
        const divisor = amount.divisor === 0 ? 1 : amount.divisor;

        return { ...grant, amount: { type: 'fixed', value: Math.floor(x / divisor) } };
      }

      return grant;
    }

    if (grant.type === 'characteristic_parameter') {
      const x = this.parameterDimensionalValueOf(spec, ability.parameters, grant.parameter_code);

      return {
        ...grant,
        type: 'characteristic',
        value: { base: x.base * grant.per_unit, size: x.size },
      } as Grant;
    }

    return grant;
  }

  /** Значение параметра способности: из выбора, иначе дефолт спеки. */
  private parameterValueOf(
    spec: AbilitySpec,
    parameters: CharacterAbility['parameters'] | undefined,
    code: string,
  ): number {
    if (spec.type === 'group') return 1;
    const chosen = parameters?.[code];
    if (chosen !== undefined) return this.toNumber(chosen);

    const parameter = spec.parameters?.find((p) => p.code === code);
    if (parameter) return this.toNumber(parameter.default);

    return 1;
  }

  /** Размерное значение параметра (для грантов «дать характеристику»): выбранное или дефолт спеки. */
  private parameterDimensionalValueOf(
    spec: AbilitySpec,
    parameters: CharacterAbility['parameters'] | undefined,
    code: string,
  ): DimensionalNumberValue {
    if (spec.type === 'group') return { base: 1, size: 0 };
    const chosen = parameters?.[code];
    if (chosen !== undefined) return typeof chosen === 'number' ? { base: chosen, size: 0 } : chosen;

    const parameter = spec.parameters?.find((p) => p.code === code);
    if (parameter) return this.dimOf(parameter, 'default', 1);

    return { base: 1, size: 0 };
  }

  /**
   * Модификаторы одного источника не складываются: применяется самый сильный бонус (макс.
   * положительный) и самый сильный штраф (мин. отрицательный) — по ТР §7 «Модификаторы».
   * Группа — конкретный источник (sourceRuleId): модификаторы от разных источников суммируются.
   */
  private aggregateModifiers(
    targetCode: string,
    entries: { role: string | null; sourceRuleId: string | null; delta: number }[],
  ): CharacteristicModifier[] {
    const groups = new Map<string | null, typeof entries>();
    for (const entry of entries) {
      const group = groups.get(entry.sourceRuleId);
      if (group) group.push(entry);
      else groups.set(entry.sourceRuleId, [entry]);
    }

    const result: CharacteristicModifier[] = [];
    for (const group of groups.values()) {
      let bestBonus: (typeof group)[number] | null = null;
      let worstPenalty: (typeof group)[number] | null = null;
      for (const entry of group) {
        if (entry.delta > 0 && (bestBonus === null || entry.delta > bestBonus.delta)) bestBonus = entry;
        if (entry.delta < 0 && (worstPenalty === null || entry.delta < worstPenalty.delta)) worstPenalty = entry;
      }
      if (bestBonus) result.push(this.modifierOf(bestBonus, targetCode));
      if (worstPenalty) result.push(this.modifierOf(worstPenalty, targetCode));
    }

    return result;
  }

  private modifierOf(
    entry: { sourceRuleId: string | null; delta: number },
    targetCode: string,
  ): CharacteristicModifier {
    return { sourceRuleId: entry.sourceRuleId, sourceLabel: null, delta: entry.delta, target: targetCode, scope: null };
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
      case 'age':
        return 'от возраста';
      default:
        return 'от правила';
    }
  }

  private effectiveEquippedSpec(
    item: InventoryItem,
    reference: CharacterReferenceService,
    keywords: Keyword[],
  ): ItemSpec | null {
    if (!item.equipped || item.ruleId === null) return null;
    const rule = reference.ruleById(item.ruleId);
    const spec = rule?.type === 'item' ? (rule.spec as ItemSpec | undefined) : undefined;
    if (!rule || !spec) return null;
    const byId = new Map(keywords.map((keyword) => [keyword.id, keyword.code]));
    const codes = (rule.keywordIds ?? []).map((id) => byId.get(id)).filter((code): code is string => Boolean(code));
    const modifiers = (item.modifierRuleIds ?? [])
      .map((id) => reference.ruleById(id))
      .filter((entry): entry is Rule => entry !== null);

    return itemModifierService.applyStack(spec, modifiers, codes).spec;
  }

  private formulaContext(
    build: CharacterBuild,
    characteristicValues: Map<string, DimensionalNumberValue>,
    reference: CharacterReferenceService,
    keywords: Keyword[] = [],
  ): FormulaContext {
    const abilityLevels = new Map<string, number>();
    for (const ability of build.abilities) {
      const rule = reference.ruleById(ability.ruleId);
      if (!rule) continue;
      // У множественного навыка несколько экземпляров (записей) — уровень для формул = max.
      const current = abilityLevels.get(rule.code) ?? 0;
      if (ability.level > current) abilityLevels.set(rule.code, ability.level);
    }

    // Производные уровни (D109) и агрегаты «Развитие X» (D108): гранты от уровня ссылаются
    // на ability_code самого правила — подкладываем вычисленный уровень в контекст формул.
    for (const [code, level] of this.derivedAbilityLevels(build, reference, keywords)) {
      if (level > 0) abilityLevels.set(code, level);
    }

    return { characteristicValues, abilityLevels };
  }

  private ruleIdOfCode(reference: CharacterReferenceService, code: string): string {
    return reference.ruleByCode(code)?.id ?? code;
  }

  private toNumber(value: number | DimensionalNumberValue): number {
    return typeof value === 'number' ? value : new DimensionalNumber(value).toNumber();
  }

  private maxLevelOf(cost: AbilityCost): number {
    switch (cost.kind) {
      case 'array':
        return cost.levels_cost.length;
      case 'progression':
        return cost.max_level;
      case 'parameter':
        return 1;
      case 'parameter_table':
        return 1;
      case 'parameter_sum_tables':
        return cost.max_level;
      case 'automatic':
        return 1;
    }
  }

  private levelCosts(cost: AbilityCost, resolveParameter?: (code: string) => number): number[] {
    switch (cost.kind) {
      case 'array':
        return [...cost.levels_cost];
      case 'progression': {
        const result: number[] = [];
        for (let level = 1; level <= cost.max_level; level++) {
          result.push(cost.base_cost + (level - 1) * cost.step);
        }

        return result;
      }
      case 'parameter': {
        const value = resolveParameter?.(cost.parameter_code) ?? 1;

        return [cost.per_unit * value];
      }
      case 'parameter_table': {
        const value = resolveParameter?.(cost.parameter_code) ?? 1;

        return [typeof value === 'number' ? value : (cost.costs[String(value)] ?? 0)];
      }
      case 'parameter_sum_tables': {
        let sum = 0;
        for (const [code, table] of Object.entries(cost.tables)) {
          const value = resolveParameter?.(code) ?? 0;
          sum += table[String(value)] ?? 0;
        }

        return [sum];
      }
      case 'automatic':
        return [0];
    }
  }

  private totalCostAtLevel(cost: AbilityCost, level: number, resolveParameter?: (code: string) => number): number {
    if (level <= 0) return 0;
    const costs = this.levelCosts(cost, resolveParameter);

    return costs.slice(0, Math.min(level, costs.length)).reduce((sum, value) => sum + value, 0);
  }

  /**
   * Зона покупки способности по умолчанию: единственная покупаемая зона спека.
   * Мультизонная способность без зафиксированной зоны (старый черновик) — первой по порядку
   * объявления зон (обычно 'os'), чтобы не задваивать списание.
   */
  private purchasableZoneOf(spec: AbilitySpec): string | null {
    if (spec.type === 'group') return null;
    const zones = spec.zones as Partial<Record<string, AbilityCost>> | undefined;
    const purchasable = Object.entries(zones ?? {})
      .filter(([, cost]) => cost && cost.kind !== 'automatic')
      .map(([zoneCode]) => zoneCode);

    return purchasable.length > 0 ? purchasable[0] : null;
  }

  /**
   * Лестница «Владения оружием» (domain_ref 'weapon-family'): стоимость уровней экземпляра —
   * лестница выбранной семьи (правило weapon_family), а не заглушка зоны способности.
   * Null — не «Владение оружием» или семья не найдена.
   */
  private weaponFamilyCostOf(
    rule: Rule,
    ability: CharacterBuild['abilities'][number],
    reference: CharacterReferenceService,
  ): AbilityCost | null {
    if (rule.type !== 'ability') return null;
    const spec = rule.spec as AbilitySpec | undefined;
    if (!spec || spec.type === 'group') return null;
    if (spec.domain_ref !== 'weapon-family') return null;
    const ladder = weaponProficiencyService.weaponFamilyLadder(reference.rules(), ability.domainCode ?? ability.domain);
    if (!ladder) return null;

    return { kind: 'array', levels_cost: ladder };
  }

  /**
   * Опции словаря домена множественного навыка: правила ревизии типов, соответствующих domain_ref
   * (виды → type 'species', языки → type 'language'). Пусто — словаря нет, домен вводится текстом.
   */
  private domainOptionsOf(rules: Rule[], domainRef: string | null): { code: string; name: string }[] {
    if (!domainRef) return [];
    const staticOptions = DOMAIN_STATIC_OPTIONS[domainRef];
    if (checkResolutionService.isCommunicationCheckDomain(domainRef)) {
      const fromChecks = checkResolutionService.communicationCheckOptions(rules);
      if (fromChecks.length > 0) return fromChecks;
    }
    if (staticOptions) return staticOptions;
    const types = DOMAIN_REF_RULE_TYPES[domainRef];
    if (!types) return [];

    return rules.filter((rule) => types.includes(rule.type)).map((rule) => ({ code: rule.code, name: rule.name }));
  }

  /**
   * Опции домена способности: для экземплярных улучшений (multiple + родитель) — экземпляры родителя
   * (домены, которые персонаж уже знает); иначе — словарь по domain_ref.
   */
  private instanceDomainOptions(
    rules: Rule[],
    build: CharacterBuild,
    spec: AbilitySpec,
  ): { code: string; name: string }[] {
    if (spec.type === 'group') return [];
    if (spec.multiple === true && spec.parent_ability_code) {
      const parentRule = rules.find((rule) => rule.code === spec.parent_ability_code);
      if (!parentRule) return [];

      return build.abilities
        .filter((ability) => ability.ruleId === parentRule.id && ability.domain != null)
        .map((ability) => ({ code: ability.domainCode ?? '', name: ability.domain ?? '' }));
    }

    return this.domainOptionsOf(rules, spec.domain_ref ?? null);
  }

  /**
   * Уровни навыков, полученных дарами-навыками особенностей (D100): активные гранты
   * `ability` (напр. «Общительный» даёт «Мастерство общения 2»). Код → уровень.
   */
  private giftedAbilityLevels(build: CharacterBuild, reference: CharacterReferenceService): Map<string, number> {
    const result = new Map<string, number>();
    this.forEachActiveGrant(build, reference, (grant) => {
      if (grant.type !== 'ability') return;
      const level = grant.level ?? 1;
      const current = result.get(grant.ability_code) ?? 0;
      if (level > current) result.set(grant.ability_code, level);
    });

    return result;
  }

  /**
   * Производные уровни способностей (D109): уровень из «опыта» = суммы стоимостей взятых
   * способностей с признаком source_keyword. Пример: «Ближний бой» — опыт ближнего боя (2/8/16).
   */
  private derivedAbilityLevels(
    build: CharacterBuild,
    reference: CharacterReferenceService,
    keywords: Keyword[],
  ): Map<string, number> {
    const keywordByCode = new Map(keywords.map((keyword) => [keyword.code, keyword.id]));
    const parameterAutoValues = this.racialAutomaticValues(build, reference, this.rulesOf(reference));
    const raceFixedBases = this.raceFixedBases(build, reference);

    const experienceOf = (sourceKeyword: string): number => {
      let sum = 0;
      for (const ability of build.abilities) {
        if (ability.level < 1) continue;
        const rule = reference.ruleById(ability.ruleId);
        if (!rule) continue;
        const keywordIds = new Set(rule.keywordIds ?? []);
        if (!keywordIds.has(keywordByCode.get(sourceKeyword) ?? -1)) continue;
        const spec = rule.type === 'ability' ? (rule.spec as AbilitySpec | undefined) : undefined;
        if (!spec || spec.type === 'group') continue;
        const zoneCode = ability.zone ?? this.purchasableZoneOf(spec);
        if (!zoneCode) continue;
        // «Владение оружием» (domain_ref weapon-family): стоимость уровней — лестница выбранной
        // семьи (правило weapon_family), а не заглушка зоны способности.
        const cost = this.weaponFamilyCostOf(rule, ability, reference) ?? spec.zones[zoneCode];
        if (!cost) continue;
        sum += this.totalCostAtLevel(cost, ability.level, (code) =>
          this.parameterCostValue(build, rule, spec, code, parameterAutoValues, raceFixedBases),
        );
      }

      return sum;
    };

    const result = new Map<string, number>();
    for (const rule of reference.rules()) {
      if (rule.type !== 'ability') continue;
      const spec = rule.spec as AbilitySpec | undefined;
      if (!spec || spec.type === 'group') continue;
      const derived = spec.derived_level;
      if (!derived) continue;
      const experience = experienceOf(derived.source_keyword);
      const level = derived.thresholds.filter((threshold: number) => experience >= threshold).length;
      result.set(rule.code, level);
    }

    // Агрегаты «Развитие X» (D108): уровень по числу методов стоимости ≥ ступени (жадный спуск).
    for (const rule of reference.rules()) {
      if (rule.type !== 'ability') continue;
      const spec = rule.spec as AbilitySpec | undefined;
      if (!spec || spec.type === 'group') continue;
      const aggregate = spec.aggregate;
      if (!aggregate) continue;
      const level = this.aggregateLevelOf(build, reference, aggregate.method_keyword, aggregate.levels, keywords);
      result.set(rule.code, level);
    }

    return result;
  }

  /**
   * Уровень агрегата «Развитие X» (D108): уровень N достигнут, если для каждой ступени стоимости
   * 1..N есть `levels[N-1]` методов с признаком method_keyword стоимостью ≥ N, без пересечения.
   * Жадный набор снизу вверх: на каждом уровне берутся наименьшие доступные методы со стоимостью ≥ N.
   */
  private aggregateLevelOf(
    build: CharacterBuild,
    reference: CharacterReferenceService,
    methodKeyword: string,
    levels: number[],
    keywords: Keyword[],
  ): number {
    const methodId = keywords.find((keyword) => keyword.code === methodKeyword)?.id;
    if (methodId === undefined) return 0;

    const parameterAutoValues = this.racialAutomaticValues(build, reference, this.rulesOf(reference));
    const raceFixedBases = this.raceFixedBases(build, reference);
    const costs: number[] = [];
    for (const ability of build.abilities) {
      if (ability.level < 1) continue;
      const rule = reference.ruleById(ability.ruleId);
      if (!rule) continue;
      if (!(rule.keywordIds ?? []).includes(methodId)) continue;
      const spec = rule.type === 'ability' ? (rule.spec as AbilitySpec | undefined) : undefined;
      if (!spec || spec.type === 'group') continue;
      if (spec.aggregate) continue; // агрегат сам не метод
      const zoneCode = ability.zone ?? this.purchasableZoneOf(spec);
      if (!zoneCode) continue;
      const cost = spec.zones[zoneCode];
      if (!cost) continue;
      costs.push(
        this.totalCostAtLevel(cost, ability.level, (code) =>
          this.parameterCostValue(build, rule, spec, code, parameterAutoValues, raceFixedBases),
        ),
      );
    }

    const available = [...costs].sort((a, b) => a - b);
    let level = 0;
    for (let tier = 1; tier <= levels.length; tier++) {
      const need = levels[tier - 1];
      if (need <= 0) {
        level = tier;
        continue;
      }
      const taken: number[] = [];
      for (let i = 0; i < available.length && taken.length < need; i++) {
        if (available[i] >= tier) taken.push(i);
      }
      if (taken.length < need) break;
      for (let i = taken.length - 1; i >= 0; i--) available.splice(taken[i], 1);
      level = tier;
    }

    return level;
  }

  /**
   * Значение параметра для стоимости параметрической цены: выбранное значение минус бесплатное
   * автоматическое значение расы (докупка сверх авто оплачивается; Аэрон: «Сопротивление магии 2»
   * бесплатно, X>2 докупается). Для не выбранной способности — дефолт спеки.
   * Для parameter_table возвращает число: стоимость по таблице, минус бесплатная ступень дара расы.
   */
  private parameterCostValue(
    build: CharacterBuild,
    rule: Rule,
    spec: AbilitySpec,
    code: string,
    autoValues: Map<string, Record<string, DimensionalNumberValue>>,
    raceFixedBases: Map<string, DimensionalNumberValue> = new Map(),
  ): number {
    if (spec.type === 'group') return 1;
    const chosen = build.abilities.find((ability) => ability.ruleId === rule.id)?.parameters?.[code];
    const osCost = spec.zones?.os;
    if (osCost?.kind === 'parameter_table') {
      const grantCode = this.parameterGrantCode(spec);
      const raceFixed = grantCode === null ? null : (raceFixedBases.get(grantCode) ?? null);
      const fallback = raceFixed ?? spec.parameters?.find((p) => p.code === code)?.default;
      const value = chosen ?? fallback;
      const cost =
        value === undefined
          ? 0
          : (osCost.costs[
              new DimensionalNumber(typeof value === 'number' ? { base: value, size: 0 } : value).toString()
            ] ?? 0);
      if (raceFixed === null) return cost;

      const freeCost = osCost.costs[new DimensionalNumber(raceFixed).toString()] ?? 0;

      return Math.max(0, cost - freeCost);
    }

    const auto = this.toNumber(autoValues.get(rule.code)?.[code] ?? { base: 0, size: 0 });
    if (chosen !== undefined) return Math.max(0, this.toNumber(chosen) - auto);

    const parameter = spec.parameters?.find((p) => p.code === code);
    if (parameter) return this.toNumber(parameter.default);

    return 1;
  }

  /** Тэги → семейства оружия с этим тэгом: вычисляются из предметов (proficiency_family_code + keywordIds). */
  private weaponFamilyTagsOf(reference: CharacterReferenceService, keywords: Keyword[]): Map<string, Set<string>> {
    const result = new Map<string, Set<string>>();

    for (const rule of reference.rules()) {
      if (rule.type !== 'item') continue;
      const spec = rule.spec as
        { proficiency_family_code?: string | null; weapon?: { weapon_profiles?: unknown[] } | null } | undefined;
      const familyCode = spec?.proficiency_family_code;
      if (!familyCode) continue;
      // Только оружие — не доспехи и не щиты.
      if (!spec?.weapon) continue;

      for (const keywordId of rule.keywordIds ?? []) {
        const kw = keywords.find((entry: Keyword) => entry.id === keywordId);
        if (!kw) continue;

        const set = result.get(kw.code) ?? new Set<string>();
        set.add(familyCode);
        result.set(kw.code, set);
      }
    }

    return result;
  }

  spentInZone(ability: EditorAbility, zoneCode = 'os'): number {
    const zone = ability.zones.find((entry) => entry.zoneCode === zoneCode) ?? null;
    if (!zone) return 0;

    if (ability.multiple) {
      return ability.instances.reduce(
        (sum, instance) =>
          sum + zone.levelCosts.slice(0, instance.level).reduce((instanceSum, cost) => instanceSum + cost, 0),
        0,
      );
    }

    return zone.levelCosts.slice(ability.giftedLevel, ability.level).reduce((sum, cost) => sum + cost, 0);
  }

  costKind(ability: EditorAbility, zoneCode = 'os'): EditorAbilityZone['kind'] | null {
    return ability.zones.find((entry) => entry.zoneCode === zoneCode)?.kind ?? null;
  }

  spentInGroup(group: EditorAbilityGroup, zoneCode = 'os'): number {
    return group.members.reduce((sum, member) => sum + this.spentInZone(member, zoneCode), 0);
  }
}
