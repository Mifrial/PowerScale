import { describe, expect, it } from 'vitest';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import { woundInstanceService } from '@/modules/Roleplay/Game/Service/Instance/woundInstanceService';
import { WOUND_ACTION_OD } from '@/modules/Roleplay/Game/Constant/Wound/WOUND_ACTION_OD';
import { WOUND_STATE_CODE } from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';

function wound(value: number, extra: Partial<CharacterStateValue['wound']> = {}): CharacterStateValue {
  return {
    stateRuleCode: WOUND_STATE_CODE,
    value,
    wound: {
      bandage: 0,
      clotting: 0,
      internal: false,
      aided: false,
      heldBy: null,
      ...extra,
    },
  };
}

function overlay(states: CharacterStateValue[], entityKey: CombatEntityKey = 'character:1'): GameCombatOverlay {
  return { gameId: 1, entityKey, kind: 'character', resources: [], states, updatedAt: 't' };
}

describe('woundInstance', () => {
  it('миграция голого value → тик = сила', () => {
    const state = woundInstanceService.migrate({ stateRuleCode: WOUND_STATE_CODE, value: 4 });
    expect(state.wound).toEqual(woundInstanceService.emptyWound());
    expect(woundInstanceService.tick(state)).toBe(4);
  });

  it('две раны без вкладов → Δ крови 4', () => {
    expect(woundInstanceService.bleedTotal([wound(3), wound(1)])).toBe(4);
  });

  it('перевязка без помощи: 2+2 и стоп на 4', () => {
    let state = wound(10);
    state = woundInstanceService.applyBandage(state, false);
    expect(woundInstanceService.payload(state).bandage).toBe(2);
    state = woundInstanceService.applyBandage(state, false);
    expect(woundInstanceService.payload(state).bandage).toBe(4);
    expect(woundInstanceService.canBandage(state, false)).toBe(false);
    state = woundInstanceService.applyBandage(state, false);
    expect(woundInstanceService.payload(state).bandage).toBe(4);
    expect(woundInstanceService.canBandage(state, true)).toBe(true);
  });

  it('первая помощь: кап снят, вклад до силы, ОД 4', () => {
    let state = wound(6);
    state = woundInstanceService.applyBandage(state, false);
    state = woundInstanceService.applyBandage(state, false);
    state = woundInstanceService.applyBandage(state, true);
    expect(woundInstanceService.payload(state).aided).toBe(true);
    expect(woundInstanceService.payload(state).bandage).toBe(6);
    expect(woundInstanceService.bandageOd({ medicHasAid: true, medicHasQuick: false, targetBandagedOnce: false })).toBe(
      WOUND_ACTION_OD.bandageAid,
    );
  });

  it('спорая: 2 ОД только после первой перевязки цели и с помощью', () => {
    expect(woundInstanceService.bandageOd({ medicHasAid: true, medicHasQuick: true, targetBandagedOnce: false })).toBe(
      WOUND_ACTION_OD.bandageAid,
    );
    expect(woundInstanceService.bandageOd({ medicHasAid: true, medicHasQuick: true, targetBandagedOnce: true })).toBe(
      WOUND_ACTION_OD.bandageQuick,
    );
    expect(woundInstanceService.bandageOd({ medicHasAid: false, medicHasQuick: true, targetBandagedOnce: true })).toBe(
      WOUND_ACTION_OD.bandage,
    );
  });

  it('зажим даёт минимум 1 и не пишет вклад', () => {
    const open = wound(4);
    expect(woundInstanceService.tick(open)).toBe(4);
    const held = woundInstanceService.applySqueeze(open, 'character:2');
    expect(woundInstanceService.tick(held)).toBe(3);
    expect(woundInstanceService.payload(held).bandage).toBe(0);
    expect(woundInstanceService.tick(woundInstanceService.releaseSqueeze(held))).toBe(4);
  });

  it('внутренняя: нельзя перевязать и зажать, свёртывание проходит', () => {
    const state = wound(5, { internal: true });
    expect(woundInstanceService.canBandage(state, true)).toBe(false);
    expect(woundInstanceService.canSqueeze(state)).toBe(false);
    const clotted = woundInstanceService.applyClotting(state, 2);
    expect(woundInstanceService.payload(clotted).clotting).toBe(2);
    expect(woundInstanceService.tick(clotted)).toBe(3);
  });

  it('clamp после падения силы', () => {
    const state = woundInstanceService.setStrength(wound(6, { bandage: 4, clotting: 3 }), 2);
    expect(woundInstanceService.payload(state).bandage).toBe(2);
    expect(woundInstanceService.payload(state).clotting).toBe(2);
    expect(woundInstanceService.tick(state)).toBe(0);
  });

  it('heldCount по всем оверлеям, максимум две руки', () => {
    const overlays = [
      overlay([wound(2, { heldBy: 'character:9' }), wound(1, { heldBy: 'character:9' })]),
      overlay([wound(4, { heldBy: 'character:8' })], 'character:2'),
    ];
    expect(woundInstanceService.heldCount('character:9', overlays)).toBe(2);
    expect(woundInstanceService.freeHands('character:9', overlays)).toBe(0);
    expect(woundInstanceService.freeHands('character:8', overlays)).toBe(1);
  });

  it('неполный sidecar не даёт NaN во вкладах', () => {
    const state = woundInstanceService.migrate({
      stateRuleCode: WOUND_STATE_CODE,
      value: 3,
      wound: { internal: true } as CharacterStateValue['wound'],
    });
    expect(woundInstanceService.payload(state).bandage).toBe(0);
    expect(woundInstanceService.payload(state).clotting).toBe(0);
    expect(woundInstanceService.tick(state)).toBe(3);
  });

  it('навыки медика', () => {
    const version = {
      abilities: [
        { ruleCode: 'pervaya-pomosch', level: 1 },
        { ruleCode: 'sporaya-perevyazka', level: 1 },
      ],
    } as CharacterVersion;
    expect(woundInstanceService.medicHasAid(version)).toBe(true);
    expect(woundInstanceService.medicHasQuick(version)).toBe(true);
    expect(
      woundInstanceService.medicHasQuick({
        abilities: [{ ruleCode: 'sporaya-perevyazka', level: 1 }],
      } as CharacterVersion),
    ).toBe(false);
  });
});
