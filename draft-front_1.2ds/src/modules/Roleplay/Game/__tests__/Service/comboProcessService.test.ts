import { describe, expect, it } from 'vitest';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { CombatActionOption } from '@/modules/Roleplay/Game/Utils/combatActions';
import type { ProcessSession } from '@/modules/Roleplay/Game/Dto/ProcessSession';
import type { ProcessSpec } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { COMBO_STEP_CODES } from '@/modules/Roleplay/Game/Constant/Process/COMBO_STEP_CODES';
import { comboProcessService } from '@/modules/Roleplay/Game/Service/Instance/comboProcessService';
import { processSessionService } from '@/modules/Roleplay/Game/Service/Instance/processSessionService';
import { ADVANTAGE_SOURCE_ACTION } from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';

const step = (code: string, name: string, amount: number) => ({
  code,
  name,
  description: '',
  interruption: { mode: 'normal' as const },
  costs: [{ resource_code: 'action-points', amount }],
});

const comboSpec: ProcessSpec = {
  start_step_code: COMBO_STEP_CODES.start,
  failure: 'end_action',
  transition: {
    mode: 'custom',
    edges: [
      { from: COMBO_STEP_CODES.start, to: COMBO_STEP_CODES.build },
      { from: COMBO_STEP_CODES.build, to: COMBO_STEP_CODES.build },
      { from: COMBO_STEP_CODES.build, to: COMBO_STEP_CODES.finish },
    ],
  },
  steps: [
    step(COMBO_STEP_CODES.start, 'Начало', 3),
    step(COMBO_STEP_CODES.build, 'Набор', 2),
    step(COMBO_STEP_CODES.finish, 'Завершение', 2),
  ],
};

function session(comboCount: number, current = COMBO_STEP_CODES.build): ProcessSession {
  return {
    gameId: 1,
    entityKey: 'character:1',
    processRuleCode: 'combo-process',
    currentStepCode: current,
    currentStepStatus: 'completed',
    status: 'active',
    startedAt: 't',
    updatedAt: 't',
    comboCount,
  };
}

function closerRule(parent: string): Rule {
  return {
    id: null,
    code: 'combo-closer',
    type: 'ability',
    name: 'Closer',
    description: '',
    spaceId: 1,
    spec: {
      type: 'skill',
      zones: {},
      requirements: [],
      grants: [],
      parent_ability_code: parent,
    },
    createdAt: 1,
  };
}

describe('ComboProcessService', () => {
  it('hides finish until combo is at least 2 and increments combo on a successful build', () => {
    const started = processSessionService.start(1, 'character:1', 'combo-process', comboSpec);
    expect(started.comboCount).toBe(0);
    expect(comboProcessService.visibleSteps(comboSpec, started).map((entry) => entry.code)).toEqual([
      COMBO_STEP_CODES.start,
    ]);

    const afterStart = comboProcessService.resolveAfterStrike(started, comboSpec, COMBO_STEP_CODES.start, true);
    expect(afterStart?.comboCount).toBe(1);
    expect(comboProcessService.visibleSteps(comboSpec, afterStart).map((entry) => entry.code)).toEqual([
      COMBO_STEP_CODES.build,
    ]);

    const afterBuild = comboProcessService.resolveAfterStrike(afterStart!, comboSpec, COMBO_STEP_CODES.build, true);
    expect(afterBuild?.comboCount).toBe(2);
    expect(comboProcessService.visibleSteps(comboSpec, afterBuild).map((entry) => entry.code)).toEqual([
      COMBO_STEP_CODES.build,
      COMBO_STEP_CODES.finish,
    ]);
  });

  it('ends the process on a miss and on finish, including a close by another attack', () => {
    const started = processSessionService.start(1, 'character:1', 'combo-process', comboSpec);
    expect(comboProcessService.resolveAfterStrike(started, comboSpec, COMBO_STEP_CODES.start, false)).toBeNull();
    expect(comboProcessService.resolveAfterStrike(session(3), comboSpec, COMBO_STEP_CODES.finish, true)).toBeNull();
    expect(
      comboProcessService.resolveAfterStrike(session(3), comboSpec, COMBO_STEP_CODES.build, true, true),
    ).toBeNull();
  });

  it('builds the close package from combo count with action source and caps', () => {
    expect(comboProcessService.strikePackage(session(0), comboSpec, COMBO_STEP_CODES.start)).toEqual({
      extraSuccessCount: 0,
      modifiers: [{ source_code: ADVANTAGE_SOURCE_ACTION, source_label: 'Действие', delta: -1 }],
    });
    expect(comboProcessService.strikePackage(session(2), comboSpec, COMBO_STEP_CODES.build)).toEqual({
      extraSuccessCount: 0,
      modifiers: [{ source_code: ADVANTAGE_SOURCE_ACTION, source_label: 'Действие', delta: -2 }],
    });
    expect(comboProcessService.strikePackage(session(4), comboSpec, COMBO_STEP_CODES.finish)).toEqual({
      extraSuccessCount: 2,
      modifiers: [{ source_code: ADVANTAGE_SOURCE_ACTION, source_label: 'Действие', delta: 3 }],
    });
    expect(
      comboProcessService.strikePackage(session(6), comboSpec, COMBO_STEP_CODES.build, true).extraSuccessCount,
    ).toBe(3);
  });

  it('lists other attacks as closers only with an owned child of the live combo process', () => {
    const processOption: CombatActionOption = {
      ruleCode: 'combo-process',
      code: 'combo-process',
      name: 'Combo',
      odCost: 0,
      isAttack: true,
      isProcess: true,
      process: comboSpec,
    };
    const other: CombatActionOption = {
      ruleCode: 'other-attack',
      code: 'other-attack',
      name: 'Other',
      odCost: 2,
      isAttack: true,
    };
    const overview = { abilities: [{ ruleCode: 'combo-closer' }] } as CharacterOverview;
    const live = session(2);
    expect(
      comboProcessService
        .listSources([processOption, other], live, comboSpec, overview, [closerRule('combo-process')])
        .map((entry) => entry.code),
    ).toEqual(['combo-process', 'other-attack']);
    expect(
      comboProcessService
        .listSources([processOption, other], live, comboSpec, overview, [closerRule('someone-else')])
        .map((entry) => entry.code),
    ).toEqual(['combo-process']);
  });
});
