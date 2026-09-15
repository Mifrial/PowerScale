import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { SpellSpec } from '@/modules/Roleplay/Rule/Dto/Ability/SpellSpec';
import type { SpellValue } from '@/modules/Roleplay/Rule/Dto/Ability/SpellValue';
import type { AbilitySpecDraft } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecDraft';
import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType';
import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { ActionComponent } from '@/modules/Roleplay/Rule/Dto/Ability/ActionComponent';
import type { SpellDamage } from '@/modules/Roleplay/Rule/Dto/Ability/SpellDamage';
import type { SpellUpgrade } from '@/modules/Roleplay/Rule/Dto/Ability/SpellUpgrade';
import type { SpellDuration } from '@/modules/Roleplay/Rule/Dto/Ability/SpellDuration';
import type { HitResolution } from '@/modules/Roleplay/Rule/Dto/Ability/HitResolution';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { ResourceRef } from '@/modules/Roleplay/Rule/Dto/Ability/ResourceRef';
import { ACTION_POINTS_RESOURCE_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/ACTION_POINTS_RESOURCE_CODE';
import { GROUP_DOMAIN_KEYWORD_CODES } from '@/modules/Roleplay/Rule/Constant/Ability/GROUP_DOMAIN_KEYWORD_CODES';

export class AbilitySpecService {
  constructor(
    private readonly manifest: Record<AbilityType, readonly (keyof AbilitySpecDraft)[]>,
    private readonly typePrecedence: AbilityType[],
    private readonly distinctiveTag: Record<AbilityType, string>,
    private readonly specificFields: (keyof AbilitySpecDraft)[],
    private readonly typeKeywords: Record<AbilityType, string[]>,
  ) {}

  resolveTypeFromKeywords(keywords: string[]): AbilityType | null {
    const set = new Set(keywords);
    for (const type of this.typePrecedence) {
      if (set.has(this.distinctiveTag[type])) return type;
    }

    return null;
  }

  /**
   * Оставляет в спеке только поля, релевантные типу (по манифесту), и проставляет type.
   * Применяется на границе эмита (specToEmit): при смене типа черновые поля редактора
   * НЕ чистятся, но в сохранённый результат мусор не попадает.
   */
  prune(spec: AbilitySpecDraft, type: AbilityType): AbilitySpec {
    const allowed = new Set<keyof AbilitySpecDraft>(this.manifest[type]);
    const out: AbilitySpecDraft = { ...spec, type };
    for (const key of this.specificFields) {
      if (!allowed.has(key)) {
        delete out[key];
      }
    }

    return out as AbilitySpec;
  }

  updateReqLevel(spec: AbilitySpecDraft, levelIndex: number, value: number): AbilitySpecDraft {
    return {
      ...spec,
      requirements: spec.requirements.map((entry, i) =>
        i === levelIndex ? { ...entry, level: Number(value) || 1 } : entry,
      ),
    };
  }

  updateRequirementLevelRequirements(
    spec: AbilitySpecDraft,
    levelIndex: number,
    reqs: Requirement[],
  ): AbilitySpecDraft {
    return {
      ...spec,
      requirements: spec.requirements.map((entry, i) => (i === levelIndex ? { ...entry, requirements: reqs } : entry)),
    };
  }

  removeRequirementLevel(spec: AbilitySpecDraft, levelIndex: number): AbilitySpecDraft {
    return { ...spec, requirements: spec.requirements.filter((_, i) => i !== levelIndex) };
  }

  addRequirementLevel(spec: AbilitySpecDraft): AbilitySpecDraft {
    return { ...spec, requirements: [...spec.requirements, { level: 1, requirements: [] }] };
  }

  updateGrantLevel(spec: AbilitySpecDraft, levelIndex: number, value: number): AbilitySpecDraft {
    return {
      ...spec,
      grants: spec.grants.map((entry, i) => (i === levelIndex ? { ...entry, level: Number(value) || 1 } : entry)),
    };
  }

  updateGrant(spec: AbilitySpecDraft, levelIndex: number, grantIndex: number, grant: Grant): AbilitySpecDraft {
    const grants = spec.grants.map((entry, i) => {
      if (i !== levelIndex) return entry;
      const levelGrants = entry.grants.map((g, j) => (j === grantIndex ? grant : g));

      return { ...entry, grants: levelGrants };
    });

    return { ...spec, grants };
  }

  removeGrant(spec: AbilitySpecDraft, levelIndex: number, grantIndex: number): AbilitySpecDraft {
    const grants = spec.grants.map((entry, i) => {
      if (i !== levelIndex) return entry;
      const levelGrants = entry.grants.filter((_, j) => j !== grantIndex);

      return { ...entry, grants: levelGrants };
    });

    return { ...spec, grants };
  }

  addGrant(spec: AbilitySpecDraft, levelIndex: number): AbilitySpecDraft {
    const grants = spec.grants.map((entry, i) => {
      if (i !== levelIndex) return entry;
      const levelGrants = [...entry.grants, this.createEmptyGrant('keyword')];

      return { ...entry, grants: levelGrants };
    });

    return { ...spec, grants };
  }

  removeGrantLevel(spec: AbilitySpecDraft, levelIndex: number): AbilitySpecDraft {
    return { ...spec, grants: spec.grants.filter((_, i) => i !== levelIndex) };
  }

  addGrantLevel(spec: AbilitySpecDraft): AbilitySpecDraft {
    return { ...spec, grants: [...spec.grants, { level: 1, grants: [] }] };
  }

  ensureActionPointCost(spec: AbilitySpecDraft, isSpell: boolean): AbilitySpecDraft {
    const hasOd = spec.action_components.some(
      (c): c is Extract<ActionComponent, { type: 'resource' }> =>
        c.type === 'resource' && c.resource_code === ACTION_POINTS_RESOURCE_CODE,
    );
    if (hasOd) return spec;

    return {
      ...spec,
      action_components: [
        ...spec.action_components,
        {
          type: 'resource',
          resource_code: ACTION_POINTS_RESOURCE_CODE,
          amount: 1,
          label: isSpell ? 'Сотворение' : undefined,
        },
      ],
    };
  }

  syncTypeTags(type: AbilityType, keywordIds: number[], keywords: { id: number; code: string }[]): number[] {
    const allTypeTagCodes = new Set(Object.values(this.typeKeywords).flat());
    const result = keywordIds.filter((id) => {
      const keyword = keywords.find((t) => t.id === id);

      return keyword && !allTypeTagCodes.has(keyword.code);
    });
    for (const code of this.typeKeywords[type]) {
      const keyword = keywords.find((t) => t.code === code);
      if (keyword && !result.includes(keyword.id)) result.push(keyword.id);
    }

    return result;
  }

  /** Признак группы-домена (Внешность/Голос/Слух/Зрение): ставит keyword по group_code. */
  syncGroupPartTag(
    groupCode: string | null | undefined,
    keywordIds: number[],
    keywords: { id: number; code: string }[],
  ): number[] {
    const domainIds = keywords
      .filter((keyword) => (GROUP_DOMAIN_KEYWORD_CODES as readonly string[]).includes(keyword.code))
      .map((keyword) => keyword.id);
    const result = keywordIds.filter((id) => !domainIds.includes(id));
    if (!groupCode) return result;
    const domain = keywords.find((keyword) => keyword.code === groupCode);
    if (domain && !result.includes(domain.id)) result.push(domain.id);

    return result;
  }

  createEmptyGrant(type: Grant['type'], defaultSourceCode = ''): Grant {
    switch (type) {
      case 'characteristic':
        return { type: 'characteristic', characteristic_code: '', value: { base: 3, size: 0 } };
      case 'characteristic_parameter':
        return {
          type: 'characteristic_parameter',
          characteristic_code: '',
          parameter_code: 'x',
          per_unit: 1,
        };
      case 'characteristic_modify':
        return {
          type: 'characteristic_modify',
          characteristic_code: '',
          amount: { type: 'fixed', value: 1 },
          source_code: defaultSourceCode,
        };
      case 'resource':
        return { type: 'resource', resource_code: '', limit: 0 };
      case 'resource_limit_change':
        return {
          type: 'resource_limit_change',
          resource_code: '',
          amount: { type: 'fixed', value: 1 },
          source_code: defaultSourceCode,
        };
      case 'ability':
        return { type: 'ability', ability_code: '' };
      case 'keyword':
        return { type: 'keyword', keyword_code: '', remove: false };
      case 'item':
        return { type: 'item', item_code: '', quantity: 1 };
      case 'magic_path':
        return { type: 'magic_path', path_code: '' };
      case 'magic_study':
        return { type: 'magic_study', scope: 'spell', max_cost: 2 };
      case 'skill_study':
        return { type: 'skill_study', ability_codes: [], max_level: 1, paid_cost: 0, max_instances: 1 };
      case 'resistance':
        return {
          type: 'resistance',
          damage_type_code: '',
          value: { base: 1, size: 0 },
          source_code: defaultSourceCode,
        };
      case 'sense_modify':
        return {
          type: 'sense_modify',
          sense_code: '',
          amount: { type: 'fixed', value: 1 },
          source_code: defaultSourceCode,
        };
      case 'process_distance_multiplier':
        return { type: 'process_distance_multiplier', ability_code: '', multiplier: 2 };
      case 'state_modify':
        return {
          type: 'state_modify',
          state_code: '',
          amount: { type: 'fixed', value: 1 },
          source_code: defaultSourceCode,
        };
      case 'check_advantage':
        return { type: 'check_advantage', amount: 1, check_codes: [] };
      case 'money':
        return { type: 'money', fixed: 50, percent: 50, apply: 'max' };
    }
  }

  /** Приводит лимит гранта ресурса к форме, соответствующей размерности ресурса. */
  normalizeGrantLimit(grant: Grant, resources: ResourceRef[]): Grant {
    if (grant.type !== 'resource' || !grant.resource_code) return grant;

    const res = resources.find((r) => r.code === grant.resource_code);
    const limit = grant.limit;
    if (res?.isDimensional && typeof limit === 'number') {
      return { ...grant, limit: { base: limit, size: 0 } };
    }
    if (!res?.isDimensional && limit && typeof limit === 'object' && !Array.isArray(limit)) {
      return { ...grant, limit: limit.base };
    }

    return grant;
  }

  createEmptyRequirement(type: Requirement['type']): Requirement {
    switch (type) {
      case 'has_ability':
        return { type: 'has_ability', ability_code: '' };
      case 'has_ability_keyword':
        return { type: 'has_ability_keyword', keyword_code: '', min_count: 1 };
      case 'has_keyword':
        return { type: 'has_keyword', keyword_code: '' };
      case 'min_weapon_mastery':
        return { type: 'min_weapon_mastery', keyword_code: '', min_level: 1 };
      case 'characteristic_value':
        return {
          type: 'characteristic_value',
          characteristic_code: '',
          min: { base: 3, size: 0 },
        };
      case 'resource_limit':
        return { type: 'resource_limit', resource_code: '' };
      case 'has_magic_path':
        return { type: 'has_magic_path', path_code: '' };
      case 'magic_path_experience':
        return { type: 'magic_path_experience', path_code: '', min: 1 };
      case 'current_speed':
        return {
          type: 'current_speed',
          axis: 'horizontal',
          direction: 'front',
          min_steps_per_action_point: 1,
        };
      case 'and':
        return { type: 'and', children: [this.createEmptyRequirement('has_keyword')] };
      case 'or':
        return { type: 'or', children: [this.createEmptyRequirement('has_keyword')] };
    }
  }

  createEmptyActionComponent(type: ActionComponent['type']): ActionComponent {
    if (type === 'material') {
      return {
        type: 'material',
        mode: 'consume',
        item_code: undefined,
        keyword_codes: undefined,
        description: undefined,
      };
    }
    if (type === 'resource') {
      return { type: 'resource', resource_code: '', amount: 0, label: undefined };
    }

    if (type === 'somatic') return { type: 'somatic', note: undefined, occupy_hands: undefined };

    return { type, note: undefined };
  }

  addActionComponent(components: ActionComponent[], type: ActionComponent['type']): ActionComponent[] {
    return [...components, this.createEmptyActionComponent(type)];
  }

  updateActionComponent(components: ActionComponent[], index: number, component: ActionComponent): ActionComponent[] {
    return components.map((c, i) => (i === index ? component : c));
  }

  patchActionComponent(components: ActionComponent[], index: number, key: string, value: unknown): ActionComponent[] {
    return components.map((c, i) => (i === index ? ({ ...c, [key]: value } as ActionComponent) : c));
  }

  removeActionComponent(components: ActionComponent[], index: number): ActionComponent[] {
    const target = components[index];
    if (target?.type === 'resource' && target.resource_code === ACTION_POINTS_RESOURCE_CODE) return components;

    return components.filter((_, i) => i !== index);
  }

  createEmptySpellSpec(): SpellSpec {
    return {
      power: { base: 3, size: 0 },
      control: { base: 3, size: -1 },
      duration: { type: 'instant' },
    };
  }

  createEmptySpellDamage(): SpellDamage {
    return {
      damage_type_code: '',
      experience_keyword_code: '',
      power_modify_steps: [{ min_experience: 0, modify: 3 }],
    };
  }

  setSpellDamage(spell: SpellSpec, damage: SpellDamage | null): SpellSpec {
    if (!damage) {
      return { power: spell.power, control: spell.control, duration: spell.duration };
    }

    return { ...spell, damage };
  }

  createEmptySpellFalloff(): NonNullable<SpellDamage['falloff']> {
    return { free_ipari: 2, size_per_extra_ipari: 1, min: { base: 3, size: -1 } };
  }

  setSpellDamageFalloff(spell: SpellSpec, enabled: boolean): SpellSpec {
    const damage = spell.damage ?? this.createEmptySpellDamage();
    if (!enabled) {
      return this.setSpellDamage(spell, {
        damage_type_code: damage.damage_type_code,
        experience_keyword_code: damage.experience_keyword_code,
        power_modify_steps: damage.power_modify_steps,
      });
    }

    return this.setSpellDamage(spell, { ...damage, falloff: damage.falloff ?? this.createEmptySpellFalloff() });
  }

  patchSpellDamageFalloff(spell: SpellSpec, patch: Partial<NonNullable<SpellDamage['falloff']>>): SpellSpec {
    const damage = spell.damage ?? this.createEmptySpellDamage();
    const falloff = { ...(damage.falloff ?? this.createEmptySpellFalloff()), ...patch };

    return this.setSpellDamage(spell, { ...damage, falloff });
  }

  isSpellValueParameter(value: SpellValue): value is { type: 'parameter'; parameter_code: string } {
    return typeof value === 'object' && 'type' in value && value.type === 'parameter';
  }

  ensureHitResolution(spec: AbilitySpecDraft): AbilitySpecDraft {
    if (spec.hit_resolution) return spec;

    return { ...spec, hit_resolution: { type: 'none' } };
  }

  createEmptySpellDuration(type: SpellDuration['type']): SpellDuration {
    if (type === 'instant') return { type: 'instant' };
    if (type === 'refreshable') return { type: 'refreshable', action_cost: 1 };
    if (type === 'sustained') return { type: 'sustained', power: { type: 'parameter', parameter_code: 'x' } };

    return { type };
  }

  setSpellDurationPower(duration: SpellDuration, power: SpellValue): SpellDuration {
    if (duration.type !== 'sustained') return duration;

    return { ...duration, power };
  }

  withSpellField<K extends keyof SpellSpec>(spell: SpellSpec, key: K, value: SpellSpec[K]): SpellSpec {
    return { ...spell, [key]: value };
  }

  setSpellValueDimensional(fieldDefault: DimensionalNumberValue, value: DimensionalNumberValue | null): SpellValue {
    return value ?? fieldDefault;
  }

  setSpellValueParameter(parameterCode: string): SpellValue {
    return { type: 'parameter', parameter_code: parameterCode };
  }

  createHitResolution(type: HitResolution['type'], rating = 1): HitResolution {
    if (type === 'auto') return { type: 'auto', rating };

    return { type };
  }

  setHitResolutionType(spec: AbilitySpecDraft, type: HitResolution['type']): AbilitySpecDraft {
    return { ...spec, hit_resolution: this.createHitResolution(type) };
  }

  setHitResolutionRating(spec: AbilitySpecDraft, rating: number): AbilitySpecDraft {
    return { ...spec, hit_resolution: { type: 'auto', rating } };
  }

  toggleSpellDurationLimit(duration: SpellDuration, enabled: boolean): SpellDuration {
    if (duration.type === 'instant') return duration;
    const limit = enabled ? { value: 1, unit: 'turn' as const } : undefined;

    return { ...duration, limit };
  }

  patchSpellDurationLimit(duration: SpellDuration, key: 'value' | 'unit', value: unknown): SpellDuration {
    if (duration.type === 'instant' || !duration.limit) return duration;

    return { ...duration, limit: { ...duration.limit, [key]: value } };
  }

  setRefreshableActionCost(duration: SpellDuration, actionCost: number): SpellDuration {
    if (duration.type !== 'refreshable') return duration;

    return { ...duration, action_cost: actionCost };
  }

  createEmptySpellUpgrade(): SpellUpgrade {
    return { action_point_delta: 1 };
  }

  setSpellUpgrade(spec: AbilitySpecDraft, upgrade: SpellUpgrade | null): AbilitySpecDraft {
    if (!upgrade) {
      const next = { ...spec };
      delete next.spell_upgrade;

      return next;
    }

    return { ...spec, spell_upgrade: upgrade };
  }

  patchSpellUpgrade(spec: AbilitySpecDraft, patch: Partial<SpellUpgrade>): AbilitySpecDraft {
    const current = spec.spell_upgrade ?? this.createEmptySpellUpgrade();
    const next: SpellUpgrade = { ...current, ...patch };
    if (!next.check_advantage) {
      delete next.check_advantage;
    }

    return { ...spec, spell_upgrade: next };
  }
}
