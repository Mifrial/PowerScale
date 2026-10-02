import { describe, expect, it } from 'vitest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { coveringService } from '@/modules/Roleplay/Game/Service/Instance/coveringService';
import { ADVANTAGE_SOURCE_CIRCUMSTANCES } from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';

const COVER_RULE: Rule = {
  mechanics: [],
  id: null,
  code: 'prikrytie',
  type: 'ability',
  name: 'Прикрытие',
  description: '',
  spaceId: 1,
  spec: {
    type: 'action',
    zones: {},
    requirements: [],
    grants: [],
    parent_ability_code: null,
    cover_ally: { circumstance_delta: -1 },
    action_components: [{ type: 'resource', resource_code: 'action-points', amount: 2 }],
  },
  createdAt: 1,
};

const BARE_COVER: Rule = {
  ...COVER_RULE,
  code: 'bare-cover',
  spec: {
    type: 'action',
    zones: {},
    requirements: [],
    grants: [],
    parent_ability_code: null,
    action_components: [{ type: 'resource', resource_code: 'action-points', amount: 2 }],
  },
};

describe('CoveringService', () => {
  it('блок допустим, уклон нет; стоимость 2 ОД; помеха обстоятельств', () => {
    expect(coveringService.canUseReaction('block')).toBe(true);
    expect(coveringService.canUseReaction('dodge')).toBe(false);
    expect(coveringService.cost([COVER_RULE])).toBe(2);
    expect(coveringService.circumstanceModifier([COVER_RULE])).toEqual({
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
        [COVER_RULE],
      ),
    ).toEqual(['character:3']);
  });

  it('способность без поля не прикрывает', () => {
    expect(
      coveringService.eligibleKeys(
        ['character:3'],
        'character:1',
        [],
        () => [{ ruleCode: 'bare-cover', level: 1 }],
        [BARE_COVER],
      ),
    ).toEqual([]);
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
    expect(coveringService.failChoiceKeys('character:1', ['character:2', 'character:3'])).toEqual([
      'character:1',
      'character:2',
      'character:3',
    ]);
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
