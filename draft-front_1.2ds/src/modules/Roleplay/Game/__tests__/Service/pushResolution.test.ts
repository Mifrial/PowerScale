import { describe, expect, it } from 'vitest';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { CheckOfferProposal } from '@/modules/Roleplay/Game/Dto/CheckOfferProposal';
import { pushResolutionService } from '@/modules/Roleplay/Game/Service/Instance/pushResolutionService';

const attack: AttackOverview = {
  itemRuleCode: 'ruka',
  itemName: 'Рука',
  itemHref: '',
  profileType: 'strike',
  profileTypeLabel: 'удар',
  distanceLabel: '0',
  reach: 1,
  minDistance: 0,
  falloff: { base: 0, size: 0 },
  accuracyLabel: '1',
  accuracy: { base: 1, size: 0 },
  damageLabel: '1',
  penetrationLabel: '0',
  damageFormula: '1',
  penetrationFormula: '0',
  isResolved: true,
  damageTypeCode: 'blunt',
  damage: { base: 3, size: 0 },
  penetration: { base: 0, size: 0 },
};

function roll(rating: number): DiceRollResult {
  return {
    spec: { diceCount: 1, dieSize: 0, dieFaces: 6, efficiency: 4, advantages: [] },
    rolls: [4],
    successes: [1],
    adjustedRolls: [4],
    droppedRolls: [],
    totalSuccesses: 1,
    check: { check_code: 'check-hit', difficulty: { base: 0, size: -1 }, passed: rating > 0, rating },
  };
}

const hit = (reaction: 'dodge' | 'ignore'): NonNullable<CheckOfferProposal['hit']> => ({
  itemRuleCode: 'ruka',
  itemName: 'Рука',
  profileType: 'strike',
  accuracy: { base: 1, size: 0 },
  reaction,
});

describe('PushResolutionService', () => {
  it('промах уклонения не идёт в контест Силы', () => {
    const meleeRolled = { attacker: roll(0), defender: roll(1) };
    const outcome = pushResolutionService.prepare({
      hit: hit('dodge'),
      attack,
      meleeRolled,
      attackerKey: 'character:1',
      defenderKey: 'character:2',
      attackerOverview: null,
      defenderOverview: null,
      attackerVersion: null,
      defenderVersion: null,
      rng: () => 0.5,
      rules: [],
      mechanics: [],
    });

    expect(outcome).toEqual({ kind: 'dodge_miss', rolled: meleeRolled });
  });
});
