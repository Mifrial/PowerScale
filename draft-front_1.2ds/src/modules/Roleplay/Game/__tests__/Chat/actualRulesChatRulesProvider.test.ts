import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { resetRegisteredApis } from '@/modules/Core/Engine/init';
import { registerRuleSpaceApi, ACTUAL_RULES_SPACE_CODE } from '@/modules/Roleplay/RuleSpace/init';
import { registerMechanicApi } from '@/modules/Roleplay/Mechanic/init';
import { registerRuleApi } from '@/modules/Roleplay/Rule/init';
import { mockMechanicApi } from '@/modules/Roleplay/Mechanic/Mock/mockMechanicApi';
import { mockRuleSpaceApi } from '@/modules/Roleplay/RuleSpace/Mock/mockRuleSpaceApi';
import { mockRuleApi } from '@/modules/Roleplay/Rule/Mock/mockRuleApi';
import { actualRulesChatRulesProvider } from '@/modules/Roleplay/Game/Chat/actualRulesChatRulesProvider';

beforeEach(() => {
  setActivePinia(createPinia());
  resetRegisteredApis();
});

describe('actualRulesChatRulesProvider', () => {
  it('резолвит срез пространства актуальных правил', async () => {
    registerRuleSpaceApi(mockRuleSpaceApi);
    registerRuleApi(mockRuleApi);
    registerMechanicApi(mockMechanicApi);

    const context = await actualRulesChatRulesProvider.resolve('private', 1);

    expect(context).not.toBeNull();
    expect(context?.spaceId).toBe(2);
    expect(context?.rulesRevision).toBe(12);
    expect(Object.keys(context?.tokenLabels ?? {}).length).toBeGreaterThan(0);
    const rules = await context!.tokenSources.find((source) => source.type === 'rule')?.search('');
    expect(rules?.length).toBeGreaterThan(0);
    expect(ACTUAL_RULES_SPACE_CODE).toBe('actual');
  });
});
