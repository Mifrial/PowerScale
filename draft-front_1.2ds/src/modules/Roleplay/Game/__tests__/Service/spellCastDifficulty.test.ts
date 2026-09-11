import { describe, expect, it } from 'vitest';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CHECK_SIMPLE_CODE, CHECK_SPELL_CAST_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import { asActionAbilitySpec } from '@/modules/Roleplay/Game/Utils/combatActions';
import { spellCastDifficultyService } from '@/modules/Roleplay/Game/Service/Instance/spellCastDifficultyService';
import { spellCastOptionsService } from '@/modules/Roleplay/Game/Service/Instance/spellCastOptionsService';
import { spellCastService } from '@/modules/Roleplay/Game/Service/Instance/spellCastService';
import { SPELL_CAST_SKIP_DIFFICULTY } from '@/modules/Roleplay/Game/Constant/Spell/SPELL_CAST_SKIP_DIFFICULTY';

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
    code: 'check-intellect',
    type: 'check',
    name: 'Проверка на Интеллект',
    description: '',
    spaceId: 1,
    spec: {
      type: 'check',
      parent_check_code: CHECK_SIMPLE_CODE,
      characteristic_code: 'intellect',
      difficulty_input: { kind: 'ask' },
      allowed_modes: 'solo',
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

function spellRule(
  code: string,
  damageType: string,
  power = { base: 4, size: 1 },
  control = { base: 3, size: -1 },
): Rule {
  return {
    id: null,
    code,
    type: 'ability',
    name: code,
    description: '',
    spaceId: 1,
    spec: {
      type: 'spell',
      zones: { or: { kind: 'array', levels_cost: [1] } },
      requirements: [],
      grants: [],
      parent_ability_code: null,
      action_components: [],
      spell: {
        power,
        control,
        duration: { type: 'instant' },
        damage: { damage_type_code: damageType, experience_keyword_code: 'electromancy', power_modify_steps: [] },
      },
    },
    createdAt: 1,
  };
}

const ARCANE: Rule = {
  id: null,
  code: 'arcane',
  type: 'damage_type',
  name: 'Арканный',
  description: '',
  spaceId: 1,
  spec: {
    type: 'damage_type',
    forms: { genitive: '', dative: '' },
    attached_rule_codes: [],
    modifies_spell_difficulty: true,
  },
  createdAt: 1,
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
    modifies_spell_difficulty: false,
  },
  createdAt: 1,
};

