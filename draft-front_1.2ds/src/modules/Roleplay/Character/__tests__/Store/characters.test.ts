import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { registerCharacterApi } from '@/modules/Roleplay/Character/init';
import { mockCharacterApi } from '@/modules/Roleplay/Character/Mock/mockCharacterApi';
import { resetRegisteredApis } from '@/modules/Core/Engine/init';
import { useCharacterStore } from '@/modules/Roleplay/Character/Store/characters';
import type { Character } from '@/modules/Roleplay/Character/Dto/Character';
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

  it('does not let an older list response overwrite the current list or clear its loading', async () => {
    const firstList = await mockCharacterApi.getCharacters();
    const secondList = firstList.slice(0, 1);
    let resolveFirst = (_list: Character[]): void => undefined;
    let resolveSecond = (_list: Character[]): void => undefined;
    let requestCount = 0;
    const getCharacters: ICharacterApi['getCharacters'] = () => {
      requestCount += 1;

      return requestCount === 1
        ? new Promise((resolve) => (resolveFirst = resolve))
        : new Promise((resolve) => (resolveSecond = resolve));
    };

    registerCharacterApi({ ...mockCharacterApi, getCharacters });
    const store = useCharacterStore();
    const firstRequest = store.fetchCharacters();
    const secondRequest = store.fetchCharacters();

    resolveSecond?.(secondList);
    await secondRequest;
    expect(store.loading).toBe(false);
    expect(store.characters).toEqual(secondList);

    resolveFirst?.(firstList);
    await firstRequest;

    expect(store.characters).toEqual(secondList);
    expect(store.loading).toBe(false);
  });

  it('does not let an aborted older list request replace a newer error', async () => {
    let rejectFirst = (_error: DOMException): void => undefined;
    let rejectSecond = (_error: Error): void => undefined;
    let requestCount = 0;
    const getCharacters: ICharacterApi['getCharacters'] = () => {
      requestCount += 1;

      return requestCount === 1
        ? new Promise((_resolve, reject) => (rejectFirst = reject))
        : new Promise((_resolve, reject) => (rejectSecond = reject));
    };

    registerCharacterApi({ ...mockCharacterApi, getCharacters });
    const store = useCharacterStore();
    const firstRequest = store.fetchCharacters();
    const secondRequest = store.fetchCharacters();

    rejectSecond?.(new Error('list failed'));
    await secondRequest;
    expect(store.error).toBe('Не удалось загрузить персонажей');

    rejectFirst?.(new DOMException('aborted', 'AbortError'));
    await firstRequest;

    expect(store.error).toBe('Не удалось загрузить персонажей');
  });
});
