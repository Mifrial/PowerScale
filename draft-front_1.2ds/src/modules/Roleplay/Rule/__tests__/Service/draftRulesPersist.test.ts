import { describe, expect, it, beforeEach } from 'vitest';
import { draftRulesPersistService } from '@/modules/Roleplay/Rule/Service/Instance/draftRulesPersistService';
import { DRAFT_RULES_STORAGE_KEY } from '@/modules/Roleplay/Rule/Constant/draftRulesConfig';

describe('DraftRulesPersistService', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('битый JSON сбрасывает ключ и помечает discarded', () => {
    localStorage.setItem(DRAFT_RULES_STORAGE_KEY, '{not json');
    const result = draftRulesPersistService.read();
    expect(result.entries).toEqual([]);
    expect(result.discarded).toBe(true);
    expect(localStorage.getItem(DRAFT_RULES_STORAGE_KEY)).toBeNull();
  });

  it('невалидные элементы отбрасывает, валидные оставляет', () => {
    localStorage.setItem(
      DRAFT_RULES_STORAGE_KEY,
      JSON.stringify([
        { foo: 1 },
        {
          spaceId: 1,
          changedRules: {
            r1: {
              id: 'r1',
              code: 'c1',
              type: 'simple',
              name: 'Правило',
              description: '',
              spaceId: 1,
              createdAt: '2026-08-02T00:00:00Z',
            },
          },
        },
      ]),
    );
    const result = draftRulesPersistService.read();
    expect(result.discarded).toBe(true);
    expect(result.entries).toHaveLength(1);
    expect(result.entries[0]?.spaceId).toBe(1);
  });
});
