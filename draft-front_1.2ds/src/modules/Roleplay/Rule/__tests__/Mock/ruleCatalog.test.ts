import { describe, it, expect } from 'vitest';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { damageTypeSpecService } from '@/modules/Roleplay/Rule/Service/Instance/damageTypeSpecService';

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
      'check',
    ]) {
      expect(types.has(t as never)).toBe(true);
    }
  });

  it('способности с type process содержат спеку процесса', () => {
    const process = ruleCatalog.find(
      (r) => r.type === 'ability' && (r.spec as { type?: string } | undefined)?.type === 'process',
    );
    expect(process?.spec).toBeDefined();
    expect((process?.spec as { process?: unknown }).process).toBeDefined();
    const steps = ((process?.spec as { process?: { steps?: Array<{ interruption?: unknown }> } }).process?.steps ?? []);
    expect(steps.length).toBeGreaterThan(0);
    expect(steps.every((step) => step.interruption)).toBe(true);
  });

  it('у каждого типа урона есть спека со склонениями', () => {
    const types = ruleCatalog.filter((rule) => rule.type === 'damage_type');
    expect(types.length).toBeGreaterThan(0);
    for (const rule of types) {
      const spec = damageTypeSpecService.asDamageTypeSpec(rule);
      expect(spec).not.toBeNull();
      expect(spec?.forms.genitive.trim()).toBeTruthy();
      expect(spec?.forms.dative.trim()).toBeTruthy();
    }
  });

  it('содержит дерево проверок', () => {
    expect(ruleCatalog.some((r) => r.code === 'check-simple' && r.type === 'check')).toBe(true);
    expect(ruleCatalog.some((r) => r.code === 'check-injury' && r.type === 'check')).toBe(true);
    expect(ruleCatalog.some((r) => r.code === 'strike-procedure' && r.mechanicId === 7)).toBe(true);
    expect(ruleCatalog.some((r) => r.code === 'throw-procedure' && r.mechanicId === 16)).toBe(true);
    expect(ruleCatalog.some((r) => r.code === 'shoot-procedure' && r.mechanicId === 17)).toBe(true);
    expect(ruleCatalog.some((r) => r.code === 'flanking-attack' && r.type === 'simple')).toBe(true);
    expect(ruleCatalog.some((r) => r.code === 'turn' && r.type === 'ability')).toBe(true);
    expect(ruleCatalog.some((r) => r.code === 'deception' && r.type === 'check')).toBe(true);
  });

  it('содержит версионируемое действие ожидания с выбираемой стоимостью', () => {
    const wait = ruleCatalog.find((rule) => rule.code === 'wait');
    const component = (wait?.spec as { action_components?: Array<{ amount: unknown }> } | undefined)
      ?.action_components?.[0];

    expect(wait?.catalogSection).toBe('scenes-combat-other');
    expect(component?.amount).toEqual({ type: 'chosen', max: 'available' });
  });
});
