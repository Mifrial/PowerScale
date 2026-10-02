import { describe, expect, it } from 'vitest';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CHECK_SIMPLE_CODE, CHECK_SPELL_CAST_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import { spellCastExecutionService } from '@/modules/Roleplay/Game/Service/Instance/spellCastExecutionService';
import type { SpellCastExecutionInput } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastExecutionInput';
import type { SpellCastRollOutcome } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastRollOutcome';
import type { HitResolution } from '@/modules/Roleplay/Rule/Dto/Ability/HitResolution';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';

const ACTION_POOL: Rule = {
  id: null,
  code: 'action-points',
  type: 'resource',
  name: 'Очки действий',
  description: '',
  spaceId: 1,
  mechanics: [],
  createdAt: 1,
  spec: { is_dimensional: false, auto_add: true },
};

const ROLL_RULES: Rule[] = [
  ACTION_POOL,
  {
    id: null,
    code: 'roll',
    type: 'simple',
    name: 'Бросок',
    description: '',
    spaceId: 1,
    mechanics: [{ mechanicId: 5, mechanicPayload: { type: 'roll', data: { efficiency: 3, sub_mechanics: ['advantage_disadvantage'] } } }],
    createdAt: 1,
  },
  {
    mechanics: [],
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
    mechanics: [],
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
    mechanics: [],
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
  mechanics: [],
  id: null,
  code: 'simple-touch',
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
    spell_touch: { weapon_damage: false, attack_sr_bonus: 1 },
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
  mechanics: [],
  spec: {
    type: 'damage_type',
    forms: { genitive: '', dative: '' },
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
    castCheckCode: CHECK_SPELL_CAST_CODE,
    characteristicValue: { base: 4, size: 0 },
    characteristicName: 'Сила воли',
    parameterPower: { base: 4, size: 0 },
    keywords: KEYWORDS,
    mechanics: MECHANICS,
    rng: () => 0.5,
    touchActionCode: 'simple-touch',
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
        rules: [ACTION_POOL, cheap, HEAVY_STRIKE],
        casterAbilities: [],
        pathCode: null,
        appliedUpgradeCodes: [],
      }),
    ).toBe(5);
    expect(
      spellCastExecutionService.actionPointCost({
        spellCode: 'discharge',
        touchActionCode: 'simple-touch',
        rules: [ACTION_POOL, discharge, SIMPLE_TOUCH],
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

  function passedCast(rating: number, passed = true): SpellCastRollOutcome {
    return {
      difficulty: { base: 3, size: 0 },
      needsCheck: true,
      roll: {
        spec: { diceCount: 1, dieSize: 0, dieFaces: 6, efficiency: 3, advantages: [] },
        rolls: [6],
        successes: [],
        adjustedRolls: [6],
        droppedRolls: [],
        totalSuccesses: rating,
        check: { check_code: CHECK_SPELL_CAST_CODE, difficulty: { base: 3, size: 0 }, passed, rating },
      },
    };
  }

  const SATURATION: Rule = {
    mechanics: [],
    id: null,
    code: 'dynamic-energy-saturation',
    type: 'ability',
    name: 'Динамическое энергонасыщение',
    description: '',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {},
      requirements: [],
      grants: [],
      parent_ability_code: null,
      spell_saturation: { min_rating: 2, rating_per_step: 2, power_per_step: 1 },
    },
    createdAt: 1,
  };
  const TRANSFER: Rule = {
    mechanics: [],
    id: null,
    code: 'interstructure-energy-transfer',
    type: 'ability',
    name: 'Межструктурные энергопереходы',
    description: '',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {},
      requirements: [],
      grants: [],
      parent_ability_code: null,
      next_cast_difficulty: { min_remaining_rating: 2, delta: -1, source_code: 'training' },
    },
    createdAt: 1,
  };
  const BARE_SATURATION: Rule = {
    ...SATURATION,
    code: 'bare-saturation',
    spec: {
      type: 'skill',
      zones: {},
      requirements: [],
      grants: [],
      parent_ability_code: null,
    },
  };

  function saturatedInput(
    steps: number,
    abilities: { ruleCode: string; level: number; zone: 'or' }[],
    extraRules: Rule[] = [SATURATION, TRANSFER],
  ) {
    return baseInput({
      spellCode: 'discharge',
      saturationSteps: steps,
      casterAbilities: abilities,
      touchTargetKey: 'character:2',
      touchTargetOverview: OVERVIEW,
      effectTargetOverview: OVERVIEW,
      rules: [...ROLL_RULES, discharge, SIMPLE_TOUCH, ELECTRICITY, ...extraRules],
    });
  }

  const saturationAbilities = [
    { ruleCode: 'dynamic-energy-saturation', level: 1, zone: 'or' as const },
    { ruleCode: 'interstructure-energy-transfer', level: 1, zone: 'or' as const },
  ];

  it('4 РУ и один шаг сдвигают мощь и оставляют порог энергоперехода', () => {
    const result = spellCastExecutionService.completeAfterHit(
      saturatedInput(1, saturationAbilities),
      { milk: false, attackSr: 1 },
      passedCast(4),
    );
    expect(result.started).toBe(true);
    expect(result.spellDamage).toEqual({ base: 5, size: 1 });
    expect(result.pendingEffectsAfterCast).toEqual([
      expect.objectContaining({ sourceRuleCode: 'interstructure-energy-transfer' }),
    ]);
  });

  it('4 РУ и два шага не оставляют порог энергоперехода', () => {
    const result = spellCastExecutionService.completeAfterHit(
      saturatedInput(2, saturationAbilities),
      { milk: false, attackSr: 1 },
      passedCast(4),
    );
    expect(result.spellDamage).toEqual({ base: 3, size: 2 });
    expect(result.pendingEffectsAfterCast).toEqual([]);
  });

  it('шаг сверх РУ не наносит урон', () => {
    const result = spellCastExecutionService.completeAfterHit(
      saturatedInput(3, saturationAbilities),
      { milk: false, attackSr: 1 },
      passedCast(4),
    );
    expect(result.started).toBe(false);
    expect(result.refuseReason).toBe('invalid_saturation');
    expect(result.spellApply).toBeNull();
  });

  it('провал проверки не принимает насыщение', () => {
    const result = spellCastExecutionService.completeAfterHit(
      saturatedInput(1, saturationAbilities),
      { milk: false, attackSr: 1 },
      passedCast(4, false),
    );
    expect(result.refuseReason).toBe('invalid_saturation');
    expect(result.spellApply).toBeNull();
  });

  it('способность без поля насыщения отвергается', () => {
    const result = spellCastExecutionService.completeAfterHit(
      saturatedInput(1, [{ ruleCode: 'bare-saturation', level: 1, zone: 'or' }], [BARE_SATURATION]),
      { milk: false, attackSr: 1 },
      passedCast(4),
    );
    expect(result.refuseReason).toBe('invalid_saturation');
    expect(result.pendingEffectsAfterCast ?? []).toEqual([]);
  });

  it('без способности насыщение отвергается', () => {
    const result = spellCastExecutionService.completeAfterHit(
      saturatedInput(1, [{ ruleCode: 'discharge', level: 1, zone: 'or' }]),
      { milk: false, attackSr: 1 },
      passedCast(4),
    );
    expect(result.refuseReason).toBe('invalid_saturation');
    expect(result.spellApply).toBeNull();
  });
});
