import { describe, expect, it } from 'vitest';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CHECK_SIMPLE_CODE, CHECK_SPELL_CAST_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import { spellCastExecutionService } from '@/modules/Roleplay/Game/Service/Instance/spellCastExecutionService';
import { SIMPLE_TOUCH_CODE } from '@/modules/Roleplay/Game/Constant/Combat/SIMPLE_TOUCH_CODE';
import type { SpellCastExecutionInput } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastExecutionInput';
import type { HitResolution } from '@/modules/Roleplay/Rule/Dto/Ability/HitResolution';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';

const ROLL_RULES: Rule[] = [
  {
    id: null,
    code: 'roll',
    type: 'simple',
    name: 'Бросок',
    description: '',
    spaceId: 1,
    mechanicId: 5,
    mechanicPayload: { type: 'roll', data: { efficiency: 3, sub_mechanics: ['advantage_disadvantage'] } },
    createdAt: 1,
  },
  {
    id: null,
    code: CHECK_SIMPLE_CODE,
    type: 'check',
    name: 'Простая проверка',
    description: '',
    spaceId: 1,
    spec: {
      type: 'check',
      difficulty_input: { kind: 'ask' },
      allowed_modes: 'both',
      attached_rule_codes: [],
    },
    createdAt: 1,
  },
  {
    id: null,
    code: CHECK_SPELL_CAST_CODE,
    type: 'check',
    name: 'Проверка на сотворение',
    description: '',
    spaceId: 1,
    spec: {
      type: 'check',
      parent_check_code: CHECK_SIMPLE_CODE,
      difficulty_input: { kind: 'ask' },
      allowed_modes: 'solo',
    },
    createdAt: 1,
  },
];

const MECHANICS: Mechanic[] = [{ id: 5, code: 'roll', name: 'Бросок', description: '', version: '1' }];

const KEYWORDS: Keyword[] = [{ id: 227, code: 'electromancy', name: 'Электромансия', description: '', active: true }];

function spellSpec(hit: HitResolution, od: number): Extract<AbilitySpec, { type: 'spell' }> {
  return {
    type: 'spell',
    zones: { or: { kind: 'array', levels_cost: [1] } },
    requirements: [],
    grants: [],
    parent_ability_code: null,
    action_components: [{ type: 'resource', resource_code: 'action-points', amount: od, label: 'Сотворение' }],
    hit_resolution: hit,
    spell: {
      power: { type: 'parameter', parameter_code: 'x' },
      control: { base: 3, size: -1 },
      duration: { type: 'instant' },
      damage: {
        damage_type_code: 'electricity',
        experience_keyword_code: 'electromancy',
        power_modify_steps: [{ min_experience: 0, modify: 3 }],
      },
    },
  };
}

function spellRule(code: string, spec: Extract<AbilitySpec, { type: 'spell' }>): Rule {
  return {
    id: null,
    code,
    type: 'ability',
    name: code,
    description: '',
    spaceId: 1,
    spec,
    keywordIds: [227],
    createdAt: 1,
  };
}

const SIMPLE_TOUCH: Rule = {
  id: null,
  code: SIMPLE_TOUCH_CODE,
  type: 'ability',
  name: 'Простое касание',
  description: '',
  spaceId: 1,
  spec: {
    type: 'action',
    zones: { or: { kind: 'automatic' } },
    requirements: [],
    grants: [],
    action_components: [{ type: 'resource', resource_code: 'action-points', amount: 3, label: 'Действие' }],
    parent_ability_code: null,
  },
  keywordIds: [14, 71, 1],
  createdAt: 1,
};

const HEAVY_STRIKE: Rule = {
  ...SIMPLE_TOUCH,
  code: 'heavy-strike',
  name: 'Тяжёлый удар',
  spec: {
    type: 'action',
    zones: { or: { kind: 'automatic' } },
    requirements: [],
    grants: [],
    action_components: [{ type: 'resource', resource_code: 'action-points', amount: 5, label: 'Действие' }],
    parent_ability_code: null,
  },
};

const ELECTRICITY: Rule = {
  id: null,
  code: 'electricity',
  type: 'damage_type',
  name: 'Электричество',
  description: '',
  spaceId: 1,
  spec: {
    type: 'damage_type',
    forms: { genitive: '', dative: '' },
    attached_rule_codes: [],
    defense_ignored: true,
    max_success_rating: 3,
  },
  createdAt: 1,
};

const OVERVIEW = {
  characteristics: [],
  combat: { melee: { stat: { value: { base: 3, size: 0 } }, weapons: [] }, ranged: null },
  resources: [],
  abilities: [],
  misc: [],
  inventory: [],
  defense: null,
  attacks: [],
  states: [],
} as unknown as CharacterOverview;

