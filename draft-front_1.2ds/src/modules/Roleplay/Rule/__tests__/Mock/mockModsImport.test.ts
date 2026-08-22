import { describe, it, expect } from 'vitest';
import { mockModsImport } from '@/modules/Roleplay/Rule/Mock/mockModsImport';
import { keywords as mockKeywords } from '@/modules/Roleplay/Rule/Mock/mockKeywords';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { ruleValidationService } from '@/modules/Roleplay/Rule/Service/Instance/ruleValidationService';
import type { ItemModifierSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierSpec';

const kwId = (code: string): number => {
  const keyword = mockKeywords.find((entry) => entry.code === code);
  expect(keyword, `keyword ${code}`).toBeDefined();

  return keyword!.id;
};

describe('mockModsImport (R29)', () => {
  it('уникальные id и code; все правила в ruleCatalog', () => {
    const ids = mockModsImport.map((rule) => rule.id);
    const codes = mockModsImport.map((rule) => rule.code);
    expect(new Set(ids).size).toBe(ids.length);
    expect(new Set(codes).size).toBe(codes.length);
    expect(mockModsImport.length).toBe(32);
    for (const rule of mockModsImport) {
      expect(rule.type).toBe('item_modifier');
      expect(ruleCatalog.some((entry) => entry.id === rule.id)).toBe(true);
      const spec = rule.spec as ItemModifierSpec;
      expect(spec.type_code.length).toBeGreaterThan(0);
      expect(ruleCatalog.some((entry) => entry.type === 'item_modifier_type' && entry.code === spec.type_code)).toBe(
        true,
      );
    }
  });

  it('применимость ссылается на существующие keywords; оковка только посох', () => {
    for (const rule of mockModsImport) {
      expect(rule.keywordIds ?? []).toEqual([]);
      const spec = rule.spec as ItemModifierSpec;
      const codes = [
        ...(spec.applies.keyword_all ?? []),
        ...(spec.applies.keyword_any ?? []),
        ...(spec.applies.keyword_none ?? []),
        ...Object.keys(spec.price.by_keyword ?? {}),
      ];
      for (const code of codes) {
        expect(
          mockKeywords.some((keyword) => keyword.code === code),
          code,
        ).toBe(true);
      }
    }
    const ferrule = mockModsImport.find((rule) => rule.code === 'steel-ferrule');
    expect((ferrule?.spec as ItemModifierSpec).applies.keyword_all).toEqual(['weapon', 'staff']);
    expect((ferrule?.spec as ItemModifierSpec).type_code).toBe('ferrule');
    const silver = mockModsImport.find((rule) => rule.code === 'silver');
    const leather = mockModsImport.find((rule) => rule.code === 'lightning-snake-leather');
    expect((silver?.spec as ItemModifierSpec).type_code).toBe('material');
    expect((leather?.spec as ItemModifierSpec).type_code).toBe('material');
    expect(kwId('improvable')).toBe(210);
  });

  it('структурная валидация модификаторов без ошибок', () => {
    const errors = ruleValidationService.validateItemModifierStructure(mockModsImport);
    expect(errors).toEqual([]);
  });
});
