import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { AbilitySpecDraft } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecDraft';
import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType';
import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { ActionComponent } from '@/modules/Roleplay/Rule/Dto/Ability/ActionComponent';
import type { SpellDuration } from '@/modules/Roleplay/Rule/Dto/Ability/SpellDuration';
import type { ResourceRef } from '@/modules/Roleplay/Rule/Dto/Ability/ResourceRef';
import { ACTION_POINTS_RESOURCE_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/ACTION_POINTS_RESOURCE_CODE';

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

  /** Признак «часть группы»: добавляет его при заданном group_code, снимает при null/пустом. */
  syncGroupPartTag(
    groupCode: string | null | undefined,
    keywordIds: number[],
    keywords: { id: number; code: string }[],
  ): number[] {
    const part = keywords.find((t) => t.code === 'group-part');
    if (!part) return keywordIds;
    const result = keywordIds.filter((id) => id !== part.id);
    if (groupCode) result.push(part.id);

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
        return { type: 'item', item_code: '' };
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

  createEmptySpellDuration(type: SpellDuration['type']): SpellDuration {
    if (type === 'instant') return { type: 'instant' };

    return { type, difficulty: { base: 3, size: 0 }, action_cost: 0 };
  }
}
