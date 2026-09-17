import { describe, expect, it } from 'vitest';
import { postureStateService } from '@/modules/Roleplay/Game/Service/Instance/postureStateService';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

const version = (codes: string[]): CharacterVersion =>
  ({ states: codes.map((stateRuleCode) => ({ stateRuleCode })) }) as CharacterVersion;

describe('PostureStateService', () => {
  it('лёжа → встать снимает лежачее', () => {
    expect(postureStateService.nextAfterStandUp(version(['lying']))).toEqual({
      addLying: false,
      removeCodes: ['lying'],
    });
  });

  it('стоя → лечь вешает лежачее и снимает неустойчивость', () => {
    expect(postureStateService.nextAfterStandUp(version(['unstable']))).toEqual({
      addLying: true,
      removeCodes: ['unstable'],
    });
  });
});
