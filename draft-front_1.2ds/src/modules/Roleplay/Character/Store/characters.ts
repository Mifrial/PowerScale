import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { Character } from '@/modules/Roleplay/Character/Dto/Character';
import type { CharacterDetail } from '@/modules/Roleplay/Character/Dto/CharacterDetail';
import { getCharacterApi } from '@/modules/Roleplay/Character/init';

export const useCharacterStore = defineStore('characters', () => {
  const characters = ref<Character[]>([]);
  const currentCharacter = ref<CharacterDetail | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);
  const detailLoading = ref(false);
  const detailError = ref<string | null>(null);

  async function fetchCharacters(signal?: AbortSignal) {
    loading.value = true;
    error.value = null;
    try {
      characters.value = await getCharacterApi().getCharacters(signal);
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return;
      error.value = 'Не удалось загрузить персонажей';
    } finally {
      loading.value = false;
    }
  }

  async function fetchCharacter(id: number, signal?: AbortSignal): Promise<CharacterDetail | null> {
    detailLoading.value = true;
    detailError.value = null;
    try {
      const detail = await getCharacterApi().getCharacter(id, signal);
      currentCharacter.value = detail;

      return detail;
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return null;
      detailError.value = 'Не удалось загрузить персонажа';

      return null;
    } finally {
      detailLoading.value = false;
    }
  }

  function clearCurrent() {
    currentCharacter.value = null;
  }

  function applyDetail(detail: CharacterDetail) {
    currentCharacter.value = detail;
  }

  return {
    characters,
    currentCharacter,
    loading,
    error,
    detailLoading,
    detailError,
    fetchCharacters,
    fetchCharacter,
    clearCurrent,
    applyDetail,
  };
});
