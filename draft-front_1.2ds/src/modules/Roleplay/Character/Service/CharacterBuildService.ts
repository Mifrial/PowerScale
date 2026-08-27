import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { InventoryBaseline } from '@/modules/Roleplay/Character/Dto/Editor/InventoryBaseline';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { CharacteristicPurchase } from '@/modules/Roleplay/Character/Dto/Editor/CharacteristicPurchase';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { ItemModifierSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierSpec';
import type { RaceSpec } from '@/modules/Roleplay/Rule/Dto/Race/RaceSpec';
import type { AbilityCost } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityCost';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import { characterEditorService } from '@/modules/Roleplay/Character/Service/Instance/characterEditorService';
import { itemModifierService } from '@/modules/Roleplay/Rule/Service/Instance/itemModifierService';
import { weaponProficiencyService } from '@/modules/Roleplay/Character/Service/Instance/weaponProficiencyService';
import { racialInnateGearService } from '@/modules/Roleplay/Character/Service/Instance/racialInnateGearService';

/**
 * Иммутабельные переходы выборов редактора (CharacterBuild). Компоненты не мутируют build
 * напрямую — переходы трассируются и тестируются (frontend-rules: апдейтеры глубоких объектов).
 */
export class CharacterBuildService {
  constructor(private readonly editor = characterEditorService) {}

  /**
   * Устанавливает уровень способности (0 — убрать). Для не-multiple способностей. Если способность
   * входит в группу с selectLimit 1 (шкала-выбор), при взятии снимает остальные способности группы.
   *
   * Ограничения этапа «Личность» (options, считаются PersonalityTab из model.personality):
   * - wealthLocked: особенность богатства недоступна (взятие/снятие блокируется при edit — «только при создании»);
   * - featureLimit: лимит числа ol-особенностей (без богатства) не даёт взять новую сверх лимита.
   *
   * Зона покупки (options.zone, D111): для способностей с несколькими покупаемыми зонами (os+or)
   * фиксируется зона, из которой списывается стоимость (списывает только её buildBudgets).
   *
   * Множественные навыки (spec.multiple) этим методом не меняются — их экземпляры управляются
   * методами add/set/removeAbilityInstance (каждая запись = экземпляр со своим доменом и уровнем).
   */
  setAbilityLevel(
    build: CharacterBuild,
    ruleId: string,
    level: number,
    rules: Rule[],
    options: {
      featureLimit?: number | null;
      wealthLocked?: boolean;
      wealthRuleIds?: ReadonlySet<string>;
      zone?: string;
    } = {},
  ): CharacterBuild {
    if (this.isMultipleRule(rules, ruleId)) return build;
    if (level > 0) {
      if (options.wealthLocked && options.wealthRuleIds?.has(ruleId)) return build;
      if (options.featureLimit !== null && options.featureLimit !== undefined && this.isOlZoneRule(rules, ruleId)) {
        const taken = new Set(
          build.abilities
            .filter(
              (ability) =>
                ability.level > 0 &&
                !options.wealthRuleIds?.has(ability.ruleId) &&
                this.isOlZoneRule(rules, ability.ruleId),
            )
            .map((ability) => ability.ruleId),
        ).size;
        const alreadyTaken = build.abilities.some((ability) => ability.ruleId === ruleId && ability.level > 0);
        if (!alreadyTaken && taken >= options.featureLimit) return build;
      }
    }

    const others = build.abilities.filter((ability) => ability.ruleId !== ruleId);
    const existing = build.abilities.find((ability) => ability.ruleId === ruleId);
    const abilities =
      level > 0
        ? [
            ...others,
            {
              ruleId,
              level,
              ...(existing?.parameters ? { parameters: existing.parameters } : {}),
              ...((options.zone ?? existing?.zone) ? { zone: options.zone ?? existing?.zone } : {}),
            },
          ]
        : others;

    return {
      ...build,
      abilities: this.applyGroupSelectLimit(abilities, ruleId, level, rules, build.abilities),
    };
  }

  /** Множественный навык ревизии (spec.multiple): экземпляры управляются dedicated-методами. */
  private isMultipleRule(rules: Rule[], ruleId: string): boolean {
    const rule = rules.find((entry) => entry.id === ruleId);
    const spec = rule?.type === 'ability' ? (rule.spec as { multiple?: boolean } | undefined) : undefined;

    return spec?.multiple === true;
  }

  /** Добавляет экземпляр множественного навыка (новый домен, стартовый уровень 1). */
  addAbilityInstance(
    build: CharacterBuild,
    ruleId: string,
    domain: string,
    rules: Rule[],
    options: { zone?: string; domainCode?: string | null } = {},
  ): CharacterBuild {
    const trimmed = domain.trim();
    if (!trimmed) return build;
    if (build.abilities.some((ability) => ability.ruleId === ruleId && ability.domain === trimmed)) return build;
    // Экземплярное улучшение (multiple + родитель): домен должен быть у экземпляра родителя —
    // улучшение «Письменности» распространяется на конкретный язык, который уже известен.
    if (this.isInstanceImprovement(rules, ruleId)) {
      const parentRuleId = this.parentRuleIdOf(rules, ruleId);
      if (parentRuleId === null) return build;
      const parentInstance = build.abilities.find(
        (ability) => ability.ruleId === parentRuleId && ability.domain === trimmed,
      );
      if (!parentInstance) return build;
      const parentCode = rules.find((entry) => entry.id === parentRuleId)?.code;
      if (parentCode) {
        const requiredLevel = this.parentLevelRequiredFor(rules, ruleId, parentCode);
        if (requiredLevel !== null && parentInstance.level < requiredLevel) return build;
      }
    }

    const abilities = [
      ...build.abilities,
      {
        ruleId,
        level: 1,
        domain: trimmed,
        zone: options.zone,
        ...(options.domainCode !== undefined ? { domainCode: options.domainCode } : {}),
      },
    ];

    return { ...build, abilities: this.applyGroupSelectLimit(abilities, ruleId, 1, rules, build.abilities) };
  }

  /** Устанавливает уровень конкретного экземпляра (0 — снять экземпляр). */
  setAbilityInstanceLevel(
    build: CharacterBuild,
    ruleId: string,
    domain: string,
    level: number,
    rules: Rule[],
    options: { zone?: string } = {},
  ): CharacterBuild {
    const existing = build.abilities.find((ability) => ability.ruleId === ruleId && ability.domain === domain);
    if (!existing) return build;
    if (level > 0 && level > this.maxInstanceLevelOf(rules, ruleId, domain)) return build;

    const others = build.abilities.filter((ability) => !(ability.ruleId === ruleId && ability.domain === domain));
    const abilities =
      level > 0
        ? [
            ...others,
            {
              ...existing,
              level,
              ...(options.zone && existing.zone !== options.zone ? { zone: options.zone } : {}),
            },
          ]
        : others;

    return { ...build, abilities };
  }

  /**
   * Переименовывает домен экземпляра (свободный текст или выбор из словаря). Экземпляры-улучшения
   * с тем же доменом переименовываются каскадно (их привязка — по домену родителя).
   */
  setAbilityInstanceDomain(
    build: CharacterBuild,
    ruleId: string,
    oldDomain: string,
    newDomain: string,
    options: { domainCode?: string | null } = {},
    rules: Rule[] = [],
  ): CharacterBuild {
    const trimmed = newDomain.trim();
    if (!trimmed || trimmed === oldDomain) return build;
    if (build.abilities.some((ability) => ability.ruleId === ruleId && ability.domain === trimmed)) return build;

    const cascadeRuleIds = new Set(this.instanceImprovementRuleIds(rules, ruleId));

    return {
      ...build,
      abilities: build.abilities.map((ability) =>
        (ability.ruleId === ruleId || cascadeRuleIds.has(ability.ruleId)) && ability.domain === oldDomain
          ? {
              ...ability,
              domain: trimmed,
              ...(options.domainCode !== undefined ? { domainCode: options.domainCode } : {}),
            }
          : ability,
      ),
    };
  }

  /** Удаляет экземпляр множественного навыка по домену; экземпляры-улучшения того же домена — каскадно. */
  removeAbilityInstance(build: CharacterBuild, ruleId: string, domain: string, rules: Rule[] = []): CharacterBuild {
    const removedRuleIds = new Set([ruleId, ...this.instanceImprovementRuleIds(rules, ruleId)]);

    return {
      ...build,
      abilities: build.abilities.filter(
        (ability) => !(removedRuleIds.has(ability.ruleId) && ability.domain === domain),
      ),
    };
  }

  /**
   * Устанавливает уровень владения семьёй оружия (блок «Владение оружием» в панели предмета).
   * Уровень 0 — снять владение; иначе — гарантированно создать экземпляр (если его нет) и выставить
   * целевой уровень (включая понижение до 1 — addAbilityInstance сам по себе no-op на существующем).
   */
  setWeaponMastery(
    build: CharacterBuild,
    masteryRuleId: string,
    familyName: string,
    familyCode: string,
    level: number,
    rules: Rule[],
  ): CharacterBuild {
    if (level <= 0) {
      const existing = build.abilities.find(
        (ability) =>
          ability.ruleId === masteryRuleId && (ability.domain === familyName || ability.domainCode === familyCode),
      );
      if (existing?.gifted) {
        return this.setAbilityInstanceLevel(build, masteryRuleId, existing.domain ?? familyName, 1, rules, {
          zone: 'or',
        });
      }

      return this.removeAbilityInstance(build, masteryRuleId, familyName, rules);
    }
    const withInstance = this.addAbilityInstance(build, masteryRuleId, familyName, rules, {
      zone: 'or',
      domainCode: familyCode,
    });

    return this.setAbilityInstanceLevel(withInstance, masteryRuleId, familyName, level, rules, { zone: 'or' });
  }

  /**
   * Устанавливает домен одиночной способности (domain_ref без multiple). Если записи нет —
   * способность получена даром (D100): материализует запись с флагом gifted (уровень 1 из гранта,
   * бюджет не списывается). Пустое значение: gifted-запись удаляется, у купленной домен сбрасывается.
   */
  setAbilityDomain(
    build: CharacterBuild,
    ruleId: string,
    domain: string,
    options: { domainCode?: string | null } = {},
  ): CharacterBuild {
    const trimmed = domain.trim();
    const existing = build.abilities.find((ability) => ability.ruleId === ruleId);

    if (!trimmed) {
      if (!existing) return build;
      if (existing.gifted) {
        return { ...build, abilities: build.abilities.filter((ability) => ability.ruleId !== ruleId) };
      }

      return {
        ...build,
        abilities: build.abilities.map((ability) => {
          if (ability.ruleId !== ruleId) return ability;
          const next = { ...ability };
          delete next.domain;
          delete next.domainCode;

          return next;
        }),
      };
    }

    if (existing) {
      return {
        ...build,
        abilities: build.abilities.map((ability) =>
          ability.ruleId === ruleId
            ? {
                ...ability,
                domain: trimmed,
                ...(options.domainCode !== undefined ? { domainCode: options.domainCode } : {}),
              }
            : ability,
        ),
      };
    }

    return {
      ...build,
      abilities: [
        ...build.abilities,
        {
          ruleId,
          level: 1,
          domain: trimmed,
          gifted: true,
          ...(options.domainCode !== undefined ? { domainCode: options.domainCode } : {}),
        },
      ],
    };
  }

  /** Экземплярное улучшение (multiple с родителем): привязка к конкретному экземпляру родителя. */
  private isInstanceImprovement(rules: Rule[], ruleId: string): boolean {
    const rule = rules.find((entry) => entry.id === ruleId);
    const spec =
      rule?.type === 'ability'
        ? (rule.spec as { multiple?: boolean; parent_ability_code?: string | null } | undefined)
        : undefined;

    return spec?.multiple === true && spec.parent_ability_code != null;
  }

  /** ruleId родительского правила экземплярного улучшения (null — не улучшение/родитель не найден). */
  private parentRuleIdOf(rules: Rule[], ruleId: string): string | null {
    const rule = rules.find((entry) => entry.id === ruleId);
    const spec =
      rule?.type === 'ability' ? (rule.spec as { parent_ability_code?: string | null } | undefined) : undefined;
    const parentCode = spec?.parent_ability_code;
    if (!parentCode) return null;

    return rules.find((entry) => entry.code === parentCode)?.id ?? null;
  }

  /** ruleId экземплярных улучшений правила (multiple с parent_ability_code == code правила). */
  private instanceImprovementRuleIds(rules: Rule[], ruleId: string): string[] {
    const rule = rules.find((entry) => entry.id === ruleId);
    if (!rule) return [];

    return rules
      .filter((entry) => {
        if (entry.type !== 'ability') return false;
        const spec = entry.spec as { multiple?: boolean; parent_ability_code?: string | null } | undefined;

        return spec?.multiple === true && spec.parent_ability_code === rule.code;
      })
      .map((entry) => entry.id);
  }

  /**
   * Минимальный требуемый уровень родителя для взятия 1-го уровня экземплярного улучшения:
   * максимум min_level по has_ability-требованиям уровня 1, ссылающимся на родителя
   * (синтез родителя по D114 даёт минимум 1). Пример: Грамотность → язык уровня 2.
   */
  private parentLevelRequiredFor(rules: Rule[], ruleId: string, parentCode: string): number | null {
    const rule = rules.find((entry) => entry.id === ruleId);
    const spec =
      rule?.type === 'ability'
        ? (rule.spec as { requirements?: { level: number; requirements: unknown[] }[] } | undefined)
        : undefined;
    const levelOne = spec?.requirements?.find((entry) => entry.level === 1);
    if (!levelOne) return 1;

    let max = 1;
    for (const requirement of levelOne.requirements) {
      const candidate = requirement as { type?: string; ability_code?: string; min_level?: number };
      if (candidate.type === 'has_ability' && candidate.ability_code === parentCode) {
        max = Math.max(max, candidate.min_level ?? 1);
      }
    }

    return max;
  }

  /** Максимальный уровень экземпляра множественного навыка (по покупаемым зонам спека). */
  private maxInstanceLevelOf(rules: Rule[], ruleId: string, domain?: string): number {
    const rule = rules.find((entry) => entry.id === ruleId);
    const spec =
      rule?.type === 'ability'
        ? (rule.spec as { zones?: Partial<Record<string, AbilityCost>>; domain_ref?: string | null } | undefined)
        : undefined;
    // «Владение оружием»: максимум экземпляра = длина лестницы выбранной семьи.
    if (spec?.domain_ref === 'weapon-family') {
      return weaponProficiencyService.weaponFamilyLadder(rules, domain)?.length ?? 1;
    }
    let max = 1;
    for (const cost of Object.values(spec?.zones ?? {})) {
      if (!cost || cost.kind === 'automatic') continue;
      const zoneMax =
        cost.kind === 'array' ? cost.levels_cost.length : cost.kind === 'progression' ? cost.max_level : 1;
      if (zoneMax > max) max = zoneMax;
    }

    return max;
  }

  /** Правило — способность с зоной цен `ol` (особенность личности). */
  private isOlZoneRule(rules: Rule[], ruleId: string): boolean {
    const rule = rules.find((entry) => entry.id === ruleId);
    const spec =
      rule?.type === 'ability'
        ? (rule.spec as { type?: string; zones?: Record<string, unknown> } | undefined)
        : undefined;

    return spec !== undefined && spec.type !== 'group' && spec.zones?.['ol'] !== undefined;
  }

  /** Заменяет покупки характеристик. */
  setPurchases(build: CharacterBuild, characteristicPurchases: CharacteristicPurchase[]): CharacterBuild {
    return { ...build, characteristicPurchases };
  }

  /**
   * Устанавливает значение параметра покупки. X === 0 и остальные параметры тоже 0 — способность снята.
   * Для parameter_sum_tables уровень = сумма параметров (потолок пула), иначе уровень 1.
   */
  setAbilityParameter(
    build: CharacterBuild,
    ruleId: string,
    code: string,
    value: number | DimensionalNumberValue,
    rules: Rule[],
  ): CharacterBuild {
    const others = build.abilities.filter((ability) => ability.ruleId !== ruleId);
    const existing = build.abilities.find((ability) => ability.ruleId === ruleId);
    const nextValue = typeof value === 'number' ? { base: value, size: 0 } : { ...value };
    const parameters = {
      ...existing?.parameters,
      [code]: nextValue,
    };

    const rule = rules.find((entry) => entry.id === ruleId);
    const spec = rule?.type === 'ability' ? (rule.spec as AbilitySpec | undefined) : undefined;
    const zones = spec && spec.type !== 'group' ? spec.zones : undefined;
    const purchaseCost = zones
      ? (Object.values(zones).find((cost) => cost && cost.kind !== 'automatic') ?? undefined)
      : undefined;

    if (purchaseCost?.kind === 'parameter_sum_tables') {
      const paramMax = (parameterCode: string): number => {
        const parameter =
          spec && spec.type !== 'group' ? spec.parameters?.find((entry) => entry.code === parameterCode) : undefined;
        if (!parameter?.max) return 6;

        return typeof parameter.max === 'number' ? parameter.max : parameter.max.base;
      };
      const numericOf = (parameterCode: string): number => {
        const raw = parameters[parameterCode];
        if (raw === undefined) return 0;

        return typeof raw === 'number' ? raw : raw.base;
      };
      let nextNumeric = typeof value === 'number' ? value : value.base;
      nextNumeric = Math.max(0, Math.min(paramMax(code), nextNumeric));
      const othersSum = Object.keys(purchaseCost.tables)
        .filter((parameterCode) => parameterCode !== code)
        .reduce((sum, parameterCode) => sum + numericOf(parameterCode), 0);
      nextNumeric = Math.min(nextNumeric, Math.max(0, purchaseCost.max_level - othersSum));
      parameters[code] = { base: nextNumeric, size: 0 };

      const level = Object.keys(purchaseCost.tables).reduce((sum, parameterCode) => sum + numericOf(parameterCode), 0);
      if (level <= 0) return { ...build, abilities: others };

      const abilities = [...others, { ruleId, level, parameters }];

      return { ...build, abilities: this.applyGroupSelectLimit(abilities, ruleId, 1, rules, build.abilities) };
    }

    const numeric = typeof value === 'number' ? value : value.base;
    if (numeric === 0) return { ...build, abilities: others };

    const abilities = [...others, { ruleId, level: 1, parameters }];

    return { ...build, abilities: this.applyGroupSelectLimit(abilities, ruleId, 1, rules, build.abilities) };
  }

  /**
   * Покупка предмета на шаге «Инвентарь» (R1/R4–R6): списывает базовую цену × qty.
   * Снаряжение (оружие/щит/доспех) — всегда новые строки qty 1 без модификаторов.
   * Стекуемые (зелья, товары) — суммируют quantity по ruleId. Модификаторы при покупке игнорируются.
   * Предмет без цены или innate (R5) — no-op.
   */
  buyItem(
    build: CharacterBuild,
    ruleId: string,
    quantity: number,
    rules: Rule[],
    keywords: Keyword[] = [],
    _modifierRuleIds: readonly string[] = [],
  ): CharacterBuild {
    const cost = this.itemCostOf(ruleId, rules, keywords, []);
    if (cost === null || quantity <= 0) return build;

    if (this.isInstancedRule(ruleId, rules)) {
      const inventory = [...build.inventory];
      let nextId = inventory.reduce((max, item) => Math.max(max, item.id), 0) + 1;
      for (let i = 0; i < quantity; i += 1) {
        inventory.push({ id: nextId, ruleId, quantity: 1, equipped: false, modifierRuleIds: [] });
        nextId += 1;
      }

      return { ...build, inventory, money: build.money - cost * quantity };
    }

    const inventory = build.inventory.map((item) =>
      item.ruleId === ruleId ? { ...item, quantity: item.quantity + quantity } : item,
    );
    if (!inventory.some((item) => item.ruleId === ruleId)) {
      const nextId = build.inventory.reduce((max, item) => Math.max(max, item.id), 0) + 1;
      inventory.push({ id: nextId, ruleId, quantity, equipped: false, modifierRuleIds: [] });
    }

    return { ...build, inventory, money: build.money - cost * quantity };
  }

  /**
   * Отмена покупки (R2): возвращает деньги и уменьшает количество ТОЛЬКО сверх базовой линии.
   * Снаряжение — снимает последние добавленные экземпляры (каждый со своей текущей ценой).
   * Стекуемые — уменьшают quantity той же ruleId.
   */
  cancelItemPurchase(
    build: CharacterBuild,
    baseline: InventoryBaseline | null | undefined,
    ruleId: string,
    quantity: number,
    rules: Rule[],
    keywords: Keyword[] = [],
    _modifierRuleIds: readonly string[] = [],
  ): CharacterBuild {
    if (!baseline || quantity <= 0) return build;

    if (this.isInstancedRule(ruleId, rules)) {
      const baselineIds = new Set(
        baseline.inventory.filter((entry) => entry.ruleId === ruleId).map((entry) => entry.id),
      );
      const extras = build.inventory.filter((entry) => entry.ruleId === ruleId && !baselineIds.has(entry.id));
      const amount = Math.min(quantity, extras.length);
      if (amount <= 0) return build;

      const removed = extras.slice(-amount);
      const removedIds = new Set(removed.map((entry) => entry.id));
      let refund = 0;
      for (const entry of removed) {
        const cost = this.itemCostOf(ruleId, rules, keywords, this.normalizedMods(entry.modifierRuleIds));
        if (cost === null) return build;
        refund += cost;
      }

      return {
        ...build,
        inventory: build.inventory.filter((entry) => !removedIds.has(entry.id)),
        money: build.money + refund,
      };
    }

    const cost = this.itemCostOf(ruleId, rules, keywords, []);
    if (cost === null) return build;

    const item = build.inventory.find((entry) => entry.ruleId === ruleId);
    if (!item) return build;

    const baselineQuantity = baseline.inventory
      .filter((entry) => entry.ruleId === ruleId)
      .reduce((sum, entry) => sum + entry.quantity, 0);
    const removable = Math.max(0, item.quantity - baselineQuantity);
    const amount = Math.min(quantity, removable);
    if (amount <= 0) return build;

    const inventory =
      item.quantity - amount === 0
        ? build.inventory.filter((entry) => entry.id !== item.id)
        : build.inventory.map((entry) =>
            entry.id === item.id ? { ...entry, quantity: entry.quantity - amount } : entry,
          );

    return { ...build, inventory, money: build.money + cost * amount };
  }

  /**
   * Отмена одного экземпляра снаряжения по id строки (не трогает базовую линию).
   */
  cancelItemInstance(
    build: CharacterBuild,
    baseline: InventoryBaseline | null | undefined,
    itemId: number,
    rules: Rule[],
    keywords: Keyword[] = [],
  ): CharacterBuild {
    if (!baseline) return build;
    const item = build.inventory.find((entry) => entry.id === itemId);
    if (!item?.ruleId) return build;
    if (baseline.inventory.some((entry) => entry.id === itemId)) return build;

    const cost = this.itemCostOf(item.ruleId, rules, keywords, this.normalizedMods(item.modifierRuleIds));
    if (cost === null) return build;

    return {
      ...build,
      inventory: build.inventory.filter((entry) => entry.id !== itemId),
      money: build.money + cost,
    };
  }

  /**
   * Сменить модификаторы у экземпляра снаряжения (та же строка, доплата/возврат = разница цен).
   * Exclusive-типы вытесняют другой модификатор того же типа. Стекуемые предметы — no-op.
   */
  applyItemModifiers(
    build: CharacterBuild,
    itemId: number,
    modifierRuleIds: readonly string[],
    rules: Rule[],
    keywords: Keyword[] = [],
  ): CharacterBuild {
    const source = build.inventory.find((item) => item.id === itemId);
    if (!source?.ruleId || source.quantity < 1) return build;
    if (!this.isInstancedRule(source.ruleId, rules)) return build;

    const newMods = this.constrainExclusive(this.normalizedMods(modifierRuleIds), rules);
    const oldMods = this.normalizedMods(source.modifierRuleIds);
    if (
      itemModifierService.identityKey(source.ruleId, oldMods) ===
      itemModifierService.identityKey(source.ruleId, newMods)
    ) {
      return build;
    }
    if (!this.modifiersApplicable(source.ruleId, rules, keywords, newMods)) return build;

    const oldCost = this.itemCostOf(source.ruleId, rules, keywords, oldMods);
    const newCost = this.itemCostOf(source.ruleId, rules, keywords, newMods);
    if (oldCost === null || newCost === null) return build;

    const inventory = build.inventory.map((item) =>
      item.id === itemId ? { ...item, modifierRuleIds: newMods } : item,
    );

    return { ...build, inventory, money: build.money - (newCost - oldCost) };
  }

  /** Тумблер экипировки предмета (R3): без жёстких слотов. */
  toggleItemEquipped(build: CharacterBuild, itemId: number, rules: Rule[] = []): CharacterBuild {
    const target = build.inventory.find((item) => item.id === itemId);
    if (target?.ruleId) {
      const rule = rules.find((entry) => entry.id === target.ruleId);
      const spec = rule?.type === 'item' ? (rule.spec as ItemSpec | undefined) : undefined;
      if (spec?.innate) return build;
    }
    const inventory = build.inventory.map((item) =>
      item.id === itemId ? { ...item, equipped: !item.equipped } : item,
    );

    return { ...build, inventory };
  }

  /** Сброс инвентаря к базовой линии (R2): копия снапшота, деньги восстанавливаются. */
  resetInventory(
    build: CharacterBuild,
    baseline: InventoryBaseline | null | undefined,
    rules: Rule[] = [],
  ): CharacterBuild {
    if (!baseline) return build;

    return this.applyInnateGear(
      { ...build, inventory: baseline.inventory.map((item) => ({ ...item })), money: baseline.money },
      rules,
    );
  }

  /** Цена предмета в гм с учётом модификаторов; null для не-предметов, innate и без cost_gm (R5). */
  private itemCostOf(
    ruleId: string,
    rules: Rule[],
    keywords: Keyword[],
    modifierRuleIds: readonly string[],
  ): number | null {
    const rule = rules.find((entry) => entry.id === ruleId);
    const spec = rule?.type === 'item' ? (rule.spec as ItemSpec | undefined) : undefined;
    if (!rule || !spec || spec.innate || spec.cost_gm === null || spec.cost_gm === undefined) return null;

    const codes = this.itemKeywordCodes(rule, keywords);
    const modifiers = modifierRuleIds
      .map((id) => rules.find((entry) => entry.id === id))
      .filter((entry): entry is Rule => entry !== undefined);

    return itemModifierService.applyStack(spec, modifiers, codes).cost;
  }

  private modifiersApplicable(
    ruleId: string,
    rules: Rule[],
    keywords: Keyword[],
    modifierRuleIds: readonly string[],
  ): boolean {
    const rule = rules.find((entry) => entry.id === ruleId);
    if (!rule) return false;
    const codes = this.itemKeywordCodes(rule, keywords);
    const modifiers = modifierRuleIds
      .map((id) => rules.find((entry) => entry.id === id))
      .filter((entry): entry is Rule => entry !== undefined);
    const effective = itemModifierService.effectiveKeywordCodes(codes, modifiers);
    for (const modifier of modifiers) {
      if (modifier.type !== 'item_modifier') return false;
      const spec = modifier.spec as ItemModifierSpec | undefined;
      if (!itemModifierService.isApplicable(spec?.applies, effective)) return false;
    }

    return true;
  }

  private itemKeywordCodes(rule: Rule, keywords: Keyword[]): string[] {
    const byId = new Map(keywords.map((keyword) => [keyword.id, keyword.code]));

    return (rule.keywordIds ?? []).map((id) => byId.get(id)).filter((code): code is string => Boolean(code));
  }

  private normalizedMods(modifierRuleIds: readonly string[] | undefined): string[] {
    return [...(modifierRuleIds ?? [])].filter((id) => id.length > 0).sort();
  }

  private isInstancedRule(ruleId: string, rules: Rule[]): boolean {
    const rule = rules.find((entry) => entry.id === ruleId);
    const spec = rule?.type === 'item' ? (rule.spec as ItemSpec | undefined) : undefined;

    return Boolean(spec?.weapon || spec?.armor || spec?.shield);
  }

  private constrainExclusive(modifierRuleIds: readonly string[], rules: Rule[]): string[] {
    let selected: string[] = [];
    for (const id of modifierRuleIds) {
      if (selected.includes(id)) continue;
      selected = itemModifierService.toggleSelection(selected, id, rules);
    }

    return this.normalizedMods(selected);
  }

  /**
   * Смена расы (ТР §7: изменение ранее сделанного выбора). Сбрасывает покупки характеристик
   * (привязаны к расовому профилю) и способности, ставшие недоступными (не расовые и не
   * удовлетворяют требования уровня).
   */
  applyRace(
    build: CharacterBuild,
    raceRuleId: string | null,
    rules: Rule[],
    config: CharacterCreationConfig,
    keywords: Keyword[],
  ): CharacterBuild {
    const withNewRace: CharacterBuild = {
      ...build,
      raceRuleId,
      characteristicPurchases: this.purchasesOf(build, raceRuleId, rules),
    };
    const model = this.editor.build(withNewRace, rules, config, keywords);
    const abilityByRuleId = new Map(model.abilities.map((ability) => [ability.ruleId, ability]));

    // Способность остаётся, если доступна в новой расе (automatic или требования выполнены) и
    // каждое выбранное значение параметра «X» лежит в её диапазоне [min, max] (потолок/минимум).
    const compatible = (ability: CharacterBuild['abilities'][number]): boolean => {
      const editor = abilityByRuleId.get(ability.ruleId);
      if (!editor || !(editor.automatic || editor.levels[0]?.met)) return false;

      for (const parameter of editor.parameters) {
        const raw = ability.parameters?.[parameter.code];
        if (raw === undefined) continue;
        const value = new DimensionalNumber(typeof raw === 'number' ? { base: raw, size: 0 } : raw);

        if (value.compare(new DimensionalNumber(parameter.min)) < 0) return false;
        if (value.compare(new DimensionalNumber(parameter.max)) > 0) return false;
      }

      return true;
    };

    return this.applyInnateGear({ ...withNewRace, abilities: build.abilities.filter(compatible) }, rules);
  }

  /**
   * Восстановление выборов из версии (edit, copy-on-write). Покупки характеристик реконструируются
   * по совпадению базы с лестницей расы; несовпавшая база откатывается к минимуму (прототип).
   */
  fromVersion(version: CharacterVersion, spaceId: number, rules: Rule[]): CharacterBuild {
    return this.applyInnateGear(
      {
        name: version.name,
        shortDescription: version.shortDescription,
        fullDescription: version.fullDescription,
        spaceId,
        spaceCode: version.spaceCode,
        rulesRevision: version.rulesRevision,
        raceRuleId: version.raceRuleId,
        characteristicPurchases: this.purchasesFromVersion(version, rules),
        abilities: version.abilities.map((ability) => ({ ...ability })),
        resources: version.resources,
        inventory: version.inventory.map((item) => ({ ...item })),
        states: version.states,
        money: version.money,
        ageYears: version.ageYears ?? null,
        olTotal: version.points.olTotal,
      },
      rules,
    );
  }

  private purchasesFromVersion(version: CharacterVersion, rules: Rule[]): CharacteristicPurchase[] {
    const raceRule = version.raceRuleId === null ? null : rules.find((rule) => rule.id === version.raceRuleId);
    const spec = raceRule?.type === 'race' ? (raceRule.spec as RaceSpec | undefined) : undefined;
    if (!spec) return [];

    const result: CharacteristicPurchase[] = [];
    for (const characteristic of spec.characteristics ?? []) {
      if (characteristic.mode !== 'purchased') continue;
      const versionValue = version.characteristics.find((value) => {
        const rule = rules.find((entry) => entry.id === value.ruleId);

        return rule?.code === characteristic.characteristic_code;
      });
      if (!versionValue) continue;
      const rung = characteristic.purchase?.find((level) => this.sameDimensional(level.value, versionValue.base));
      if (rung) result.push({ characteristicCode: characteristic.characteristic_code, cost: rung.cost });
    }

    return result;
  }

  private sameDimensional(a: DimensionalNumberValue, b: DimensionalNumberValue): boolean {
    return a.base === b.base && a.size === b.size;
  }

  private purchasesOf(build: CharacterBuild, raceRuleId: string | null, rules: Rule[]): CharacteristicPurchase[] {
    const rule = raceRuleId === null ? null : rules.find((entry) => entry.id === raceRuleId);
    const spec = rule?.type === 'race' ? (rule.spec as RaceSpec | undefined) : undefined;
    const purchasedCodes = new Set(
      (spec?.characteristics ?? []).filter((c) => c.mode === 'purchased').map((c) => c.characteristic_code),
    );

    return build.characteristicPurchases.filter((purchase) => purchasedCodes.has(purchase.characteristicCode));
  }

  /**
   * Кардинальность группы (правило type 'group' + group_code участников):
   * - selectLimit 1: при взятии способности снимает остальные взятые способности той же группы;
   * - selectLimit N>1: при полной группе добавление отклоняется (build остаётся без изменений);
   * - selectLimit -1/0: без ограничений.
   */
  private applyGroupSelectLimit(
    abilities: CharacterBuild['abilities'],
    ruleId: string,
    level: number,
    rules: Rule[],
    previous: CharacterBuild['abilities'],
  ): CharacterBuild['abilities'] {
    if (level < 1) return abilities;

    const rule = rules.find((entry) => entry.id === ruleId);
    const memberSpec = rule?.type === 'ability' ? (rule.spec as { group_code?: string | null } | undefined) : undefined;
    const groupCode = memberSpec?.group_code;
    if (!groupCode) return abilities;

    const groupRule = rules.find((entry) => entry.type === 'ability' && entry.code === groupCode);
    const groupSpec =
      groupRule?.type === 'ability' ? (groupRule.spec as { selectLimit?: number } | undefined) : undefined;
    const selectLimit = groupSpec?.selectLimit ?? -1;
    if (selectLimit === -1 || selectLimit === 0) return abilities;

    const groupRuleIds = new Set(
      rules
        .filter((entry) => {
          if (entry.type !== 'ability') return false;
          const candidate = (entry.spec as { group_code?: string | null } | undefined)?.group_code;

          return candidate === groupCode;
        })
        .map((entry) => entry.id),
    );

    if (selectLimit === 1) {
      return abilities.filter((ability) => ability.ruleId === ruleId || !groupRuleIds.has(ability.ruleId));
    }

    // Считаем разные правила группы (у множественных навыков несколько экземпляров — один ruleId).
    const takenInGroup = new Set(
      abilities.filter((ability) => ability.ruleId !== ruleId && groupRuleIds.has(ability.ruleId)).map((a) => a.ruleId),
    ).size;
    if (takenInGroup >= selectLimit) return previous;

    return abilities;
  }

  private applyInnateGear(build: CharacterBuild, rules: Rule[]): CharacterBuild {
    const next = racialInnateGearService.applyRacialInnateGear(build, rules);

    return { ...build, inventory: next.inventory, abilities: next.abilities };
  }
}
