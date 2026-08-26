import { ACTION_POINTS_RESOURCE_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/ACTION_POINTS_RESOURCE_CODE';
import { RULE_TYPE_LABELS } from '@/modules/Roleplay/Rule/Constant/RULE_TYPE_LABELS';
import type { AbilitySpecService } from '@/modules/Roleplay/Rule/Service/Spec/AbilitySpecService';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType';
import type { ActionComponent } from '@/modules/Roleplay/Rule/Dto/Ability/ActionComponent';
import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';
import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { ItemModifierSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierSpec';
import type { CheckSpec } from '@/modules/Roleplay/Rule/Dto/Check/CheckSpec';
import { checkResolutionService } from '@/modules/Roleplay/Rule/Service/Instance/checkResolutionService';
import { damageTypeSpecService } from '@/modules/Roleplay/Rule/Service/Instance/damageTypeSpecService';
import type { RaceSpec } from '@/modules/Roleplay/Rule/Dto/Race/RaceSpec';
import type { SpeciesSpec } from '@/modules/Roleplay/Rule/Dto/Race/SpeciesSpec';
import type { CharacteristicSpec } from '@/modules/Roleplay/Rule/Dto/CharacteristicSpec';
import type { ResourceSpec } from '@/modules/Roleplay/Rule/Dto/ResourceSpec';
import type { StateSpec } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
import type { PoisonSpec } from '@/modules/Roleplay/Rule/Dto/Poison/PoisonSpec';
import type { AgeSpec } from '@/modules/Roleplay/Rule/Dto/Age/AgeSpec';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { CatalogValidationResult } from '@/modules/Roleplay/Rule/Dto/CatalogValidationResult';
import type { ReferenceTargetType } from '@/modules/Roleplay/Rule/Dto/ReferenceTargetType';
import type { ReferenceError } from '@/modules/Roleplay/Rule/Dto/ReferenceError';
import type { AbilityStructureError } from '@/modules/Roleplay/Rule/Dto/AbilityStructureError';
import type { RaceStructureError } from '@/modules/Roleplay/Rule/Dto/RaceStructureError';
import type { RefExpectation } from '@/modules/Roleplay/Rule/Dto/RefExpectation';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

export class RuleValidationService {
  constructor(private readonly abilitySpec: AbilitySpecService) {}

  expectedTypeLabel(type: ReferenceTargetType): string {
    if (type === 'keyword') return 'Признак';

    return RULE_TYPE_LABELS[type];
  }

  /**
   * Тот же набор проверок, что публикация: ссылки, код, структура спек, цикл видов.
   */
  validateCatalog(effective: Rule[], keywords: Keyword[]): CatalogValidationResult {
    const items = [
      ...this.validateRuleReferences(
        effective,
        keywords.map((keyword) => ({ code: keyword.code, name: keyword.name })),
      ).map((error) => ({
        ruleCode: error.ruleCode,
        ruleName: error.ruleName,
        message: this.formatReferenceError(error),
      })),
      ...this.validateRuleCodeFormat(effective),
      ...this.validateAbilityStructure(effective, keywords),
      ...this.validateRaceStructure(effective),
      ...this.validateSpeciesStructure(effective),
      ...this.validateItemModifierStructure(effective),
      ...this.validateCheckStructure(effective),
      ...this.validateDamageTypeStructure(effective),
      ...this.validateAgeStructure(effective),
    ];
    const cycle = this.findSpeciesCycle(effective);

    return {
      items,
      spaceErrors: cycle ? [this.formatSpeciesCycle(cycle)] : [],
    };
  }

  blockingMessagesForRule(result: CatalogValidationResult, ruleCode: string): string[] {
    return [
      ...result.items.filter((item) => item.ruleCode === ruleCode).map((item) => item.message),
      ...result.spaceErrors,
    ];
  }

  /**
   * Проверяет, что все строковые ссылки (*_code) в правилах указывают на существующие
   * правила нужного типа. Возвращает массив ошибок (пустой = валидно).
   */
  validateRuleReferences(rules: Rule[], keywords: { code: string; name: string }[]): ReferenceError[] {
    const byCode = new Map<string, Rule>();
    for (const rule of rules) byCode.set(rule.code, rule);
    const keywordCodes = new Set(keywords.map((t) => t.code));

    const errors: ReferenceError[] = [];
    const refs: { rule: Rule; ref: RefExpectation }[] = [];

    for (const rule of rules) {
      this.collectSpecRefs(rule, (ref) => {
        refs.push({ rule, ref });
      });
    }

    for (const { rule, ref } of refs) {
      if (ref.type === 'keyword') {
        if (!keywordCodes.has(ref.code)) {
          errors.push({ ruleName: rule.name, ruleCode: rule.code, refCode: ref.code, expectedType: ref.type });
        }
        continue;
      }
      const target = byCode.get(ref.code);
      if (!target) {
        errors.push({ ruleName: rule.name, ruleCode: rule.code, refCode: ref.code, expectedType: ref.type });
        continue;
      }
      if (target.type !== ref.type) {
        errors.push({ ruleName: rule.name, ruleCode: rule.code, refCode: ref.code, expectedType: ref.type });
      }
    }

    return errors;
  }

  formatReferenceError(err: ReferenceError): string {
    return `${err.ruleName} → ссылка на "${err.refCode}" (нужен тип «${this.expectedTypeLabel(err.expectedType)}»)`;
  }

  /**
   * Проверяет формат кода правила: латиница, `-`, `_`, цифры 0-9 (ТР §3 — глобальный
   * семантический ключ; кириллица и пробелы недопустимы).
   */
  validateRuleCodeFormat(rules: Rule[]): { ruleCode: string; ruleName: string; message: string }[] {
    const errors: { ruleCode: string; ruleName: string; message: string }[] = [];
    for (const rule of rules) {
      if (!/^[a-z0-9_-]+$/.test(rule.code)) {
        errors.push({
          ruleCode: rule.code,
          ruleName: rule.name,
          message: 'код должен состоять из латиницы, цифр 0-9, символов «-» и «_»',
        });
      }
    }

    return errors;
  }

  /**
   * Структурная валидация способностей по типу: обязательная ОД-стоимость,
   * шаги/переходы процесса, сложность и компоненты заклинания.
   */
  validateAbilityStructure(
    rules: Rule[],
    keywords: { id: number; code: string; name: string }[],
  ): AbilityStructureError[] {
    const errors: AbilityStructureError[] = [];

    for (const rule of rules) {
      if (rule.type !== 'ability') continue;
      const spec = rule.spec as AbilitySpec | undefined;
      if (!spec) {
        errors.push({
          ruleName: rule.name,
          ruleCode: rule.code,
          message: 'способность должна содержать спеку',
        });
        continue;
      }
      const type = this.abilityTypeFromRule(rule, keywords);
      if (!type) {
        errors.push({
          ruleName: rule.name,
          ruleCode: rule.code,
          message: 'у способности должен быть выбран тип',
        });
        continue;
      }

      const components = 'action_components' in spec ? spec.action_components : [];

      if (type === 'action' || type === 'spell') {
        const costs = components.filter(
          (c): c is Extract<ActionComponent, { type: 'resource' }> => c.type === 'resource',
        );
        if (!this.hasActionPointCost(costs)) {
          errors.push({
            ruleName: rule.name,
            ruleCode: rule.code,
            message: 'действие требует минимум 1 ОД (стоимость в «Очки Действий» ≥ 1)',
          });
        }
      }

      if (type === 'process') {
        const steps = 'process' in spec ? (spec.process?.steps ?? []) : [];
        if (steps.length < 2) {
          errors.push({
            ruleName: rule.name,
            ruleCode: rule.code,
            message: 'процесс должен содержать минимум 2 шага',
          });
        }
        const stepCodes = new Set(steps.filter((s) => s.code).map((s) => s.code));
        for (const step of steps) {
          if (!this.hasActionPointCost(step.costs ?? [])) {
            errors.push({
              ruleName: rule.name,
              ruleCode: rule.code,
              message: `шаг «${step.name || step.code}» требует минимум 1 ОД`,
            });
          }
        }
        const startStep = 'process' in spec ? spec.process?.start_step_code : undefined;
        if (startStep && !stepCodes.has(startStep)) {
          errors.push({
            ruleName: rule.name,
            ruleCode: rule.code,
            message: `начальный шаг «${startStep}» не существует`,
          });
        }
        const transition = 'process' in spec ? spec.process?.transition : undefined;
        if (transition?.mode === 'custom') {
          for (const edge of transition.edges ?? []) {
            if (edge.from && !stepCodes.has(edge.from)) {
              errors.push({
                ruleName: rule.name,
                ruleCode: rule.code,
                message: `переход: шаг «${edge.from}» не существует`,
              });
            }
            if (edge.to && !stepCodes.has(edge.to)) {
              errors.push({
                ruleName: rule.name,
                ruleCode: rule.code,
                message: `переход: шаг «${edge.to}» не существует`,
              });
            }
          }
        }
      }

      if (type === 'spell') {
        const spell = 'spell' in spec ? spec.spell : undefined;
        if (!spell?.difficulty) {
          errors.push({
            ruleName: rule.name,
            ruleCode: rule.code,
            message: 'заклинание требует сложность сотворения',
          });
        }
      }

      for (const component of components) {
        if (component.type !== 'material') continue;
        const hasItem = !!component.item_code;
        const hasTags = !!component.keyword_codes?.length;
        if (hasItem === hasTags) {
          errors.push({
            ruleName: rule.name,
            ruleCode: rule.code,
            message: 'материальный компонент должен указывать предмет или набор тегов',
          });
        }
        if (hasItem && component.item_code) {
          const exists = rules.some((r) => r.code === component.item_code && r.type === 'item');
          if (!exists) {
            errors.push({
              ruleName: rule.name,
              ruleCode: rule.code,
              message: `материальный компонент ссылается на отсутствующий предмет «${component.item_code}»`,
            });
          }
        }
      }
    }

    return errors;
  }

  /** Цикл наследования и обязательные поля проверки. */
  validateCheckStructure(rules: Rule[]): { ruleCode: string; ruleName: string; message: string }[] {
    const errors: { ruleCode: string; ruleName: string; message: string }[] = [];
    const byCode = new Map(rules.filter((rule) => rule.type === 'check').map((rule) => [rule.code, rule]));

    for (const rule of rules) {
      if (rule.type !== 'check') continue;
      const spec = checkResolutionService.asCheckSpec(rule);
      if (!spec) {
        errors.push({
          ruleCode: rule.code,
          ruleName: rule.name,
          message: 'проверка должна содержать спеку type check',
        });
        continue;
      }
      if (spec.difficulty_input.kind === 'from_state' && !spec.difficulty_input.state_code?.trim()) {
        errors.push({
          ruleCode: rule.code,
          ruleName: rule.name,
          message: 'сложность из состояния требует state_code',
        });
      }
      if (spec.attached_rule_codes) {
        const byAnyCode = new Map(rules.map((entry) => [entry.code, entry]));
        for (const code of spec.attached_rule_codes) {
          const attached = byAnyCode.get(code);
          if (!attached) {
            errors.push({
              ruleCode: rule.code,
              ruleName: rule.name,
              message: `правило броска «${code}» не найдено`,
            });
            continue;
          }
          if (attached.type === 'check' || attached.mechanicId == null) {
            errors.push({
              ruleCode: rule.code,
              ruleName: rule.name,
              message: `«${code}» нельзя повесить на бросок проверки`,
            });
          }
        }
      }
      const seen = new Set<string>([rule.code]);
      let parentCode = spec.parent_check_code ?? '';
      while (parentCode) {
        if (seen.has(parentCode)) {
          errors.push({
            ruleCode: rule.code,
            ruleName: rule.name,
            message: 'цикл в наследовании проверки',
          });
          break;
        }
        seen.add(parentCode);
        const parent = byCode.get(parentCode);
        parentCode = checkResolutionService.asCheckSpec(parent)?.parent_check_code ?? '';
      }
    }

    return errors;
  }

  /** Карточки на типе урона существуют и несут механику. */
  validateDamageTypeStructure(rules: Rule[]): { ruleCode: string; ruleName: string; message: string }[] {
    const errors: { ruleCode: string; ruleName: string; message: string }[] = [];
    const byCode = new Map(rules.map((rule) => [rule.code, rule]));

    for (const rule of rules) {
      if (rule.type !== 'damage_type') continue;
      const spec = damageTypeSpecService.asDamageTypeSpec(rule);
      if (!spec) {
        errors.push({
          ruleCode: rule.code,
          ruleName: rule.name,
          message: 'тип урона должен содержать спеку type damage_type',
        });
        continue;
      }
      if (!spec.forms.genitive.trim() || !spec.forms.dative.trim()) {
        errors.push({
          ruleCode: rule.code,
          ruleName: rule.name,
          message: 'у типа урона должны быть заполнены родительный и дательный',
        });
      }
      for (const code of spec.attached_rule_codes) {
        const attached = byCode.get(code);
        if (!attached) {
          errors.push({
            ruleCode: rule.code,
            ruleName: rule.name,
            message: `хук «${code}» не найден`,
          });
          continue;
        }
        if (attached.type === 'damage_type' || attached.type === 'check' || attached.mechanicId == null) {
          errors.push({
            ruleCode: rule.code,
            ruleName: rule.name,
            message: `«${code}» нельзя повесить на тип урона`,
          });
        }
      }
    }

    return errors;
  }

  validateAgeStructure(rules: Rule[]): { ruleCode: string; ruleName: string; message: string }[] {
    const errors: { ruleCode: string; ruleName: string; message: string }[] = [];

    for (const rule of rules) {
      if (rule.type !== 'age') continue;
      const spec = rule.spec as AgeSpec | undefined;
      if (!spec || spec.type !== 'age') {
        errors.push({
          ruleCode: rule.code,
          ruleName: rule.name,
          message: 'возраст должен содержать спеку type age',
        });
        continue;
      }
      const ages = spec.ages ?? [];
      if (ages.length < 1) {
        errors.push({
          ruleCode: rule.code,
          ruleName: rule.name,
          message: 'возраст должен содержать хотя бы одну ступень',
        });
        continue;
      }
      const names = new Set<string>();
      for (const stage of ages) {
        const name = stage.name?.trim() ?? '';
        if (!name) {
          errors.push({
            ruleCode: rule.code,
            ruleName: rule.name,
            message: 'у возрастной ступени должно быть имя',
          });
          continue;
        }
        if (names.has(name)) {
          errors.push({
            ruleCode: rule.code,
            ruleName: rule.name,
            message: `ступень «${name}» указана несколько раз`,
          });
        }
        names.add(name);
        for (const effect of stage.effects ?? []) {
          if (!effect.characteristic_code) {
            errors.push({
              ruleCode: rule.code,
              ruleName: rule.name,
              message: `ступень «${name}»: у эффекта не указана характеристика`,
            });
          }
        }
      }
    }

    return errors;
  }

  /** Структурная валидация модификатора предмета: цена и применимость консистентны. */
  validateItemModifierStructure(rules: Rule[]): { ruleCode: string; ruleName: string; message: string }[] {
    const errors: { ruleCode: string; ruleName: string; message: string }[] = [];

    for (const rule of rules) {
      if (rule.type !== 'item_modifier') continue;
      const spec = rule.spec as ItemModifierSpec | undefined;
      if (!spec) continue;

      if (!spec.type_code?.trim()) {
        errors.push({
          ruleCode: rule.code,
          ruleName: rule.name,
          message: 'модификатор должен ссылаться на тип (type_code)',
        });
      }

      const price = spec.price;
      const hasPrice =
        (price?.factor ?? null) !== null ||
        (price?.add_gm ?? null) !== null ||
        (price?.add_gm_per_100g ?? null) !== null ||
        (price?.min_final_gm ?? null) !== null;
      const hasEffect = (spec.effects ?? []).some((effect) => effect.text.trim().length > 0);
      if (!hasPrice && !hasEffect) {
        errors.push({
          ruleCode: rule.code,
          ruleName: rule.name,
          message: 'модификатор должен задавать влияние на цену или хотя бы один эффект',
        });
      }

      const applies = spec.applies;
      if (!applies) continue;
      const sets =
        (applies.keyword_all ?? []).length + (applies.keyword_any ?? []).length + (applies.keyword_none ?? []).length;
      if (sets === 0) continue;

      const noneSet = new Set(applies.keyword_none ?? []);
      for (const code of applies.keyword_all ?? []) {
        if (noneSet.has(code)) {
          errors.push({
            ruleCode: rule.code,
            ruleName: rule.name,
            message: `признак «${code}» одновременно в требуемых и запрещённых`,
          });
        }
      }
    }

    return errors;
  }

  /** Структурная валидация рас: стоимость, пустые/дубли коды, уровни закупки. */
  validateRaceStructure(rules: Rule[]): RaceStructureError[] {
    const errors: RaceStructureError[] = [];

    for (const rule of rules) {
      if (rule.type !== 'race') continue;
      const spec = rule.spec as RaceSpec | undefined;
      if (!spec) continue;

      if (typeof spec.cost_os !== 'number' || !Number.isInteger(spec.cost_os)) {
        errors.push({
          ruleName: rule.name,
          ruleCode: rule.code,
          message: 'стоимость расы (cost_os) должна быть целым числом',
        });
      }

      const characteristics = spec.characteristics ?? [];
      for (const code of this.duplicateCodes(characteristics.map((c) => c.characteristic_code))) {
        errors.push({
          ruleName: rule.name,
          ruleCode: rule.code,
          message: `характеристика «${code}» указана несколько раз`,
        });
      }
      for (const c of characteristics) {
        const label = c.characteristic_code || 'без кода';
        if (!c.characteristic_code) {
          errors.push({
            ruleName: rule.name,
            ruleCode: rule.code,
            message: 'у характеристики не указан код',
          });
        }
        if (c.mode === 'purchased') {
          const costs = (c.purchase ?? []).map((l) => l.cost);
          for (const cost of costs) {
            if (cost < 1) {
              errors.push({
                ruleName: rule.name,
                ruleCode: rule.code,
                message: `уровень закупки «${label}»: стоимость должна быть ≥ 1`,
              });
            }
          }
          for (const cost of this.duplicateCodes(costs.map(String))) {
            errors.push({
              ruleName: rule.name,
              ruleCode: rule.code,
              message: `уровень закупки «${label}»: стоимость ${cost} указана несколько раз`,
            });
          }
        }
        if (this.outOfCharacteristicBaseRange(c.base.base)) {
          errors.push({
            ruleName: rule.name,
            ruleCode: rule.code,
            message: `характеристика «${label}»: база вне диапазона (3–5)`,
          });
        }
        for (const level of c.purchase ?? []) {
          if (this.outOfCharacteristicBaseRange(level.value.base)) {
            errors.push({
              ruleName: rule.name,
              ruleCode: rule.code,
              message: `уровень закупки «${label}»: база значения вне диапазона (3–5)`,
            });
          }
        }
      }

      const abilities = spec.abilities ?? [];
      for (const code of this.duplicateAbilityCodes(abilities)) {
        errors.push({
          ruleName: rule.name,
          ruleCode: rule.code,
          message: `способность «${code}» указана несколько раз`,
        });
      }
      for (const a of abilities) {
        if (!a.ability_code) {
          errors.push({
            ruleName: rule.name,
            ruleCode: rule.code,
            message: 'у способности не указан код',
          });
        }
      }
    }

    return errors;
  }

  /** Структурная валидация видов/подвидов: пустые/дубли кодов способностей. */
  validateSpeciesStructure(rules: Rule[]): RaceStructureError[] {
    const errors: RaceStructureError[] = [];

    for (const rule of rules) {
      if (rule.type !== 'species') continue;
      const spec = rule.spec as SpeciesSpec | undefined;
      if (!spec) continue;

      const abilities = spec.abilities ?? [];
      for (const code of this.duplicateAbilityCodes(abilities)) {
        errors.push({
          ruleName: rule.name,
          ruleCode: rule.code,
          message: `способность «${code}» указана несколько раз`,
        });
      }
      for (const a of abilities) {
        if (!a.ability_code) {
          errors.push({
            ruleName: rule.name,
            ruleCode: rule.code,
            message: 'у способности не указан код',
          });
        }
      }
    }

    return errors;
  }

  /**
   * Ищет цикл в цепочке видов (species) через parent_race_code. Возвращает строку цикла
   * вида «a → b → a» или null. Расы не участвуют (родитель расы — всегда species).
   */
  findSpeciesCycle(rules: Rule[]): string | null {
    const byCode = new Map<string, Rule>();
    for (const r of rules) {
      if (r.type === 'species') byCode.set(r.code, r);
    }

    const color = new Map<string, number>(); // 0 white, 1 gray, 2 black

    const visit = (code: string, stack: string[]): string | null => {
      const state = color.get(code) ?? 0;
      if (state === 1) {
        const start = stack.indexOf(code);

        return [...stack.slice(start), code].join(' → ');
      }
      if (state === 2) return null;

      color.set(code, 1);
      stack.push(code);

      const rule = byCode.get(code);
      const parent = (rule?.spec as SpeciesSpec | undefined)?.parent_race_code;
      if (parent && byCode.has(parent)) {
        const cycle = visit(parent, stack);
        if (cycle) return cycle;
      }

      stack.pop();
      color.set(code, 2);

      return null;
    };

    for (const code of byCode.keys()) {
      if ((color.get(code) ?? 0) !== 0) continue;
      const cycle = visit(code, []);
      if (cycle) return cycle;
    }

    return null;
  }

  formatSpeciesCycle(cycle: string): string {
    return `Цикл в цепочке видов: ${cycle}`;
  }

  private abilityTypeFromRule(rule: Rule, keywords: { id: number; code: string }[]): AbilityType | null {
    const spec = rule.spec;
    if (spec && 'type' in spec && spec.type) return spec.type as AbilityType;
    const codes = (rule.keywordIds ?? [])
      .map((id) => keywords.find((t) => t.id === id)?.code)
      .filter((c): c is string => !!c);

    return this.abilitySpec.resolveTypeFromKeywords(codes);
  }

  private collectSpecRefs(rule: Rule, collect: (ref: RefExpectation) => void): void {
    const spec = rule.spec;
    if (!spec || typeof spec !== 'object') return;

    switch (rule.type) {
      case 'item': {
        const item = spec as ItemSpec;
        for (const code of item.special_rule_codes ?? []) {
          collect({ code, type: 'simple' });
        }
        for (const slot of item.weapon?.block_profile?.resistances ?? []) {
          if (slot.source_code) {
            collect({ code: slot.source_code, type: 'source' });
          }
        }
        for (const profile of item.weapon?.weapon_profiles ?? []) {
          this.walkFormula(profile.damage?.formula, 'characteristic', collect);
          if (profile.damage?.damage_type_code) {
            collect({ code: profile.damage.damage_type_code, type: 'damage_type' });
          }
          this.walkFormula(profile.penetration, 'characteristic', collect);
          this.walkFormula(profile.distance, 'characteristic', collect);
          this.walkFormula(profile.range, 'characteristic', collect);
        }
        for (const slot of item.armor?.defense_slots ?? []) {
          if (slot.source_code) {
            collect({ code: slot.source_code, type: 'source' });
          }
        }
        for (const slot of item.armor?.resistance_slots ?? []) {
          if (slot.damage_type_code) {
            collect({ code: slot.damage_type_code, type: 'damage_type' });
          }
          if (slot.source_code) {
            collect({ code: slot.source_code, type: 'source' });
          }
        }
        for (const limit of item.armor?.characteristic_limits ?? []) {
          if (limit.characteristic_code) {
            collect({ code: limit.characteristic_code, type: 'characteristic' });
          }
          this.walkFormula(limit.limit, 'characteristic', collect);
        }
        break;
      }

      case 'item_modifier': {
        const modifier = spec as ItemModifierSpec;
        if (modifier.type_code) {
          collect({ code: modifier.type_code, type: 'item_modifier_type' });
        }
        for (const code of modifier.applies?.keyword_all ?? []) {
          collect({ code, type: 'keyword' });
        }
        for (const code of modifier.applies?.keyword_any ?? []) {
          collect({ code, type: 'keyword' });
        }
        for (const code of modifier.applies?.keyword_none ?? []) {
          collect({ code, type: 'keyword' });
        }
        for (const effect of modifier.effects ?? []) {
          for (const op of effect.ops ?? []) {
            if (op.type !== 'keyword') continue;
            for (const code of [...(op.add ?? []), ...(op.remove ?? [])]) {
              collect({ code, type: 'keyword' });
            }
          }
        }
        break;
      }

      case 'ability': {
        const ability = spec as AbilitySpec;
        // Группа (type 'group') — контейнер: только ссылка на лимит, без зон/требований/даров.
        if (ability.type === 'group') break;
        if (ability.group_code) {
          collect({ code: ability.group_code, type: 'ability' });
        }
        if (ability.parent_ability_code) {
          collect({ code: ability.parent_ability_code, type: 'ability' });
        }
        for (const zone of Object.keys(ability.zones ?? {})) {
          if (zone) collect({ code: zone, type: 'points' });
        }
        for (const entry of ability.requirements ?? []) {
          for (const req of entry.requirements ?? []) {
            this.walkRequirements(req, collect);
          }
        }
        for (const entry of ability.grants ?? []) {
          for (const grant of entry.grants ?? []) {
            this.walkGrant(grant, collect);
          }
        }
        if ('action_components' in ability) {
          for (const component of ability.action_components) {
            if (component.type === 'resource' && component.resource_code) {
              collect({ code: component.resource_code, type: 'resource' });
            }
            if (component.type === 'material') {
              if (component.item_code) {
                collect({ code: component.item_code, type: 'item' });
              }
              for (const tag of component.keyword_codes ?? []) {
                collect({ code: tag, type: 'keyword' });
              }
            }
          }
        }
        if ('process' in ability) {
          for (const step of ability.process?.steps ?? []) {
            for (const cost of step.costs ?? []) {
              if (cost.resource_code) {
                collect({ code: cost.resource_code, type: 'resource' });
              }
            }
          }
        }
        break;
      }

      case 'characteristic': {
        const charSpec = spec as CharacteristicSpec;
        // formula в виде строки "min(memory, reasoning)" — проверяем упомянутые коды
        if (typeof charSpec.formula === 'string') {
          const refs = charSpec.formula.match(/[a-zа-яё][a-zа-яё0-9-]*/gi) ?? [];
          for (const ref of refs) {
            if (ref === 'min' || ref === 'max') continue;
            collect({ code: ref, type: 'characteristic' });
          }
        }
        if (charSpec.base_from?.characteristic_code) {
          collect({ code: charSpec.base_from.characteristic_code, type: 'characteristic' });
        }
        break;
      }

      case 'race': {
        const race = spec as RaceSpec;
        if (race.parent_race_code) {
          collect({ code: race.parent_race_code, type: 'species' });
        }
        for (const c of race.characteristics ?? []) {
          if (c?.characteristic_code) {
            collect({ code: c.characteristic_code, type: 'characteristic' });
          }
        }
        for (const ref of race.abilities ?? []) {
          if (ref?.ability_code) {
            collect({ code: ref.ability_code, type: 'ability' });
          }
        }
        break;
      }

      case 'species': {
        const species = spec as SpeciesSpec;
        if (species.parent_race_code) {
          collect({ code: species.parent_race_code, type: 'species' });
        }
        for (const ref of species.abilities ?? []) {
          if (ref?.ability_code) {
            collect({ code: ref.ability_code, type: 'ability' });
          }
        }
        break;
      }

      case 'state': {
        const state = spec as StateSpec;
        for (const effect of state.effects ?? []) {
          if (effect.type === 'characteristic_modify' && effect.characteristic_code) {
            collect({ code: effect.characteristic_code, type: 'characteristic' });
          }
          if (
            (effect.type === 'resource_limit_modify' || effect.type === 'resource_limit_set') &&
            effect.resource_code
          ) {
            collect({ code: effect.resource_code, type: 'resource' });
          }
          if (effect.type === 'check_advantage') {
            for (const code of effect.characteristic_codes ?? []) {
              collect({ code, type: 'characteristic' });
            }
          }
          if (effect.type === 'damage_over_time') {
            const decay = effect.decay;
            if (decay && (decay.kind === 'characteristic' || decay.kind === 'check') && decay.characteristic_code) {
              collect({ code: decay.characteristic_code, type: 'characteristic' });
            }
          }
        }
        break;
      }

      case 'poison': {
        const poison = spec as PoisonSpec;
        if (poison.damage_type_code) {
          collect({ code: poison.damage_type_code, type: 'damage_type' });
        }
        const decay = poison.default_decay;
        if (decay && (decay.kind === 'characteristic' || decay.kind === 'check') && decay.characteristic_code) {
          collect({ code: decay.characteristic_code, type: 'characteristic' });
        }
        break;
      }

      case 'resource': {
        const resource = spec as ResourceSpec;
        for (const adjustment of resource.limit?.adjustments ?? []) {
          this.walkFormula(adjustment.value, 'resource', collect);
          if (adjustment.source_code) {
            collect({ code: adjustment.source_code, type: 'source' });
          }
        }
        break;
      }

      case 'check': {
        const check = spec as CheckSpec;
        if (check.parent_check_code) {
          collect({ code: check.parent_check_code, type: 'check' });
        }
        if (check.characteristic_code) {
          collect({ code: check.characteristic_code, type: 'characteristic' });
        }
        if (check.difficulty_input.kind === 'from_state' && check.difficulty_input.state_code) {
          collect({ code: check.difficulty_input.state_code, type: 'state' });
        }
        break;
      }

      case 'age': {
        const age = spec as AgeSpec;
        for (const stage of age.ages ?? []) {
          for (const effect of stage.effects ?? []) {
            if (effect.characteristic_code) {
              collect({ code: effect.characteristic_code, type: 'characteristic' });
            }
          }
        }
        break;
      }

      default:
        break;
    }
  }

  private walkFormula(
    node: Formula | null | undefined,
    _expected: ReferenceTargetType,
    collect: (ref: RefExpectation) => void,
  ): void {
    if (!node || typeof node !== 'object') return;
    if (node.type === 'characteristic' && node.characteristic_code) {
      collect({ code: node.characteristic_code, type: 'characteristic' });
    }
    if (node.type === 'characteristic_size' && node.characteristic_code) {
      collect({ code: node.characteristic_code, type: 'characteristic' });
    }
    if (node.type === 'characteristic_size_gap') {
      if (node.characteristic_code_from) collect({ code: node.characteristic_code_from, type: 'characteristic' });
      if (node.characteristic_code_to) collect({ code: node.characteristic_code_to, type: 'characteristic' });
    }
    if (node.type === 'ability_level' && node.ability_code) {
      collect({ code: node.ability_code, type: 'ability' });
    }
  }

  private walkRequirements(node: Requirement | undefined, collect: (ref: RefExpectation) => void): void {
    if (!node || typeof node !== 'object') return;
    if (node.type === 'and' || node.type === 'or') {
      if (Array.isArray(node.children)) {
        for (const child of node.children) this.walkRequirements(child, collect);
      }

      return;
    }
    if (node.type === 'has_ability' && node.ability_code) {
      collect({ code: node.ability_code, type: 'ability' });
    }
    if (node.type === 'has_ability_keyword' && node.keyword_code) {
      collect({ code: node.keyword_code, type: 'keyword' });
    }
    if (node.type === 'has_keyword' && node.keyword_code) {
      collect({ code: node.keyword_code, type: 'keyword' });
    }
    if (node.type === 'characteristic_value' && node.characteristic_code) {
      collect({ code: node.characteristic_code, type: 'characteristic' });
    }
    if (node.type === 'resource_limit' && node.resource_code) {
      collect({ code: node.resource_code, type: 'resource' });
    }
  }

  private walkGrant(grant: Grant | undefined, collect: (ref: RefExpectation) => void): void {
    if (!grant || typeof grant !== 'object') return;
    if (grant.type === 'characteristic' && grant.characteristic_code) {
      collect({ code: grant.characteristic_code, type: 'characteristic' });
    }
    if (grant.type === 'characteristic_modify' && grant.characteristic_code) {
      collect({ code: grant.characteristic_code, type: 'characteristic' });
      this.walkFormula(grant.amount, 'characteristic', collect);
      if (grant.source_code) {
        collect({ code: grant.source_code, type: 'source' });
      }
    }
    if (grant.type === 'resource' && grant.resource_code) {
      collect({ code: grant.resource_code, type: 'resource' });
    }
    if (grant.type === 'resource_limit_change' && grant.resource_code) {
      collect({ code: grant.resource_code, type: 'resource' });
      this.walkFormula(grant.amount, 'resource', collect);
      if (grant.source_code) {
        collect({ code: grant.source_code, type: 'source' });
      }
    }
    if (grant.type === 'ability' && grant.ability_code) {
      collect({ code: grant.ability_code, type: 'ability' });
    }
    if (grant.type === 'keyword' && grant.keyword_code) {
      collect({ code: grant.keyword_code, type: 'keyword' });
    }
    if (grant.type === 'item' && grant.item_code) {
      collect({ code: grant.item_code, type: 'item' });
    }
    if (grant.type === 'resistance' && grant.damage_type_code) {
      collect({ code: grant.damage_type_code, type: 'damage_type' });
      if (grant.source_code) {
        collect({ code: grant.source_code, type: 'source' });
      }
    }
    if (grant.type === 'sense_modify' && grant.sense_code) {
      collect({ code: grant.sense_code, type: 'sense' });
      this.walkFormula(grant.amount, 'sense', collect);
      if (grant.source_code) {
        collect({ code: grant.source_code, type: 'source' });
      }
    }
  }

  private amountValue(amount: unknown): number | null {
    if (typeof amount === 'number') return amount;
    if (amount && typeof amount === 'object' && 'base' in amount) {
      const a = amount;

      return typeof a.base === 'number' ? a.base : null;
    }

    return null;
  }

  private hasActionPointCost(costs: { resource_code: string; amount: unknown }[]): boolean {
    return costs.some((c) => c.resource_code === ACTION_POINTS_RESOURCE_CODE && (this.amountValue(c.amount) ?? 0) >= 1);
  }

  private duplicateCodes(codes: (string | null | undefined)[]): string[] {
    const seen = new Set<string>();
    const dups = new Set<string>();
    for (const c of codes) {
      if (!c) continue;
      if (seen.has(c)) dups.add(c);
      seen.add(c);
    }

    return Array.from(dups);
  }

  /**
   * Дубли кодов способностей расы/вида, игнорируя валидный паттерн «бесплатно + доступна
   * покупка»: две записи одного ability_code, различающиеся по automatic (одна true, одна false).
   * Настоящий дубль (одна и та же семантика) остаётся ошибкой.
   */
  private duplicateAbilityCodes(abilities: { ability_code?: string | null; automatic?: boolean }[]): string[] {
    const byCode = new Map<string, boolean[]>();
    for (const a of abilities) {
      if (!a.ability_code) continue;
      const list = byCode.get(a.ability_code) ?? [];
      list.push(a.automatic === true);
      byCode.set(a.ability_code, list);
    }

    const dups: string[] = [];
    for (const [code, flags] of byCode) {
      if (flags.length < 2) continue;
      const hasAuto = flags.some((f) => f);
      const hasManual = flags.some((f) => !f);
      // Валидный паттерн «бесплатно + дозакупка»: одна automatic, другая нет.
      if (hasAuto && hasManual && flags.length === 2) continue;
      dups.push(code);
    }

    return dups;
  }

  private outOfCharacteristicBaseRange(base: number): boolean {
    return !Number.isInteger(base) || base < 3 || base > 5;
  }
}
