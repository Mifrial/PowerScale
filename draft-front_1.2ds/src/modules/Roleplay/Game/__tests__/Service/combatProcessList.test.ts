import { describe, expect, it } from 'vitest';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { ActiveSpell } from '@/modules/Roleplay/Game/Dto/Spell/ActiveSpell';
import type { ProcessSession } from '@/modules/Roleplay/Game/Dto/ProcessSession';
import { combatProcessListService } from '@/modules/Roleplay/Game/Service/Instance/combatProcessListService';
import type { ProcessSpec } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const movementSpec: ProcessSpec = {
  start_step_code: 'walk',
  transition: { mode: 'chain', max_shift: 1, direction: 'both' },
  steps: [
    {
      code: 'walk',
      name: 'Ходьба',
      description: '',
      interruption: { mode: 'normal' },
      costs: [{ resource_code: 'action-points', amount: 1 }],
    },
  ],
};

const emergencySpec: ProcessSpec = {
  ...movementSpec,
  steps: [
    {
      ...movementSpec.steps[0],
      interruption: {
        mode: 'emergency',
        effects: [
          {
            type: 'after_action_until_resource_spent_check_modifier',
            resource_code: 'action-points',
            amount: 1,
            check_codes: [],
            delta: -1,
          },
        ],
      },
    },
  ],
};

function abilityRule(code: string, name: string, spec: Rule['spec']): Rule {
  return {
    id: null,
    code,
    type: 'ability',
    name,
    description: '',
    spaceId: 1,
    spec,
    createdAt: 1,
  };
}

const walkRule = abilityRule('movement', 'Движение', {
  type: 'process',
  zones: {},
  requirements: [],
  grants: [],
  parent_ability_code: null,
  process: movementSpec,
});

const emergencyRule = abilityRule('locked-process', 'Рывок', {
  type: 'process',
  zones: {},
  requirements: [],
  grants: [],
  parent_ability_code: null,
  process: emergencySpec,
});

const generatorRule = abilityRule('lightning-generator', 'Генератор молний', {
  type: 'spell',
  zones: {},
  requirements: [],
  grants: [],
  parent_ability_code: null,
  hit_resolution: { type: 'none' },
  action_components: [],
  spell: {
    power: { base: 1, size: 0 },
    control: { base: 1, size: 0 },
    duration: { type: 'sustained', power: { type: 'parameter', parameter_code: 'x' } },
  },
});

const pathRule: Rule = {
  id: null,
  code: 'arcanist',
  type: 'magic_path',
  name: 'Арканист',
  description: '',
  spaceId: 1,
  createdAt: 1,
};

const coreRule: Rule = {
  id: null,
  code: 'magic-core',
  type: 'item',
  name: 'Магическое ядро',
  description: '',
  spaceId: 1,
  createdAt: 1,
};

const session = (code: string): ProcessSession => ({
  gameId: 1,
  entityKey: 'character:1',
  processRuleCode: code,
  currentStepCode: 'walk',
  currentStepStatus: 'pending',
  status: 'active',
  startedAt: 't',
  updatedAt: 't',
});

const spell = (sourceKey: string, casterKey: CombatEntityKey = 'character:1'): ActiveSpell => ({
  id: `1:${casterKey}:lightning-generator:${sourceKey}`,
  gameId: 1,
  casterKey,
  spellCode: 'lightning-generator',
  sourceKey,
  pathCode: 'arcanist',
  durationType: 'sustained',
  usedPower: { base: 4, size: 0 },
  sustainPower: { base: 4, size: 0 },
  parameterValues: {},
  appliedUpgradeCodes: [],
  startedRound: 1,
  startedParticipantId: casterKey,
  stability: { base: 3, size: 0 },
});

describe('CombatProcessListService', () => {
  it('кладёт процесс и несколько sustained разных источников', () => {
    const rows = combatProcessListService.listRows({
      entityKey: 'character:1',
      processSession: session('movement'),
      committedAction: null,
      activeSpells: [spell('inventory:9'), spell('inventory:10'), spell('inventory:9', 'character:2')],
      version: {
        inventory: [
          { id: 9, ruleCode: 'magic-core', quantity: 1, equipped: true },
          { id: 10, ruleCode: 'magic-core', quantity: 1, equipped: true },
        ],
        abilities: [],
      } as never,
      states: [],
      rules: [walkRule, generatorRule, pathRule, coreRule],
    });

    expect(rows.map((row) => row.kind)).toEqual(['process', 'sustained-spell', 'sustained-spell']);
    expect(rows[0]?.canAbort).toBe(true);
    expect(rows[0]?.valueLabel).toBe('Ходьба');
    expect(rows[1]?.canAbort).toBe(true);
    expect(rows[1]?.details.some((detail) => detail.label === 'Путь' && detail.value === 'Арканист')).toBe(true);
    expect(rows.filter((row) => row.kind === 'sustained-spell')).toHaveLength(2);
  });

  it('кладёт долг ОД рядом с процессом', () => {
    const rows = combatProcessListService.listRows({
      entityKey: 'character:1',
      processSession: null,
      committedAction: {
        gameId: 1,
        entityKey: 'character:1',
        actionRuleCode: 'perevyazat',
        remainingOd: 5,
        totalOd: 8,
        targetKey: 'character:2',
        stateIndices: [0],
        startedAt: 't1',
        updatedAt: 't1',
      },
      activeSpells: [],
      version: null,
      states: [],
      rules: [
        abilityRule('perevyazat', 'Перевязать', {
          type: 'action',
          zones: {},
          requirements: [],
          grants: [],
          parent_ability_code: null,
          action_components: [],
        }),
      ],
    });

    expect(rows[0]?.kind).toBe('committed-action');
    expect(rows[0]?.canAbort).toBe(true);
    expect(rows[0]?.valueLabel).toBe('ещё 5 ОД');
  });

  it('не даёт оборвать процесс с emergency-шагом', () => {
    const rows = combatProcessListService.listRows({
      entityKey: 'character:1',
      processSession: session('locked-process'),
      committedAction: null,
      activeSpells: [],
      version: null,
      states: [],
      rules: [emergencyRule],
    });

    expect(rows[0]?.canAbort).toBe(false);
  });
});
