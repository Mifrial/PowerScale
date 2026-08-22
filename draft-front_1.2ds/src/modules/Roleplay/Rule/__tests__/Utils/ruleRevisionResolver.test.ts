import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { registerSpaceApi } from '@/modules/Roleplay/Space/init';
import { registerRuleApi } from '@/modules/Roleplay/Rule/init';
import { mockSpaceApi } from '@/modules/Roleplay/Space/Mock/mockSpaceApi';
import { mockRuleApi } from '@/modules/Roleplay/Rule/Mock/mockRuleApi';
import { findRuleInRevision, resolveRuleFromRevision } from '@/modules/Roleplay/Rule/Utils/ruleRevisionResolver';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

beforeEach(() => {
  setActivePinia(createPinia());
  serviceLocator.reset();
  registerSpaceApi(mockSpaceApi);
  registerRuleApi(mockRuleApi);
});

function rule(id: string, code: string): Rule {
  return {
    id,
    code,
    type: 'simple',
    name: code,
    description: '',
    spaceId: 1,
    createdAt: '2026-01-01T00:00:00Z',
  };
}

describe('findRuleInRevision', () => {
  it('ищет по коду (ключ среза ревизии)', () => {
    const rules = [rule('r-1', 'movement')];

    expect(findRuleInRevision(rules, 'movement')?.id).toBe('r-1');
  });

  it('ищет по id (глобальный ключ)', () => {
    const rules = [rule('r-1', 'movement')];

    expect(findRuleInRevision(rules, 'r-1')?.code).toBe('movement');
  });

  it('возвращает null, если правила нет в срезе', () => {
    expect(findRuleInRevision([rule('r-1', 'movement')], 'absent')).toBeNull();
  });
});

describe('resolveRuleFromRevision', () => {
  it('берёт правило из среза ревизии по коду (версия ревизии, не каталог)', async () => {
    const resolved = await resolveRuleFromRevision({
      spaceId: 1,
      rulesRevision: 5,
      ruleId: 'movement',
    });

    expect(resolved).not.toBeNull();
    expect(resolved?.code).toBe('movement');
    // Ревизия перезаписывает spaceId — это правило из среза, а не каталога.
    expect(resolved?.spaceId).toBe(1);
  });

  it('правила нет в срезе (выведено из обращения) — фолбэк на каталог по id', async () => {
    // night-vision выведено из обращения на ревизиях ≥ 8 (mockSpaces).
    const resolved = await resolveRuleFromRevision({
      spaceId: 1,
      rulesRevision: 12,
      ruleId: 'rule-26',
    });

    expect(resolved).not.toBeNull();
    expect(resolved?.code).toBe('night-vision');
  });

  it('без контекста ревизии резолвит из глобального каталога', async () => {
    const resolved = await resolveRuleFromRevision({
      spaceId: null,
      rulesRevision: null,
      ruleId: 'rule-33',
    });

    expect(resolved?.code).toBe('movement');
  });

  it('ruleId null → null без обращения к API', async () => {
    const resolved = await resolveRuleFromRevision({ spaceId: 1, rulesRevision: 5, ruleId: null });

    expect(resolved).toBeNull();
  });
});
