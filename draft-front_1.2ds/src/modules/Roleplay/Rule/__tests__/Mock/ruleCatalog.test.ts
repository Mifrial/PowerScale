import { describe, it, expect } from 'vitest';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';

describe('ruleCatalog', () => {
  it('id уникальны (глобальная идентичность правила)', () => {
    const ids = ruleCatalog.map((r) => r.id);
    expect(new Set(ids).size).toBe(ids.length);
  });

  it('code уникален и не пуст (глобальный семантический ключ)', () => {
    const codes = ruleCatalog.map((r) => r.code);
    expect(codes.length).toBeGreaterThan(0);
    expect(new Set(codes).size).toBe(codes.length);
    for (const c of codes) expect(c.trim()).toBeTruthy();
  });

  it('содержит все типы правил', () => {
    const types = new Set(ruleCatalog.map((r) => r.type));
    for (const t of [
      'simple',
      'race',
      'species',
      'characteristic',
      'resource',
      'points',
      'ability',
      'item',
      'item_modifier',
      'item_modifier_type',
      'damage_type',
      'source',
      'state',
      'poison',
    ]) {
      expect(types.has(t as never)).toBe(true);
    }
  });

  it('способности с type process/spell содержат соответствующие спеки', () => {
    const movement = ruleCatalog.find((r) => r.code === 'movement');
    expect(movement?.type).toBe('ability');
    expect((movement?.spec as { type?: string } | undefined)?.type).toBe('process');

    const fireBolt = ruleCatalog.find((r) => r.code === 'fire-bolt');
    expect((fireBolt?.spec as { type?: string } | undefined)?.type).toBe('spell');
  });
});
