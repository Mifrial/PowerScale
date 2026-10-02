import { describe, expect, it } from 'vitest';
import { postureStateService } from '@/modules/Roleplay/Game/Service/Instance/postureStateService';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const version = (codes: string[]): CharacterVersion =>
  ({ states: codes.map((stateRuleCode) => ({ stateRuleCode })) }) as CharacterVersion;

function state(code: string, flag?: 'lying' | 'unstable'): Rule {
  return {
    code,
    type: 'state',
    spec: { value_type: 'flag', aggregation: 'max', effects: [], ...(flag ? { [flag]: true } : {}) },
  } as Rule;
}

const rules = [state('lying', 'lying'), state('unstable', 'unstable')];

describe('PostureStateService', () => {
  it('лёжа → встать снимает лежачее', () => {
    expect(postureStateService.nextAfterStandUp(version(['lying']), rules)).toEqual({
      addCode: null,
      removeCodes: ['lying'],
    });
  });

  it('стоя → лечь вешает лежачее и снимает неустойчивость', () => {
    expect(postureStateService.nextAfterStandUp(version(['unstable']), rules)).toEqual({
      addCode: 'lying',
      removeCodes: ['unstable'],
    });
  });

  it('код lying без флага не считается лежачим', () => {
    expect(postureStateService.hasLying(version(['lying']), [state('lying')])).toBe(false);
  });

  it('восстановление устойчивости только у роли recover-stability', () => {
    const flagged = {
      code: 'stand-firm',
      type: 'ability',
      spec: { type: 'action', combat_action: 'recover-stability' },
    } as Rule;
    const sameCode = {
      code: 'recover-stability',
      type: 'ability',
      spec: { type: 'action' },
    } as Rule;

    expect(postureStateService.isRecoverStability(flagged)).toBe(true);
    expect(postureStateService.isRecoverStability(sameCode)).toBe(false);
  });
});

describe('AttackDamageService posture rules', () => {
  it('ищет лёжа и неустойчивость по флагу, не по коду каталога', () => {
    expect(attackDamageService.lyingRule([state('lying')])).toBeNull();
    expect(attackDamageService.lyingRule([state('prone', 'lying')])?.code).toBe('prone');
    expect(attackDamageService.unstableRule([state('unstable')])).toBeNull();
    expect(attackDamageService.unstableRule([state('wobble', 'unstable')])?.code).toBe('wobble');
  });
});
