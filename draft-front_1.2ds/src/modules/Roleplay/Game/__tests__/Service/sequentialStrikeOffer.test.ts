import { describe, expect, it } from 'vitest';
import { sequentialStrikeOfferService } from '@/modules/Roleplay/Game/Service/Instance/sequentialStrikeOfferService';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const profile = (name: string): AttackOverview => ({
  itemRuleCode: name,
  itemName: name,
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
  damageTypeCode: null,
  damage: { base: 1, size: 0 },
  penetration: { base: 0, size: 0 },
});

const hit = {
  itemRuleCode: 'ruka',
  itemName: 'Рука',
  profileType: 'strike' as const,
  accuracy: { base: 5, size: 0 },
  reaction: null,
};

describe('SequentialStrikeOfferService', () => {
  it('создаёт слот на удар и заполняет реакции по очереди', () => {
    const slots = sequentialStrikeOfferService.createSlots(
      [
        { targetKey: 'character:2', profile: profile('Рука') },
        { targetKey: 'character:2', profile: profile('Кинжал') },
      ],
      hit,
    );
    expect(slots).toHaveLength(2);
    expect(slots[1]?.hit.itemName).toBe('Кинжал');
    expect(sequentialStrikeOfferService.pendingTargetKeys(slots)).toEqual(['character:2']);
    const afterFirst = sequentialStrikeOfferService.fillNextHit(slots, 'character:2', { ...hit, reaction: 'dodge' });
    expect(afterFirst[0]?.hit.reaction).toBe('dodge');
    expect(afterFirst[1]?.hit.reaction).toBeNull();
    expect(sequentialStrikeOfferService.nextPending(afterFirst, 'character:2')?.strikeIndex).toBe(1);
  });

  it('резервирует ОД уже выбранных реакций', () => {
    const rules: Rule[] = [
      {
        mechanics: [],
        id: null,
        code: 'action-points',
        type: 'resource',
        name: 'Очки действий',
        description: '',
        spaceId: 1,
        keywordIds: [],
        createdAt: 0,
        spec: { is_dimensional: false, auto_add: true },
      },
      {
        mechanics: [],
        id: null,
        code: 'dodge',
        type: 'ability',
        name: 'Уклонение',
        description: '',
        spaceId: 1,
        keywordIds: [],
        createdAt: 0,
        spec: {
          type: 'action',
          zones: {},
          requirements: [],
          grants: [],
          parent_ability_code: null,
          combat_action: 'dodge',
          action_components: [{ type: 'resource', resource_code: 'action-points', amount: 1, label: 'Действие' }],
        },
      },
    ];
    const slots = sequentialStrikeOfferService.createSlots(
      [{ targetKey: 'character:2', profile: profile('Рука') }],
      hit,
    );
    const filled = sequentialStrikeOfferService.fillNextHit(slots, 'character:2', { ...hit, reaction: 'dodge' });
    expect(sequentialStrikeOfferService.committedReactionOd(filled, rules)).toBe(1);
  });
});
