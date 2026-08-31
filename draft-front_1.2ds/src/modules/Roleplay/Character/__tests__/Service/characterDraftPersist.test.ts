import { describe, expect, it, beforeEach } from 'vitest';
import { characterDraftPersistService } from '@/modules/Roleplay/Character/Service/Instance/characterDraftPersistService';
import { CHARACTER_DRAFT_STORAGE_KEY } from '@/modules/Roleplay/Character/Constant/characterDraftConfig';

describe('CharacterDraftPersistService', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('битый JSON сбрасывает ключ и помечает discarded', () => {
    localStorage.setItem(CHARACTER_DRAFT_STORAGE_KEY, '{not json');
    const result = characterDraftPersistService.read();
    expect(result.entries).toEqual([]);
    expect(result.discarded).toBe(true);
    expect(localStorage.getItem(CHARACTER_DRAFT_STORAGE_KEY)).toBeNull();
  });

  it('запись без build отбрасывается', () => {
    localStorage.setItem(CHARACTER_DRAFT_STORAGE_KEY, JSON.stringify([{ draftKey: 'character:1', config: {} }]));
    const result = characterDraftPersistService.read();
    expect(result.entries).toEqual([]);
    expect(result.discarded).toBe(true);
  });

  it('инвентарь с legacy ruleId читается как ruleCode', () => {
    localStorage.setItem(
      CHARACTER_DRAFT_STORAGE_KEY,
      JSON.stringify([
        {
          draftKey: 'character:3',
          config: { osTotal: 20, orTotal: 10, moneyBudget: 100 },
          dirty: false,
          updatedAt: '2026-08-31T00:00:00Z',
          build: {
            name: 'Гаррик',
            shortDescription: null,
            fullDescription: null,
            spaceId: 2,
            spaceCode: 'actual',
            rulesRevision: 6,
            raceRuleId: 'rule-122',
            characteristicPurchases: [],
            abilities: [],
            resources: [],
            inventory: [
              { id: 1, ruleId: 'rule-407', quantity: 1, equipped: true },
              { id: 2, ruleCode: null, name: 'Камень', quantity: 1, equipped: false },
            ],
            states: [],
            money: 50,
            ageYears: null,
            olTotal: 7,
          },
          inventoryBaseline: {
            money: 50,
            inventory: [{ id: 1, ruleId: 'rule-407', quantity: 1, equipped: true }],
          },
        },
      ]),
    );

    const result = characterDraftPersistService.read();
    const entry = result.entries[0];
    expect(result.discarded).toBe(false);
    expect(entry?.build.raceRuleCode).toBe('rule-122');
    expect(entry?.build.inventory).toMatchObject([
      { id: 1, ruleCode: 'rule-407', quantity: 1, equipped: true },
      { id: 2, ruleCode: null, name: 'Камень', quantity: 1, equipped: false },
    ]);
    expect(entry?.inventoryBaseline?.inventory[0]?.ruleCode).toBe('rule-407');
  });
});
