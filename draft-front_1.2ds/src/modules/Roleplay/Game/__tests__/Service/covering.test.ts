import { describe, expect, it } from 'vitest';
import { coveringService } from '@/modules/Roleplay/Game/Service/Instance/coveringService';
import { ADVANTAGE_SOURCE_CIRCUMSTANCES } from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';

describe('CoveringService', () => {
  it('блок допустим, уклон нет; стоимость 2 ОД; помеха обстоятельств', () => {
    expect(coveringService.canUseReaction('block')).toBe(true);
    expect(coveringService.canUseReaction('dodge')).toBe(false);
    expect(coveringService.cost()).toBe(2);
    expect(coveringService.circumstanceModifier()).toEqual({
      source_code: ADVANTAGE_SOURCE_CIRCUMSTANCES,
      source_label: 'Обстоятельства',
      delta: -1,
    });
  });

  it('атакующий и цель не кандидаты', () => {
    expect(
      coveringService.eligibleKeys(
        ['character:1', 'character:2', 'character:3'],
        'character:1',
        ['character:2'],
        (key) => (key === 'character:3' ? [{ ruleCode: 'prikrytie', level: 1 }] : []),
      ),
    ).toEqual(['character:3']);
  });

  it('несколько успехов — наибольший РУ', () => {
    expect(
      coveringService.actualTarget({
        primaryKey: 'character:1',
        attackerChoice: 'character:1',
        results: [
          { key: 'character:2', passed: true, rating: 1 },
          { key: 'character:3', passed: true, rating: 4 },
        ],
      }),
    ).toBe('character:3');
  });

  it('все провалились — ждёт выбор среди цели и прикрывавших', () => {
    expect(
      coveringService.failChoiceKeys('character:1', ['character:2', 'character:3']),
    ).toEqual(['character:1', 'character:2', 'character:3']);
    expect(
      coveringService.actualTarget({
        primaryKey: 'character:1',
        attackerChoice: null,
        results: [{ key: 'character:2', passed: false, rating: -2 }],
      }),
    ).toBeNull();
    expect(
      coveringService.actualTarget({
        primaryKey: 'character:1',
        attackerChoice: 'character:2',
        results: [{ key: 'character:2', passed: false, rating: -5 }],
      }),
    ).toBe('character:2');
  });
});
