import { describe, expect, it, vi } from 'vitest';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import { injuryCheckService } from '@/modules/Roleplay/Game/Service/Instance/injuryCheckService';

import { injuryRollService } from '@/modules/Roleplay/Game/Service/Instance/injuryRollService';

import { resolveInjuryProcedure } from '@/modules/Roleplay/Game/Utils/resolveInjuryProcedure';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { fetchMechanics } from '@/modules/Roleplay/Rule/Mock/mockMechanics';

function rngFromFaces(faces: number[]): DiceRng {
  let index = 0;

  return () => {
    const face = faces[index] ?? 1;
    index += 1;

    return (face - 1) / 6;
  };
}

describe('injuryInputFromAttack', () => {
  it('режущий не кладёт raw в leftover — только в рану', () => {
    const input = injuryCheckService.injuryInputFromAttack({
      hpDamage: 0,
      cuttingWound: 6,
      woundFromHit: null,
      overlayExhaustion: 1,
      endurance: 3,
      remainingSr: 2,
      damageTypeCode: 'cutting',
      actorKey: 'character:1',
    });
    expect(input.leftoverDamage).toBe(0);
    expect(input.woundStrength).toBe(6);
    expect(input.exhaustion).toBe(1);
  });

  it('рана в формуле — этого удара, не сумма с листа', () => {
    const input = injuryCheckService.injuryInputFromAttack({
      hpDamage: 12,
      cuttingWound: null,
      woundFromHit: 24,
      overlayExhaustion: 27,
      endurance: 5,
      remainingSr: 12,
      damageTypeCode: 'slashing',
      actorKey: 'character:1',
    });
    expect(input.woundStrength).toBe(24);
    expect(input.leftoverDamage).toBe(12);
    expect(input.exhaustion).toBe(27);
  });

  it('истощение 7 с оверлея даёт сложность 1', () => {
    const input = injuryCheckService.injuryInputFromAttack({
      hpDamage: 0,
      cuttingWound: null,
      woundFromHit: null,
      overlayExhaustion: 7,
      endurance: 3,
      remainingSr: 0,
      damageTypeCode: null,
      actorKey: 'character:1',
    });
    expect(injuryRollService.injuryDifficulty(input, resolveInjuryProcedure([], []), 0)).toBe(1);
  });

  it('колющий РУ 4 добавляет 2 к сложности', async () => {
    const mechanics = await fetchMechanics();
    const input = injuryCheckService.injuryInputFromAttack({
      hpDamage: 0,
      cuttingWound: null,
      woundFromHit: null,
      overlayExhaustion: 0,
      endurance: 99,
      remainingSr: 4,
      damageTypeCode: 'piercing',
      actorKey: 'character:1',
    });
    const result = injuryRollService.rollInjury(input, rngFromFaces([1, 1, 1, 1]), ruleCatalog, mechanics);
    expect(result.check?.difficulty).toEqual({ base: 2, size: 0 });
    expect(result.rolls.length).toBe(4);
  });

  it('0 повреждений и 0 раны с удара — автоувечье не запускается (колющий РУ не считается)', () => {
    expect(
      injuryCheckService.shouldLaunchInjuryFromAttack({
        hpDamage: 0,
        cuttingWound: null,
        woundFromHit: null,
      }),
    ).toBe(false);
  });

  it('рана с удара при leftover 0 — автоувечье запускается', () => {
    expect(
      injuryCheckService.shouldLaunchInjuryFromAttack({
        hpDamage: 0,
        cuttingWound: 6,
        woundFromHit: null,
      }),
    ).toBe(true);
  });
});

describe('applyInjuryCheck', () => {
  it('сложность 0 и skipIfNoRoll — без чата и без состояния', async () => {
    const mechanics = await fetchMechanics();
    const sendMessage = vi.fn().mockResolvedValue(true);
    const applied = await injuryCheckService.applyInjuryCheck({
      input: injuryCheckService.injuryInputFromAttack({
        hpDamage: 0,
        cuttingWound: null,
        woundFromHit: null,
        overlayExhaustion: 0,
        endurance: 3,
        remainingSr: 0,
        damageTypeCode: null,
        actorKey: 'character:1',
      }),
      rng: rngFromFaces([6, 6, 6, 6]),
      rules: ruleCatalog,
      mechanics,
      gameId: 1,
      targetKey: 'character:1',
      targetName: 'Цель',
      chatId: 10,
      speaker: { kind: 'gm' },
      skipIfNoRoll: true,
      sendMessage,
    });
    expect(applied.skipped).toBe(true);
    expect(applied.overlay).toBeNull();
    expect(sendMessage).not.toHaveBeenCalled();
  });
});