describe('SpellCastDifficultyService', () => {
  it('{4|1} vs {3|-1} даёт +7 и сложность {4|2}', () => {
    const result = spellCastDifficultyService.compute({
      requiredPower: { base: 4, size: 1 },
      usedPower: { base: 3, size: -1 },
      requiredControl: { base: 3, size: 0 },
      availableControl: { base: 3, size: 0 },
    });
    expect(result.powerShortage).toBe(7);
    expect(result.needsCheck).toBe(true);
    expect(result.difficulty).toEqual({ base: 4, size: 2 });
  });

  it('одновременное превышение снижает на min(x, y)', () => {
    const result = spellCastDifficultyService.compute({
      requiredPower: { base: 3, size: 0 },
      usedPower: { base: 4, size: 0 },
      requiredControl: { base: 3, size: 0 },
      availableControl: { base: 5, size: 0 },
    });
    expect(result.powerShortage).toBe(-1);
    expect(result.controlShortage).toBe(-2);
    expect(result.difficulty).toEqual({ base: 5, size: -1 });
  });

  it('одностороннее превышение сложность не снижает', () => {
    const result = spellCastDifficultyService.compute({
      requiredPower: { base: 3, size: 0 },
      usedPower: { base: 5, size: 0 },
      requiredControl: { base: 3, size: 0 },
      availableControl: { base: 3, size: 0 },
    });
    expect(result.difficulty).toEqual({ base: 3, size: 0 });
    expect(result.needsCheck).toBe(true);
  });

  it('ниже {3|-1} → skip {0|-1}', () => {
    const result = spellCastDifficultyService.compute({
      requiredPower: { base: 3, size: -1 },
      usedPower: { base: 5, size: 1 },
      requiredControl: { base: 3, size: -1 },
      availableControl: { base: 5, size: 1 },
    });
    expect(result.needsCheck).toBe(false);
    expect(result.difficulty).toEqual(SPELL_CAST_SKIP_DIFFICULTY);
  });

  it('resistance с флагом увеличивает сложность', () => {
    const spell = spellRule('bolt', 'arcane', { base: 3, size: 0 }, { base: 3, size: 0 });
    const without = spellCastDifficultyService.computeForSpell(
      {
        spellCode: 'bolt',
        usedPower: { base: 3, size: 0 },
        availableControl: { base: 3, size: 0 },
        parameterValues: {},
        hasTarget: true,
        targetResistanceAmount: 2,
      },
      [spell, ARCANE],
    );
    expect(without.difficulty).toEqual({ base: 5, size: 0 });
  });

  it('без флага resistance не влияет', () => {
    const spell = spellRule('discharge', 'electricity', { base: 3, size: 0 }, { base: 3, size: 0 });
    const result = spellCastDifficultyService.computeForSpell(
      {
        spellCode: 'discharge',
        usedPower: { base: 3, size: 0 },
        availableControl: { base: 3, size: 0 },
        parameterValues: {},
        hasTarget: true,
        targetResistanceAmount: 9,
      },
      [spell, ELECTRICITY],
    );
    expect(result.difficulty).toEqual({ base: 3, size: 0 });
  });
});

describe('SpellCastService', () => {
  it('skip не зовёт rng', () => {
    const spell = spellRule('easy', 'electricity', { base: 3, size: -1 }, { base: 3, size: -1 });
    const outcome = spellCastService.rollForSpell(
      {
        spellCode: 'easy',
        usedPower: { base: 5, size: 1 },
        availableControl: { base: 5, size: 1 },
        parameterValues: {},
        hasTarget: false,
      },
      'check-intellect',
      { base: 3, size: 0 },
      'Интеллект',
      undefined,
      () => {
        throw new Error('rng must not run');
      },
      [...ROLL_RULES, spell, ELECTRICITY],
      MECHANICS,
    );
    expect(outcome.needsCheck).toBe(false);
    expect(outcome.roll).toBeNull();
  });

  it('живой бросок — проверка на сотворение, не check пути', () => {
    const spell = spellRule('hard', 'electricity', { base: 5, size: 0 }, { base: 3, size: 0 });
    const outcome = spellCastService.rollForSpell(
      {
        spellCode: 'hard',
        usedPower: { base: 3, size: 0 },
        availableControl: { base: 3, size: 0 },
        parameterValues: {},
        hasTarget: false,
      },
      'check-willpower',
      { base: 4, size: 1 },
      'Сила воли',
      undefined,
      () => 0.5,
      [...ROLL_RULES, spell, ELECTRICITY],
      MECHANICS,
    );
    expect(outcome.needsCheck).toBe(true);
    expect(outcome.roll?.check?.check_code).toBe(CHECK_SPELL_CAST_CODE);
    expect(outcome.roll?.check?.check_name).toBe('Проверка на сотворение');
    expect(outcome.roll?.spec.label).toBe('Сила воли');
  });
});

