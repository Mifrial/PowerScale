import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { registerCharacterApi } from '@/modules/Roleplay/Character/init';
import { registerSpaceApi } from '@/modules/Roleplay/Space/init';
import { mockCharacterApi } from '@/modules/Roleplay/Character/Mock/mockCharacterApi';
import { mockSpaceApi } from '@/modules/Roleplay/Space/Mock/mockSpaceApi';
import { characterChatRulesProvider } from '@/modules/Roleplay/Character/Chat/characterChatRulesProvider';
import type { ICharacterApi } from '@/modules/Roleplay/Character/Interface/ICharacterApi';

beforeEach(() => {
  setActivePinia(createPinia());
  serviceLocator.reset();
});

describe('characterChatRulesProvider', () => {
  it('резолвит ревизию по карточке списка, без getCharacter', async () => {
    const getCharacter = vi.fn(mockCharacterApi.getCharacter);
    const api: ICharacterApi = { ...mockCharacterApi, getCharacter };
    registerCharacterApi(api);
    registerSpaceApi(mockSpaceApi);

    const context = await characterChatRulesProvider.resolve('character_discussion', 7);

    expect(getCharacter).not.toHaveBeenCalled();
    expect(context).not.toBeNull();
    expect(context?.spaceId).toBe(1);
    expect(context?.rulesRevision).toBe(5);
    expect(context?.tokenLabels['movement']).toBeTruthy();
  });

  it('неизвестный чат — null без деталок', async () => {
    const getCharacter = vi.fn(mockCharacterApi.getCharacter);
    registerCharacterApi({ ...mockCharacterApi, getCharacter });
    registerSpaceApi(mockSpaceApi);

    await expect(characterChatRulesProvider.resolve('character_discussion', 999_999)).resolves.toBeNull();
    expect(getCharacter).not.toHaveBeenCalled();
  });
});
