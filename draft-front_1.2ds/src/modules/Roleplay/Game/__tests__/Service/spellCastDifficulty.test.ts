import { describe, expect, it } from 'vitest';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CHECK_SIMPLE_CODE, CHECK_SPELL_CAST_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import { asActionAbilitySpec } from '@/modules/Roleplay/Game/Utils/combatActions';
import { spellCastDifficultyService } from '@/modules/Roleplay/Game/Service/Instance/spellCastDifficultyService';
import { spellCastExecutionService } from '@/modules/Roleplay/Game/Service/Instance/spellCastExecutionService';
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
    mechanics: [
      {
        mechanicId: 5,
        mechanicPayload: { type: 'roll', data: { efficiency: 3, sub_mechanics: ['advantage_disadvantage'] } },
      },
    ],
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

function spellRule(
  code: string,
  damageType: string,
  power = { base: 4, size: 1 },
  control = { base: 3, size: -1 },
  resistedByMagic = false,
): Rule {
  return {
    mechanics: [],
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
        resisted_by_magic: resistedByMagic,
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
  mechanics: [],
  spec: {
    type: 'damage_type',
    forms: { genitive: '', dative: '' },
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
  mechanics: [],
  spec: {
    type: 'damage_type',
    forms: { genitive: '', dative: '' },
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
    const spell = spellRule('bolt', 'arcane', { base: 3, size: 0 }, { base: 3, size: 0 }, true);
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

  it('пробивание увеличивает Требуемую мощь и уменьшает resistance', () => {
    const spell = spellRule('piercing-bolt', 'arcane', { base: 3, size: 0 }, { base: 3, size: 0 }, true);
    const result = spellCastDifficultyService.computeForSpell(
      {
        spellCode: 'piercing-bolt',
        usedPower: { base: 3, size: 0 },
        availableControl: { base: 3, size: 0 },
        parameterValues: {},
        hasTarget: true,
        targetResistanceAmount: 3,
        requiredPowerDelta: 1,
        resistancePenetration: 2,
      },
      [spell, ARCANE],
    );

    expect(result.powerShortage).toBe(1);
    expect(result.difficulty).toEqual({ base: 5, size: 0 });
  });

  it('без resisted_by_magic сопротивление цели не влияет на сложность', () => {
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

  it('применяет тренировочный бонус после сопротивления', () => {
    const spell = spellRule('trained-bolt', 'arcane', { base: 3, size: 0 }, { base: 3, size: 0 }, true);
    const result = spellCastDifficultyService.computeForSpell(
      {
        spellCode: 'trained-bolt',
        usedPower: { base: 3, size: 0 },
        availableControl: { base: 3, size: 0 },
        parameterValues: {},
        hasTarget: true,
        targetResistanceAmount: 1,
        trainingDifficultyDelta: -1,
      },
      [spell, ARCANE],
    );
    expect(result.difficulty).toEqual({ base: 3, size: 0 });
    expect(result.needsCheck).toBe(true);
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
      CHECK_SPELL_CAST_CODE,
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
      CHECK_SPELL_CAST_CODE,
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
      mechanics: [],
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
      mechanics: [],
      id: null,
      code: 'arcanist',
      type: 'magic_path',
      name: 'Арканист',
      description: '',
      spaceId: 1,
      spec: {
        type: 'magic_path',
        check_code: 'check-intellect',
        cast_check_code: 'check-spell-cast',
        power_characteristic_code: 'magic-power',
        control_characteristic_code: 'magic-control',
        study_cost: null,
        includes_path_codes: [],
      },
      createdAt: 1,
    };
    const version = {
      abilities: [{ ruleCode: 'becoming-arcanist', level: 1 }],
      inventory: [{ id: 3, ruleCode: 'magic-core', quantity: 1, equipped: true }],
    } as CharacterVersion;
    expect(spellCastOptionsService.listPaths(version, [becoming, path])).toEqual([
      {
        pathCode: 'arcanist',
        name: 'Арканист',
        checkCode: 'check-intellect',
        castCheckCode: 'check-spell-cast',
        powerCharacteristicCode: 'magic-power',
        controlCharacteristicCode: 'magic-control',
        noControl: false,
        recordedCasting: false,
        activeSpellLimit: null,
        includesPathCodes: [],
      },
    ]);
    expect(
      spellCastOptionsService.listSources(version, [{ ...path, code: 'magic-core', type: 'item', name: 'Ядро' }]),
    ).toEqual([{ key: 'inventory:3', name: 'Ядро' }]);
  });

  it('опубликованный путь без type в спеке читается как magic_path', () => {
    const trait: Rule = {
      mechanics: [],
      id: null,
      code: 'magic-crystal-capacity',
      type: 'ability',
      name: 'Врождённый кристалл',
      description: '',
      spaceId: 1,
      spec: {
        type: 'trait',
        zones: { os: { kind: 'array', levels_cost: [1] } },
        requirements: [],
        grants: [
          {
            level: 1,
            grants: [
              { type: 'item', item_code: 'magic-crystal', quantity: 1 },
              { type: 'magic_path', path_code: 'simple-magic' },
            ],
          },
        ],
        parent_ability_code: null,
      },
      createdAt: 1,
    };
    // Канон сервера: MagicPathSpecs не требует `type` в спеке пути.
    const path: Rule = {
      mechanics: [],
      id: null,
      code: 'simple-magic',
      type: 'magic_path',
      name: 'Простейшее волшебство',
      description: '',
      spaceId: 1,
      spec: {
        check_code: 'check-spell-cast',
        cast_check_code: 'check-spell-cast',
        power_characteristic_code: 'magic-power',
        control_characteristic_code: null,
        includes_path_codes: [],
        no_control: true,
        recorded_casting: true,
      } as unknown as Rule['spec'],
      createdAt: 1,
    };
    const version = {
      abilities: [{ ruleCode: 'magic-crystal-capacity', level: 1 }],
      inventory: [],
      raceRuleCode: 'yappi',
    } as unknown as CharacterVersion;

    expect(spellCastOptionsService.listPaths(version, [trait, path])).toEqual([
      {
        pathCode: 'simple-magic',
        name: 'Простейшее волшебство',
        checkCode: 'check-spell-cast',
        castCheckCode: 'check-spell-cast',
        powerCharacteristicCode: 'magic-power',
        controlCharacteristicCode: null,
        noControl: true,
        recordedCasting: true,
        activeSpellLimit: null,
        includesPathCodes: [],
      },
    ]);
  });

  it('кристалл показывает ману, записи и предел мощи', () => {
    const spell = spellRule('discharge', 'electricity');
    const crystal: Rule = {
      mechanics: [],
      id: null,
      code: 'magic-crystal',
      type: 'item',
      name: 'Кристалл',
      description: '',
      spaceId: 1,
      spec: {
        category: 'equipment',
        cost_gm: null,
        weight: null,
        innate: true,
        special_rule_codes: [],
        storage: {
          power_parameter: 'max_power',
          volume_parameter: 'volume',
          recorded_parameter: 'recorded_spells',
          volume_base: 20,
          volume_increment: 10,
          min_volume_power_delta: 3,
        },
      },
      createdAt: 1,
    };
    const version = {
      abilities: [],
      inventory: [
        {
          id: 5,
          ruleCode: 'magic-crystal',
          quantity: 1,
          equipped: true,
          parameters: { max_power: { base: 4, size: 0 } },
          payload: { volume: 20, recorded_spells: [{ ruleCode: 'discharge' }] },
        },
      ],
      items: [
        {
          inventoryId: 5,
          ruleCode: 'magic-crystal',
          maxPower: { base: 4, size: 0 },
          volume: 20,
          occupied: 10,
          mana: 6,
          manaMax: 10,
        },
      ],
    } as unknown as CharacterVersion;

    expect(spellCastOptionsService.listSources(version, [crystal])).toEqual([
      { key: 'inventory:5', name: 'Кристалл 4 · мана 6/10' },
    ]);
    expect(spellCastOptionsService.listRecordedSpells(version, [spell])).toEqual([
      {
        ruleCode: 'discharge',
        name: 'discharge',
        requiredPower: { base: 4, size: 1 },
        requiredControl: { base: 3, size: -1 },
      },
    ]);
    expect(spellCastOptionsService.sourceCapOf(version, null, [crystal], [], 'inventory:5', 'magic-power')).toEqual({
      base: 4,
      size: 0,
    });
    expect(spellCastOptionsService.sourceCapOf(version, null, [crystal], [], 'inventory:5', null)).toEqual({
      base: 4,
      size: 0,
    });
  });

  it('цена каста в мане — мощь × целые ходы Создания', () => {
    expect(spellCastOptionsService.manaCostOf({ base: 4, size: 0 }, 4, 4)).toBe(4);
    expect(spellCastOptionsService.manaCostOf({ base: 4, size: 0 }, 5, 4)).toBe(8);
    expect(spellCastOptionsService.manaCostOf({ base: 3, size: 1 }, 4, 4)).toBe(6);
    expect(spellCastOptionsService.manaCostOf({ base: 4, size: 0 }, 0, 4)).toBe(0);
    expect(spellCastOptionsService.manaCostOf({ base: 4, size: 0 }, 4, 0)).toBe(0);
  });

  it('хранилище источника ищется по строке инвентаря', () => {
    const version = {
      abilities: [],
      inventory: [],
      items: [
        {
          inventoryId: 5,
          ruleCode: 'magic-crystal',
          maxPower: { base: 4, size: 0 },
          volume: 20,
          occupied: 10,
          mana: 6,
          manaMax: 10,
        },
      ],
    } as unknown as CharacterVersion;

    expect(spellCastOptionsService.storageOf(version, 'inventory:5')?.mana).toBe(6);
    expect(spellCastOptionsService.storageOf(version, 'inventory:4')).toBeNull();
    expect(spellCastOptionsService.storageOf(version, 'characteristic:magic-power')).toBeNull();
    expect(spellCastOptionsService.storageOf(null, 'inventory:5')).toBeNull();
  });

  it('без ядра в инвентаре источника нет', () => {
    const trait: Rule = {
      mechanics: [],
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
      mechanics: [],
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
    expect(spellCastOptionsService.listSources(version, [trait, item])).toEqual([]);
  });
});

describe('sourceCapOf', () => {
  const core: Rule = {
    mechanics: [],
    id: null,
    code: 'magic-core',
    type: 'item',
    name: 'Магическое ядро',
    description: '',
    spaceId: 1,
    spec: {
      category: 'equipment',
      cost_gm: null,
      weight: null,
      innate: true,
      special_rule_codes: [],
      grants: [
        {
          type: 'characteristic_parameter',
          characteristic_code: 'magic-power',
          parameter_code: 'power',
          per_unit: 1,
          permanent: true,
        },
      ],
    },
    createdAt: 1,
  };

  const version = (inventoryId: number, power: { base: number; size: number }, abilities: unknown[] = []) =>
    ({
      inventory: [{ id: inventoryId, ruleCode: 'magic-core', quantity: 1, equipped: true, parameters: { power } }],
      abilities,
      characteristics: [{ ruleCode: 'magic-power', base: { base: 5, size: 1 }, modifiers: [] }],
      states: [],
    }) as unknown as CharacterVersion;

  const overview = {
    characteristics: [{ ruleCode: 'magic-power', name: 'Магическая мощь', value: { base: 5, size: 1 } }],
  } as unknown as CharacterOverview;

  it('берёт мощь экземпляра ядра, а не общую характеристику', () => {
    expect(
      spellCastOptionsService.sourceCapOf(
        version(3, { base: 4, size: 0 }),
        overview,
        [core],
        [],
        'inventory:3',
        'magic-power',
      ),
    ).toEqual({ base: 4, size: 0 });
  });

  it('добавляет бонус spell_source_power к мощи источника', () => {
    const flow: Rule = {
      mechanics: [],
      id: null,
      code: 'core-flow-optimization',
      type: 'ability',
      name: 'Оптимизация потоков',
      description: '',
      spaceId: 1,
      spec: {
        type: 'skill',
        zones: {},
        requirements: [],
        grants: [],
        parent_ability_code: null,
        spell_source_power: {
          scope: { kind: 'item', item_code: 'magic-core' },
          path_scoped: true,
          amount: { type: 'ability_level', ability_code: 'core-flow-optimization', multiplier: 1 },
        },
      },
      createdAt: 1,
    };
    const abilities = [{ ruleCode: 'core-flow-optimization', level: 1 }];

    expect(
      spellCastOptionsService.sourceCapOf(
        version(3, { base: 4, size: 0 }, abilities),
        overview,
        [core, flow],
        [],
        'inventory:3',
        'magic-power',
      ),
    ).toEqual({ base: 5, size: 0 });
  });

  it('имя источника показывает мощь и бонус', () => {
    const flow: Rule = {
      mechanics: [],
      id: null,
      code: 'core-flow-optimization',
      type: 'ability',
      name: 'Оптимизация потоков',
      description: '',
      spaceId: 1,
      spec: {
        type: 'skill',
        zones: {},
        requirements: [],
        grants: [],
        parent_ability_code: null,
        spell_source_power: {
          scope: { kind: 'item', item_code: 'magic-core' },
          path_scoped: true,
          amount: { type: 'ability_level', ability_code: 'core-flow-optimization', multiplier: 1 },
        },
      },
      createdAt: 1,
    };
    const abilities = [{ ruleCode: 'core-flow-optimization', level: 1 }];

    expect(spellCastOptionsService.listSources(version(3, { base: 4, size: 0 }, abilities), [core, flow])).toEqual([
      { key: 'inventory:3', name: 'Магическое ядро 4 (+1)' },
    ]);
  });

  it('производная способность по опыту даёт бонус без покупки', () => {
    const flow: Rule = {
      mechanics: [],
      id: null,
      code: 'core-flow-optimization',
      type: 'ability',
      name: 'Оптимизация потоков',
      description: '',
      spaceId: 1,
      spec: {
        type: 'skill',
        zones: {},
        requirements: [],
        grants: [],
        parent_ability_code: null,
        derived_level: { source_keyword: 'magic', thresholds: [0] },
        spell_source_power: {
          scope: { kind: 'item', item_code: 'magic-core' },
          path_scoped: true,
          amount: { type: 'ability_level', ability_code: 'core-flow-optimization', multiplier: 1 },
        },
      },
      createdAt: 1,
    };

    expect(
      spellCastOptionsService.sourceCapOf(
        version(3, { base: 4, size: 0 }, []),
        overview,
        [core, flow],
        [{ id: 1, code: 'magic', name: 'Волшебство', description: '', active: true }],
        'inventory:3',
        'magic-power',
      ),
    ).toEqual({ base: 5, size: 0 });
  });

  it('scope source действует только на выбранный предмет', () => {
    const flow: Rule = {
      mechanics: [],
      id: null,
      code: 'core-flow-optimization',
      type: 'ability',
      name: 'Оптимизация потоков',
      description: '',
      spaceId: 1,
      spec: {
        type: 'skill',
        zones: {},
        requirements: [],
        grants: [],
        parent_ability_code: null,
        spell_source_power: {
          scope: { kind: 'source', target_code: 'core' },
          path_scoped: true,
          amount: { type: 'ability_level', ability_code: 'core-flow-optimization', multiplier: 1 },
        },
      },
      createdAt: 1,
    };
    const abilities = [{ ruleCode: 'core-flow-optimization', level: 1, targets: { core: 3 } }];

    expect(
      spellCastOptionsService.sourceCapOf(
        version(3, { base: 4, size: 0 }, abilities),
        overview,
        [core, flow],
        [],
        'inventory:3',
        'magic-power',
      ),
    ).toEqual({ base: 5, size: 0 });
    expect(
      spellCastOptionsService.sourceCapOf(
        version(4, { base: 4, size: 0 }, abilities),
        overview,
        [core, flow],
        [],
        'inventory:4',
        'magic-power',
      ),
    ).toEqual({ base: 4, size: 0 });
  });

  it('берёт эффективную мощь источника из серверного листа без повторного бонуса', () => {
    const v = version(3, { base: 4, size: 0 });
    v.sourcePowers = [
      {
        inventoryId: 3,
        ruleCode: 'magic-core',
        characteristicCode: 'magic-power',
        base: { base: 4, size: 0 },
        bonus: 1,
        value: { base: 5, size: 0 },
        contributions: [{ sourceRuleCode: 'core-flow-optimization', delta: 1 }],
      },
    ];

    expect(spellCastOptionsService.sourceCapOf(v, overview, [core], [], 'inventory:3', 'magic-power')).toEqual({
      base: 5,
      size: 0,
    });
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

describe('clampToRange', () => {
  it('держит размерное значение в пределах параметра', () => {
    expect(
      spellCastDifficultyService.clampToRange({ base: 5, size: 5 }, { base: 3, size: -1 }, { base: 5, size: 1 }),
    ).toEqual({ base: 5, size: 1 });
    expect(
      spellCastDifficultyService.clampToRange({ base: 3, size: -2 }, { base: 3, size: -1 }, { base: 5, size: 1 }),
    ).toEqual({ base: 3, size: -1 });
    expect(
      spellCastDifficultyService.clampToRange({ base: 4, size: 0 }, { base: 3, size: -1 }, { base: 5, size: 1 }),
    ).toEqual({ base: 4, size: 0 });
  });

  it('без пределов не трогает значение', () => {
    expect(spellCastDifficultyService.clampToRange({ base: 5, size: 3 }, null, null)).toEqual({ base: 5, size: 3 });
  });
});

describe('sustainPowerLimits', () => {
  it('берёт минимум и максимум параметра длительности', () => {
    const rule = spellRule('lightning-generator', 'electricity');
    const spec = rule.spec as unknown as {
      spell: Record<string, unknown>;
      parameters?: Record<string, unknown>[];
    };
    spec.spell.duration = { type: 'sustained', power: { type: 'parameter', parameter_code: 'x' } };
    spec.parameters = [
      {
        code: 'x',
        label: 'X',
        kind: 'dimensional',
        resolution: 'activation',
        default: { base: 3, size: 0 },
        min: { base: 3, size: -1 },
        max: { base: 5, size: 1 },
      },
    ];

    expect(spellCastDifficultyService.sustainPowerLimits(rule)).toEqual({
      min: { base: 3, size: -1 },
      max: { base: 5, size: 1 },
    });
  });

  it('фиксированная мощь длительности задаёт обе границы', () => {
    const rule = spellRule('lightning-generator', 'electricity');
    const spec = rule.spec as unknown as { spell: Record<string, unknown> };
    spec.spell.duration = { type: 'sustained', power: { base: 4, size: 0 } };

    expect(spellCastDifficultyService.sustainPowerLimits(rule)).toEqual({
      min: { base: 4, size: 0 },
      max: { base: 4, size: 0 },
    });
  });

  it('без формы мощи пределов нет', () => {
    expect(spellCastDifficultyService.sustainPowerLimits(spellRule('discharge', 'electricity'))).toEqual({
      min: null,
      max: null,
    });
  });
});

describe('asActionAbilitySpec', () => {
  it('не принимает type spell', () => {
    expect(asActionAbilitySpec(spellRule('discharge', 'electricity'))).toBeNull();
  });
});

describe('targetSpellResistanceFromOverview', () => {
  const damageType = (code: string, modifies: boolean): Rule => ({
    mechanics: [],
    id: null,
    code,
    type: 'damage_type',
    name: code,
    description: '',
    spaceId: 1,
    spec: {
      type: 'damage_type',
      forms: { genitive: code, dative: code },
      defense_ignored: false,
      modifies_spell_difficulty: modifies,
      max_success_rating: null,
    },
    createdAt: 1,
  });

  const line = (code: string, value: number, source: string) => ({
    kind: 'resistance' as const,
    value,
    valueLabel: String(value),
    durability: null,
    sourceCode: source,
    sourceLabel: source,
    damageTypeLabel: code,
    damageTypeDative: code,
    damageTypeCode: code,
  });

  const overview = (grants: ReturnType<typeof line>[], armor: ReturnType<typeof line>[] = []) =>
    ({
      defense: {
        armor: armor.length ? [{ itemRuleCode: 'armor', itemName: 'Броня', href: '#', lines: armor, tiers: [] }] : [],
        resistances: grants,
        constantDefense: 0,
        tiers: [],
        shield: null,
      },
    }) as unknown as CharacterOverview;

  it('берёт только типы с modifies_spell_difficulty', () => {
    const rules = [damageType('arcane', true), damageType('electricity', false)];

    expect(
      spellCastOptionsService.targetSpellResistanceFromOverview(
        overview([line('arcane', 2, 'innate'), line('electricity', 5, 'ring')]),
        rules,
      ),
    ).toBe(2);
  });

  it('в одном источнике оставляет сильнейший плюс, источники складывает', () => {
    const rules = [damageType('arcane', true)];

    expect(
      spellCastOptionsService.targetSpellResistanceFromOverview(
        overview(
          [line('arcane', 1, 'mastery'), line('arcane', 2, 'mastery'), line('arcane', 1, 'innate')],
          [line('arcane', 3, 'armor')],
        ),
        rules,
      ),
    ).toBe(6);
  });
});

describe('простейшее волшебство', () => {
  it('сравнивает только мощь и сдвигает её сопротивлением цели', () => {
    const input = {
      requiredPower: { base: 4, size: 0 },
      usedPower: { base: 3, size: 1 },
    };

    const success = spellCastDifficultyService.controllessOutcome(input);
    expect(success.success).toBe(true);
    expect(success.effectivePower).toEqual({ base: 3, size: 1 });
    expect(spellCastDifficultyService.formatControllessLine(success)).toBe('Сотворение по Мощи: 3↑ против 4 — успех');

    const resisted = spellCastDifficultyService.controllessOutcome({
      ...input,
      resistanceModify: 3,
    });
    expect(resisted.success).toBe(false);
    expect(resisted.effectivePower).toEqual({ base: 3, size: 0 });
    expect(spellCastDifficultyService.formatControllessLine(resisted)).toBe(
      'Сотворение по Мощи: 3↑ − сопротивление 3 = 3 против 4 — недостаточно',
    );
  });

  it('без стата источника не подставляет {3|0}', () => {
    expect(spellCastOptionsService.sourceCapOf(null, null, [], [], '', null)).toBeNull();
  });

  it('путь по умолчанию: включающий раньше собственного', () => {    const path = (code: string, includes: string[]) => ({
      pathCode: code,
      name: code,
      checkCode: null,
      castCheckCode: null,
      powerCharacteristicCode: null,
      controlCharacteristicCode: null,
      noControl: false,
      recordedCasting: false,
      activeSpellLimit: code === 'psionic' ? 1 : null,
      includesPathCodes: includes,
    });
    const paths = [path('psionic', []), path('shaman', ['psionic'])];

    expect(
      spellCastOptionsService.defaultPathForSpell('lightning-strike', paths, [
        { ruleCode: 'lightning-strike', level: 1, domainCode: 'psionic' },
      ]),
    ).toBe('shaman');
    expect(
      spellCastOptionsService.defaultPathForSpell('lightning-strike', paths, [
        { ruleCode: 'lightning-strike', level: 1, domainCode: 'shaman' },
      ]),
    ).toBe('shaman');
  });

  it('экземпляр псионического заклинания доступен через Шамана, но не через Арканиста', () => {
    const spell = spellRule('zap', 'electricity');
    const pathRule = (code: string, includes: string[]): Rule => ({
      ...spell,
      code,
      type: 'magic_path',
      spec: {
        type: 'magic_path',
        check_code: CHECK_SPELL_CAST_CODE,
        cast_check_code: CHECK_SPELL_CAST_CODE,
        power_characteristic_code: 'magic-power',
        control_characteristic_code: 'magic-control',
        includes_path_codes: includes,
      },
    });
    const rules = [spell, pathRule('arcanist', []), pathRule('psionic', []), pathRule('shaman', ['psionic'])];
    const overview = {
      characteristics: [{ ruleCode: 'magic-power', value: { base: 4, size: 0 } }],
    } as unknown as CharacterOverview;
    const abilities = [{ ruleCode: 'zap', level: 1, domainCode: 'psionic' }];

    expect(
      spellCastExecutionService.isSpellAvailableForUse({
        spellCode: 'zap',
        pathCode: 'shaman',
        casterOverview: overview,
        casterAbilities: abilities,
        rules,
      }),
    ).toBe(true);
    expect(
      spellCastExecutionService.isSpellAvailableForUse({
        spellCode: 'zap',
        pathCode: 'arcanist',
        casterOverview: overview,
        casterAbilities: abilities,
        rules,
      }),
    ).toBe(false);
  });
});
