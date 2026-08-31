import { describe, expect, it } from 'vitest';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { versions } from '@/modules/Roleplay/Character/Mock/mockCharacters';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import { stateRuntimeEffectsService } from '@/modules/Roleplay/Character/Service/Instance/stateRuntimeEffectsService';
import { liveActionPointsLimitService } from '@/modules/Roleplay/Character/Service/Instance/liveActionPointsLimitService';
import {
  DISABLED_STATE_CODE,
  MAIM_STATE_CODE,
  UNCONSCIOUS_STATE_CODE,
  WEAKNESS_STATE_CODE,
} from '@/modules/Roleplay/Rule/init';

const rules = ruleCatalog;

function withFlag(version: CharacterVersion, code: string): CharacterVersion {
  const rule = rules.find((item) => item.code === code && item.type === 'state');
  if (!rule) throw new Error(code);

  return { ...version, states: [...version.states, { stateRuleId: rule.id }] };
}

function withMaim(version: CharacterVersion, strength: number): CharacterVersion {
  const rule = rules.find((item) => item.code === MAIM_STATE_CODE && item.type === 'state');
  if (!rule) throw new Error('maim');

  return { ...version, states: [...version.states, { stateRuleId: rule.id, value: strength }] };
}

describe('liveActionPointsLimit', () => {
  const base = versions[1];

  it('Слабость даёт −1 к лимиту ОД', () => {
    const sheet = liveActionPointsLimitService.liveActionPointsLimit(
      base,
      rules,
      stateRuntimeEffectsService.effectiveCharacteristicValues(base, rules),
    );
    const weak = withFlag(base, WEAKNESS_STATE_CODE);
    const next = liveActionPointsLimitService.liveActionPointsLimit(
      weak,
      rules,
      stateRuntimeEffectsService.effectiveCharacteristicValues(weak, rules),
    );
    expect(sheet).not.toBeNull();
    expect(next).toBe((sheet ?? 0) - 1);
  });

  it('Потеря сознания ставит лимит ОД в 0', () => {
    const knocked = withFlag(base, UNCONSCIOUS_STATE_CODE);
    expect(
      liveActionPointsLimitService.liveActionPointsLimit(
        knocked,
        rules,
        stateRuntimeEffectsService.effectiveCharacteristicValues(knocked, rules),
      ),
    ).toBe(0);
  });

  it('Обессилен меняет вклад Восприятия/Силы в лимит ОД', () => {
    const sheet =
      liveActionPointsLimitService.liveActionPointsLimit(
        base,
        rules,
        stateRuntimeEffectsService.effectiveCharacteristicValues(base, rules),
      ) ?? 0;
    const disabled = withFlag(base, DISABLED_STATE_CODE);
    const next =
      liveActionPointsLimitService.liveActionPointsLimit(
        disabled,
        rules,
        stateRuntimeEffectsService.effectiveCharacteristicValues(disabled, rules),
      ) ?? 0;
    expect(next).not.toBe(sheet);
  });

  it('увечья складывают помехи на попадание, Силу и Ловкость, не на Волю', () => {
    const one = withMaim(base, 2);
    const two = withMaim(one, 3);
    expect(stateRuntimeEffectsService.checkAdvantageFromStates(one, rules, { kind: 'hit' })).toBe(-2);
    expect(stateRuntimeEffectsService.checkAdvantageFromStates(two, rules, { kind: 'hit' })).toBe(-5);
    expect(
      stateRuntimeEffectsService.checkAdvantageFromStates(two, rules, { kind: 'characteristic', code: 'strength' }),
    ).toBe(-5);
    expect(
      stateRuntimeEffectsService.checkAdvantageFromStates(two, rules, { kind: 'characteristic', code: 'dexterity' }),
    ).toBe(-5);
    expect(
      stateRuntimeEffectsService.checkAdvantageFromStates(two, rules, { kind: 'characteristic', code: 'willpower' }),
    ).toBe(0);
    expect(stateRuntimeEffectsService.checkAdvantageFromStates(two, rules)).toBe(0);
  });
});
