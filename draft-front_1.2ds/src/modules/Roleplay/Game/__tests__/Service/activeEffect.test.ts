import { describe, expect, it } from 'vitest';
import type { GameActiveEffect } from '@/modules/Roleplay/Game/Dto/GameActiveEffect';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { ActiveEffectService } from '@/modules/Roleplay/Game/Service/ActiveEffectService';

const effect: GameActiveEffect = {
  id: '1',
  gameId: 1,
  sourceType: 'spell',
  sourceRuleCode: 'tearing',
  applierKey: 'npc:3',
  targetKind: 'npc',
  targetId: 4,
  targetKey: 'npc:4',
  durationType: 'lingering',
  durationUnit: 'turn',
  turnsTotal: 1,
  turnsLeft: 1,
  strength: { base: 3, size: 1 },
  context: {},
  startedRound: 1,
  startedParticipant: 'npc:3',
  version: 1,
};

const rules: Rule[] = [
  {
    id: null,
    code: 'tearing',
    type: 'ability',
    name: 'Слезоточивость',
    description: '',
    spaceId: 1,
    mechanics: [],
    createdAt: 1,
  },
];

const service = new ActiveEffectService();

describe('ActiveEffectService', () => {
  it('оставляет эффекты только на своей цели', () => {
    expect(service.ofTarget([effect], 'npc:4')).toEqual([effect]);
    expect(service.ofTarget([effect], 'npc:3')).toEqual([]);
  });

  it('подписывает эффект названием карточки и остатком ходов', () => {
    expect(service.labelOf(effect, rules)).toBe('Слезоточивость (осталось ходов: 1)');
    expect(service.labelOf({ ...effect, sourceRuleCode: 'unknown' }, rules)).toBe(
      'unknown (осталось ходов: 1)',
    );
  });
});
