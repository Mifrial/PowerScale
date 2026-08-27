import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { registerRuleApi, registerRevisionRulesFetcher } from '@/modules/Roleplay/Rule/init';
import { mockSpaceApi } from '@/modules/Roleplay/Space/Mock/mockSpaceApi';
import { mockRuleApi } from '@/modules/Roleplay/Rule/Mock/mockRuleApi';
import { ruleRevisionResolverService } from '@/modules/Roleplay/Rule/Service/Instance/ruleRevisionResolverService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

beforeEach(() => {
  setActivePinia(createPinia());
  serviceLocator.reset();
  registerRuleApi(mockRuleApi);
  registerRevisionRulesFetcher({
    fetchRules: async (spaceId, revision, signal) => {
      const slice = await mockSpaceApi.getRevision(spaceId, revision, signal);

      return slice.rules;
    },
  });
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

    expect(ruleRevisionResolverService.findRuleInRevision(rules, 'movement')?.id).toBe('r-1');
  });

  it('ищет по id (глобальный ключ)', () => {
    const rules = [rule('r-1', 'movement')];

    expect(ruleRevisionResolverService.findRuleInRevision(rules, 'r-1')?.code).toBe('movement');
  });

  it('возвращает null, если правила нет в срезе', () => {
    expect(ruleRevisionResolverService.findRuleInRevision([rule('r-1', 'movement')], 'absent')).toBeNull();
  });
});

describe('resolveRuleFromRevision', () => {
  it('берёт правило из среза ревизии по коду (версия ревизии, не каталог)', async () => {
    const resolved = await ruleRevisionResolverService.resolveRuleFromRevision({
      spaceId: 1,
      rulesRevision: 5,
      ruleId: 'dodge',
    });

    expect(resolved).not.toBeNull();
    expect(resolved?.code).toBe('dodge');
    // Ревизия перезаписывает spaceId — это правило из среза, а не каталога.
    expect(resolved?.spaceId).toBe(1);
  });

  it('правила нет в срезе (выведено из обращения) — фолбэк на каталог по id', async () => {
    // night-vision выведено из обращения на ревизиях ≥ 8 (mockSpaces).
    const resolved = await ruleRevisionResolverService.resolveRuleFromRevision({
      spaceId: 1,
      rulesRevision: 12,
      ruleId: 'rule-26',
    });

    expect(resolved).not.toBeNull();
    expect(resolved?.code).toBe('night-vision');
  });

  it('без контекста ревизии резолвит из глобального каталога', async () => {
    const resolved = await ruleRevisionResolverService.resolveRuleFromRevision({
      spaceId: null,
      rulesRevision: null,
      ruleId: 'rule-6',
    });

    expect(resolved?.code).toBe('human');
  });

  it('ruleId null → null без обращения к API', async () => {
    const resolved = await ruleRevisionResolverService.resolveRuleFromRevision({
      spaceId: 1,
      rulesRevision: 5,
      ruleId: null,
    });

    expect(resolved).toBeNull();
  });
});

describe('resolveRevisionSlice', () => {
  it('отдаёт найденное правило вместе со срезом ревизии', async () => {
    const slice = await ruleRevisionResolverService.resolveRevisionSlice({
      spaceId: 1,
      rulesRevision: 5,
      ruleId: 'dodge',
    });

    expect(slice.rule?.code).toBe('dodge');
    expect(slice.rules.length).toBeGreaterThan(1);
    expect(slice.rules.some((entry) => entry.code === 'dodge')).toBe(true);
  });

  it('чип чата передаёт code — находит в срезе актуальных правил', async () => {
    const slice = await ruleRevisionResolverService.resolveRevisionSlice({
      spaceId: 2,
      rulesRevision: 12,
      ruleId: 'magic-resistance',
    });

    expect(slice.rule?.code).toBe('magic-resistance');
    expect(slice.rule?.name).toContain('Сопротивление магии');
  });

  it('без ревизии резолвит каталог по code, а не только по id', async () => {
    const slice = await ruleRevisionResolverService.resolveRevisionSlice({
      spaceId: null,
      rulesRevision: null,
      ruleId: 'magic-resistance',
    });

    expect(slice.rule?.code).toBe('magic-resistance');
  });
});