function baseInput(
  partial: Partial<SpellCastExecutionInput> & Pick<SpellCastExecutionInput, 'spellCode' | 'rules'>,
): SpellCastExecutionInput {
  return {
    casterKey: 'character:1',
    casterOverview: OVERVIEW,
    casterAbilities: [{ ruleCode: 'discharge', level: 1, zone: 'or' }],
    currentActionPoints: { base: 4, size: 0 },
    resolve: {
      spellCode: partial.spellCode,
      usedPower: { base: 5, size: 1 },
      availableControl: { base: 5, size: 1 },
      parameterValues: { x: { base: 4, size: 0 } },
      hasTarget: false,
    },
    checkCode: CHECK_SPELL_CAST_CODE,
    characteristicValue: { base: 4, size: 0 },
    characteristicName: 'Сила воли',
    parameterPower: { base: 4, size: 0 },
    keywords: KEYWORDS,
    mechanics: MECHANICS,
    rng: () => 0.5,
    touchActionCode: SIMPLE_TOUCH_CODE,
    touchProfile: {
      itemName: 'Рука',
      profileType: 'strike',
      accuracy: { base: 3, size: 0 },
      reach: 1,
      falloff: { base: 0, size: 0 },
      damage: { base: 3, size: 0 },
      damageTypeCode: 'blunt',
    },
    touchTargetKey: null,
    touchTargetOverview: null,
    effectTargetOverview: null,
    distanceIpari: 0,
    sourceKey: '',
    pathCode: null,
    appliedUpgradeCodes: [],
    ...partial,
  };
}

describe('SpellCastExecutionService', () => {
  const discharge = spellRule('discharge', spellSpec({ type: 'attack' }, 4));
  const bolt = spellRule('lightning-strike', spellSpec({ type: 'auto', rating: 2 }, 4));
  const cheap = spellRule('cheap', { ...spellSpec({ type: 'attack' }, 2) });

  it('не стартует при нехватке ОД', () => {
    const result = spellCastExecutionService.execute(
      baseInput({
        spellCode: 'discharge',
        currentActionPoints: { base: 3, size: 0 },
        rules: [...ROLL_RULES, discharge, SIMPLE_TOUCH, ELECTRICITY],
      }),
    );
    expect(result.started).toBe(false);
    expect(result.refuseReason).toBe('not_enough_ap');
  });

  it('max ОД заклинания и атаки', () => {
    expect(
      spellCastExecutionService.actionPointCost({
        spellCode: 'cheap',
        touchActionCode: 'heavy-strike',
        rules: [cheap, HEAVY_STRIKE],
        casterAbilities: [],
        pathCode: null,
        appliedUpgradeCodes: [],
      }),
    ).toBe(5);
    expect(
      spellCastExecutionService.actionPointCost({
        spellCode: 'discharge',
        touchActionCode: SIMPLE_TOUCH_CODE,
        rules: [discharge, SIMPLE_TOUCH],
        casterAbilities: [],
        pathCode: null,
        appliedUpgradeCodes: [],
      }),
    ).toBe(4);
  });

  it('воздух: ОД списаны, молоко, skip check, без электричества', () => {
    const result = spellCastExecutionService.execute(
      baseInput({
        spellCode: 'discharge',
        rules: [...ROLL_RULES, discharge, SIMPLE_TOUCH, ELECTRICITY],
      }),
    );
    expect(result.started).toBe(true);
    expect(result.spentAp).toBe(4);
    expect(result.remainingActionPoints?.base).toBe(0);
    expect(result.milk).toBe(true);
    expect(result.autoFail).toBe(false);
    expect(result.cast?.needsCheck).toBe(false);
    expect(result.spellApply).toBeNull();
  });

  it('молния без цели — auto-fail после ОД', () => {
    const result = spellCastExecutionService.execute(
      baseInput({
        spellCode: 'lightning-strike',
        touchActionCode: null,
        touchProfile: null,
        rules: [...ROLL_RULES, bolt, ELECTRICITY],
      }),
    );
    expect(result.started).toBe(true);
    expect(result.spentAp).toBe(4);
    expect(result.autoFail).toBe(true);
    expect(result.milk).toBe(false);
    expect(result.spellApply).toBeNull();
  });

  it('касание персонажа не бросается внутри execute', () => {
    const result = spellCastExecutionService.execute(
      baseInput({
        spellCode: 'discharge',
        touchTargetKey: 'character:2',
        touchTargetOverview: OVERVIEW,
        effectTargetOverview: OVERVIEW,
        rules: [...ROLL_RULES, discharge, SIMPLE_TOUCH, ELECTRICITY],
      }),
    );
    expect(result.started).toBe(false);
    expect(result.refuseReason).toBe('needs_hit_offer');
  });

  it('после попадания 0 РУ электричество идёт с 1 РУ', () => {
    const result = spellCastExecutionService.completeAfterHit(
      baseInput({
        spellCode: 'discharge',
        touchTargetKey: 'character:2',
        touchTargetOverview: OVERVIEW,
        effectTargetOverview: OVERVIEW,
        rules: [...ROLL_RULES, discharge, SIMPLE_TOUCH, ELECTRICITY],
      }),
      { milk: false, attackSr: 0 },
    );
    expect(result.milk).toBe(false);
    expect(result.spellSr).toBe(1);
    expect(result.spellApply).not.toBeNull();
    expect(result.weaponApply).toBeNull();
  });
});
