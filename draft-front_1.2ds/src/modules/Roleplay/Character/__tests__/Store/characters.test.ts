import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { registerCharacterApi } from '@/modules/Roleplay/Character/init';
import { mockCharacterApi } from '@/modules/Roleplay/Character/Mock/mockCharacterApi';
import { resetRegisteredApis } from '@/modules/Core/Engine/init';
import { useCharacterStore } from '@/modules/Roleplay/Character/Store/characters';
import type { CharacterDetail } from '@/modules/Roleplay/Character/Dto/CharacterDetail';
import type { ICharacterApi } from '@/modules/Roleplay/Character/Interface/ICharacterApi';

describe('useCharacterStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    resetRegisteredApis();
  });

  it('does not let an older detail response overwrite the current entity', async () => {
    const firstDetail = await mockCharacterApi.getCharacter(1);
    const secondDetail = await mockCharacterApi.getCharacter(2);
    let resolveFirst = (_detail: CharacterDetail): void => undefined;
    let resolveSecond = (_detail: CharacterDetail): void => undefined;
    let requestCount = 0;
    const getCharacter: ICharacterApi['getCharacter'] = () => {
      requestCount += 1;

      return requestCount === 1
        ? new Promise((resolve) => (resolveFirst = resolve))
        : new Promise((resolve) => (resolveSecond = resolve));
    };

    registerCharacterApi({ ...mockCharacterApi, getCharacter });
    const store = useCharacterStore();
    const firstRequest = store.fetchCharacter(1);
    const secondRequest = store.fetchCharacter(2);

    resolveSecond?.(secondDetail);
    resolveFirst?.(firstDetail);
    await Promise.all([firstRequest, secondRequest]);

    expect(store.currentCharacter?.character.id).toBe(secondDetail.character.id);
  });
});
