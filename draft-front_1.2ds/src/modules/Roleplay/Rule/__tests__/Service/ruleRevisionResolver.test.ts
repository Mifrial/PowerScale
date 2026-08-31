import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { resetRegisteredApis } from '@/modules/Core/Engine/init';
import { registerRuleApi, registerRevisionRulesFetcher } from '@/modules/Roleplay/Rule/init';
import { mockSpaceApi } from '@/modules/Roleplay/Space/Mock/mockSpaceApi';
import { mockRuleApi } from '@/modules/Roleplay/Rule/Mock/mockRuleApi';
import { ruleRevisionResolverService } from '@/modules/Roleplay/Rule/Service/Instance/ruleRevisionResolverService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

beforeEach(() => {
  setActivePinia(createPinia());
  resetRegisteredApis();
  registerRuleApi(mockRuleApi);
  registerRevisionRulesFetcher({
    fetchRules: async (spaceId, revision, signal) => {
      const slice = await mockSpaceApi.getRevision(spaceId, revision, signal);

      return slice.rules;
    },
  });
});

function rule(id: number | null, code: string): Rule {
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
  it('ищет только по semantic code', () => {
    const rules = [rule(null, 'movement')];

    expect(ruleRevisionResolverService.findRuleInRevision(rules, 'movement')?.code).toBe('movement');
    expect(ruleRevisionResolverService.findRuleInRevision(rules, 'r-1')).toBeNull();
  });

  it('возвращает null, если правила нет в срезе', () => {
    expect(ruleRevisionResolverService.findRuleInRevision([rule(null, 'movement')], 'absent')).toBeNull();
  });
});

describe('resolveRuleFromRevision', () => {
  it('берёт правило из среза ревизии по коду (версия ревизии, не каталог)', async () => {
    const resolved = await ruleRevisionResolverService.resolveRuleFromRevision({
      spaceId: 1,
      rulesRevision: 5,
      ruleCode: 'dodge',
    });

    expect(resolved).not.toBeNull();
    expect(resolved?.code).toBe('dodge');
    expect(resolved?.spaceId).toBe(1);
  });

  it('правила нет в срезе — фолбэк на каталог по code', async () => {
    const resolved = await ruleRevisionResolverService.resolveRuleFromRevision({
      spaceId: 1,
      rulesRevision: 12,
      ruleCode: 'night-vision',
    });

    expect(resolved).not.toBeNull();
    expect(resolved?.code).toBe('night-vision');
  });

  it('слайдер со storage id не резолвит правило по numeric/строковому PK', async () => {
    const resolved = await ruleRevisionResolverService.resolveRuleFromRevision({
      spaceId: 1,
      rulesRevision: 12,
      ruleCode: '__storage-pk-not-a-code__',
    });

    expect(resolved).toBeNull();
  });

  it('без контекста ревизии резолвит из глобального каталога по code', async () => {
    const resolved = await ruleRevisionResolverService.resolveRuleFromRevision({
      spaceId: null,
      rulesRevision: null,
      ruleCode: 'human',
    });

    expect(resolved?.code).toBe('human');
  });

  it('ruleCode null → null без обращения к API', async () => {
    const resolved = await ruleRevisionResolverService.resolveRuleFromRevision({
      spaceId: 1,
      rulesRevision: 5,
      ruleCode: null,
    });

    expect(resolved).toBeNull();
  });
});

describe('resolveRevisionSlice', () => {
  it('отдаёт найденное правило вместе со срезом ревизии', async () => {
    const slice = await ruleRevisionResolverService.resolveRevisionSlice({
      spaceId: 1,
      rulesRevision: 5,
      ruleCode: 'dodge',
    });

    expect(slice.rule?.code).toBe('dodge');
    expect(slice.rules.length).toBeGreaterThan(1);
    expect(slice.rules.some((entry) => entry.code === 'dodge')).toBe(true);
  });

  it('чип чата передаёт code — находит в срезе актуальных правил', async () => {
    const slice = await ruleRevisionResolverService.resolveRevisionSlice({
      spaceId: 2,
      rulesRevision: 12,
      ruleCode: 'magic-resistance',
    });

    expect(slice.rule?.code).toBe('magic-resistance');
    expect(slice.rule?.name).toContain('Сопротивление магии');
  });

  it('без ревизии резолвит каталог по code', async () => {
    const slice = await ruleRevisionResolverService.resolveRevisionSlice({
      spaceId: null,
      rulesRevision: null,
      ruleCode: 'magic-resistance',
    });

    expect(slice.rule?.code).toBe('magic-resistance');
  });
});
