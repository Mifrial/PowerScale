import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { resetRegisteredApis } from '@/modules/Core/Engine/init';
import { registerCharacterApi } from '@/modules/Roleplay/Character/init';
import { registerRuleSpaceApi } from '@/modules/Roleplay/RuleSpace/init';
import { mockCharacterApi } from '@/modules/Roleplay/Character/Mock/mockCharacterApi';
import { mockRuleSpaceApi } from '@/modules/Roleplay/RuleSpace/Mock/mockRuleSpaceApi';
import { characterChatRulesProvider } from '@/modules/Roleplay/Character/Chat/characterChatRulesProvider';
import type { ICharacterApi } from '@/modules/Roleplay/Character/Interface/ICharacterApi';

beforeEach(() => {
  setActivePinia(createPinia());
  resetRegisteredApis();
});

describe('characterChatRulesProvider', () => {
  it('резолвит ревизию по карточке списка, без getCharacter', async () => {
    const getCharacter = vi.fn(mockCharacterApi.getCharacter);
    const api: ICharacterApi = { ...mockCharacterApi, getCharacter };
    registerCharacterApi(api);
    registerRuleSpaceApi(mockRuleSpaceApi);

    const context = await characterChatRulesProvider.resolve('character_discussion', 7);

    expect(getCharacter).not.toHaveBeenCalled();
    expect(context).not.toBeNull();
    expect(context?.spaceId).toBe(1);
    expect(context?.rulesRevision).toBe(5);
    expect(context?.tokenLabels['dodge']).toBeTruthy();
  });

  it('неизвестный чат — null без деталок', async () => {
    const getCharacter = vi.fn(mockCharacterApi.getCharacter);
    registerCharacterApi({ ...mockCharacterApi, getCharacter });
    registerRuleSpaceApi(mockRuleSpaceApi);

    await expect(characterChatRulesProvider.resolve('character_discussion', 999_999)).resolves.toBeNull();
    expect(getCharacter).not.toHaveBeenCalled();
  });
});
