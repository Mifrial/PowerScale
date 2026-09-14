import { describe, expect, it } from 'vitest';
import { woundActionLaunchService } from '@/modules/Roleplay/Game/Service/Instance/woundActionLaunchService';
import { BANDAGE_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/BANDAGE_ABILITY_CODE';
import { SQUEEZE_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/SQUEEZE_ABILITY_CODE';
import { WOUND_STATE_CODE } from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';
import { WOUND_ACTION_OD } from '@/modules/Roleplay/Game/Constant/Wound/WOUND_ACTION_OD';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

function wound(value: number, extra: Partial<NonNullable<CharacterStateValue['wound']>> = {}): CharacterStateValue {
  return {
    stateRuleCode: WOUND_STATE_CODE,
    value,
    wound: { bandage: 0, clotting: 0, internal: false, aided: false, heldBy: null, ...extra },
  };
}

describe('WoundActionLaunchService', () => {
  it('отличает перевязку и зажим', () => {
    expect(woundActionLaunchService.isBandage(BANDAGE_ABILITY_CODE)).toBe(true);
    expect(woundActionLaunchService.isSqueeze(SQUEEZE_ABILITY_CODE)).toBe(true);
    expect(woundActionLaunchService.isWoundAction('wait')).toBe(false);
  });

  it('считает ОД перевязки по цели и списки ран', () => {
    const actor = { abilities: [{ ruleCode: 'pervaya-pomosch', level: 1 }] } as CharacterVersion;
    expect(woundActionLaunchService.bandageOd(actor, null)).toBe(WOUND_ACTION_OD.bandageAid);
    expect(woundActionLaunchService.bandageOd(actor, { woundBandagedOnce: true } as never)).toBe(
      WOUND_ACTION_OD.bandageAid,
    );
    expect(woundActionLaunchService.squeezeOd(2)).toBe(4);
    const states = [wound(3), wound(2, { internal: true })];
    const bandage = woundActionLaunchService.bandageOptions(states, actor);
    expect(bandage[0]?.disabled).toBe(false);
    expect(bandage[1]?.disabled).toBe(true);
    const squeeze = woundActionLaunchService.squeezeOptions(states);
    expect(squeeze[0]?.disabled).toBe(false);
    expect(squeeze[1]?.disabled).toBe(true);
  });
});
