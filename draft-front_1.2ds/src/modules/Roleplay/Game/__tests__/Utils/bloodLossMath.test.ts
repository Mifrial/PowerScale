import { describe, expect, it } from 'vitest';
import {
  applyBloodLossGain,
  bloodLossInjuryDifficulty,
  reservedExhaustion,
} from '@/modules/Roleplay/Game/Utils/bloodLossMath';
import {
  declineOutcomeFromRating,
  shouldSkipExhaustionCheck,
} from '@/modules/Roleplay/Game/Utils/exhaustionCheckMessage';
import { formatBloodLossTickMessage } from '@/modules/Roleplay/Game/Utils/bloodLossMessage';
import { characteristicDependsOn } from '@/modules/Roleplay/Character/Utils/stateRuntimeEffects';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

describe('bloodLossMath', () => {
  it('1 истощение + 10 крови → 2 истощения, пол 1', () => {
    const next = applyBloodLossGain(0, 10, 1);
    expect(next.bloodLoss).toBe(10);
    expect(next.exhaustion).toBe(2);
    expect(next.reserved).toBe(1);
    expect(bloodLossInjuryDifficulty(next.reserved)).toBeNull();
  });

  it('6 истощения + 10 крови → 7, обычное увечье (reserved 1)', () => {
    const next = applyBloodLossGain(0, 10, 6);
    expect(next.exhaustion).toBe(7);
    expect(next.reserved).toBe(1);
    expect(bloodLossInjuryDifficulty(next.reserved)).toBeNull();
  });

  it('2 истощения + 50 крови → 7, пол 5, одно увечье DC 2', () => {
    const next = applyBloodLossGain(0, 50, 2);
    expect(next.exhaustion).toBe(7);
    expect(next.reserved).toBe(5);
    expect(next.addedExhaustion).toBe(5);
    expect(bloodLossInjuryDifficulty(5)).toBe(2);
    expect(bloodLossInjuryDifficulty(4)).toBe(1);
    expect(bloodLossInjuryDifficulty(6)).toBe(4);
  });

  it('reserved = ⌊кровь/10⌋', () => {
    expect(reservedExhaustion(9)).toBe(0);
    expect(reservedExhaustion(10)).toBe(1);
    expect(reservedExhaustion(49)).toBe(4);
  });
});

describe('declineOutcomeFromRating', () => {
  it('маппит РУ провала на флаги Упадка сил', () => {
    expect(declineOutcomeFromRating(0)).toBe('clear');
    expect(declineOutcomeFromRating(-1)).toBe('weakness');
    expect(declineOutcomeFromRating(-2)).toBe('disabled');
    expect(declineOutcomeFromRating(-3)).toBe('unconscious');
    expect(declineOutcomeFromRating(-8)).toBe('unconscious');
  });
});

describe('formatBloodLossTickMessage', () => {
  it('пишет прирост и итог с токеном персонажа', () => {
    expect(formatBloodLossTickMessage('Гаррик', 4, 12, 'character:3')).toBe(
      '[[character:3,Гаррик]] получил 4 повреждения от кровопотери. Итого у персонажа уже Кровопотеря 12',
    );
    expect(formatBloodLossTickMessage('Гаррик', 1, 1, 'character:3')).toContain('1 повреждение');
    expect(formatBloodLossTickMessage('Бородач', 5, 50, 'npc:2')).toContain('[[npc:2,Бородач]]');
  });
});

describe('characteristicDependsOn', () => {
  it('видит производную min/max', () => {
    const derived: Rule[] = [
      {
        id: 's',
        code: 'strength',
        type: 'characteristic',
        name: 'Сила',
        description: '',
        spaceId: 1,
        spec: { type: 'characteristic' },
        createdAt: '',
      },
      {
        id: 'd',
        code: 'dexterity',
        type: 'characteristic',
        name: 'Ловкость',
        description: '',
        spaceId: 1,
        spec: { type: 'characteristic' },
        createdAt: '',
      },
      {
        id: 'm',
        code: 'might',
        type: 'characteristic',
        name: 'Мощь',
        description: '',
        spaceId: 1,
        spec: { type: 'characteristic', formula: 'min(strength, dexterity)' },
        createdAt: '',
      },
    ];
    expect(characteristicDependsOn('might', 'strength', derived)).toBe(true);
    expect(characteristicDependsOn('might', 'willpower', derived)).toBe(false);
  });
});

describe('shouldSkipExhaustionCheck', () => {
  it('пропускает рост истощения в бессознательности, снижение — нет', () => {
    expect(shouldSkipExhaustionCheck(true, 'increase')).toBe(true);
    expect(shouldSkipExhaustionCheck(true, 'decrease')).toBe(false);
    expect(shouldSkipExhaustionCheck(false, 'increase')).toBe(false);
    expect(shouldSkipExhaustionCheck(false, 'decrease')).toBe(false);
  });
});