describe('SpellCastOptionsService', () => {
  it('список изученных spell без action', () => {
    const spell = spellRule('discharge', 'electricity');
    const overview = {
      abilities: [
        { ruleCode: 'discharge', type: 'spell', name: 'Разряд' },
        { ruleCode: 'power-strike', type: 'action', name: 'Удар' },
      ],
    } as CharacterOverview;
    const listed = spellCastOptionsService.listOwnedSpells(overview, [spell]);
    expect(listed).toEqual([
      {
        ruleCode: 'discharge',
        name: 'Разряд',
        requiredPower: { base: 4, size: 1 },
        requiredControl: { base: 3, size: -1 },
      },
    ]);
  });

  it('строка проверки называет характеристику и сложность', () => {
    expect(
      spellCastDifficultyService.formatCastCheckLine(true, { base: 3, size: 0 }, 'Интеллект', { base: 4, size: 0 }),
    ).toBe('Проверка на сотворение: Интеллект 4 против 3');
    expect(spellCastDifficultyService.formatSpellRequirement(null)).toBe('х');
    expect(spellCastDifficultyService.formatSpellRequirement({ base: 3, size: -1 })).toBe('3↓');
  });

  it('пути из гранта magic_path', () => {
    const becoming: Rule = {
      id: null,
      code: 'becoming-arcanist',
      type: 'ability',
      name: 'Становление',
      description: '',
      spaceId: 1,
      spec: {
        type: 'skill',
        zones: { or: { kind: 'array', levels_cost: [1] } },
        requirements: [],
        grants: [{ level: 1, grants: [{ type: 'magic_path', path_code: 'arcanist' }] }],
        parent_ability_code: null,
      },
      createdAt: 1,
    };
    const path: Rule = {
      id: null,
      code: 'arcanist',
      type: 'magic_path',
      name: 'Арканист',
      description: '',
      spaceId: 1,
      spec: { type: 'magic_path', check_code: 'check-intellect', study_cost: null, includes_path_codes: [] },
      createdAt: 1,
    };
    const version = {
      abilities: [{ ruleCode: 'becoming-arcanist', level: 1 }],
      inventory: [{ id: 3, ruleCode: 'magic-core', quantity: 1, equipped: true }],
    } as CharacterVersion;
    expect(spellCastOptionsService.listPaths(version, [becoming, path])).toEqual([
      { pathCode: 'arcanist', name: 'Арканист', checkCode: 'check-intellect' },
    ]);
    expect(
      spellCastOptionsService.listSources(version, [{ ...path, code: 'magic-core', type: 'item', name: 'Ядро' }]),
    ).toEqual([{ key: 'inventory:3', name: 'Ядро' }]);
  });

  it('источник из гранта, если ядра нет в инвентаре', () => {
    const trait: Rule = {
      id: null,
      code: 'magic-core-capacity',
      type: 'ability',
      name: 'Ядро X',
      description: '',
      spaceId: 1,
      spec: {
        type: 'trait',
        zones: { os: { kind: 'array', levels_cost: [1] } },
        requirements: [],
        grants: [{ level: 1, grants: [{ type: 'item', item_code: 'magic-core', quantity: 1 }] }],
        parent_ability_code: null,
      },
      createdAt: 1,
    };
    const item: Rule = {
      id: null,
      code: 'magic-core',
      type: 'item',
      name: 'Магическое ядро',
      description: '',
      spaceId: 1,
      spec: { category: 'equipment', cost_gm: null, weight: null, innate: true, special_rule_codes: [] },
      createdAt: 1,
    };
    const version = {
      abilities: [{ ruleCode: 'magic-core-capacity', level: 1 }],
      inventory: [],
    } as unknown as CharacterVersion;
    expect(spellCastOptionsService.listSources(version, [trait, item])).toEqual([
      { key: 'grant:magic-core-capacity', name: 'Магическое ядро' },
    ]);
  });
});

describe('clampToAtMost', () => {
  it('не поднимает мощь выше максимума кастера', () => {
    expect(spellCastDifficultyService.clampToAtMost({ base: 5, size: 2 }, { base: 3, size: 0 })).toEqual({
      base: 3,
      size: 0,
    });
    expect(spellCastDifficultyService.clampToAtMost({ base: 3, size: -1 }, { base: 3, size: 0 })).toEqual({
      base: 3,
      size: -1,
    });
  });
});

describe('asActionAbilitySpec', () => {
  it('не принимает type spell', () => {
    expect(asActionAbilitySpec(spellRule('discharge', 'electricity'))).toBeNull();
  });
});
