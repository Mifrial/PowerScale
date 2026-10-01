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
  let detailRequestSequence = 0;
  let listRequestSequence = 0;

  async function fetchCharacters(signal?: AbortSignal) {
    const requestSequence = ++listRequestSequence;
    loading.value = true;
    error.value = null;
    try {
      const list = await getCharacterApi().getCharacters(signal);
      if (requestSequence !== listRequestSequence) return;
      characters.value = list;
    } catch (e) {
      if (requestSequence !== listRequestSequence) return;
      if (e instanceof DOMException && e.name === 'AbortError') return;
      error.value = 'Не удалось загрузить персонажей';
    } finally {
      if (requestSequence === listRequestSequence) loading.value = false;
    }
  }

  async function fetchCharacter(id: number, signal?: AbortSignal): Promise<CharacterDetail | null> {
    const requestSequence = ++detailRequestSequence;
    detailLoading.value = true;
    detailError.value = null;
    try {
      const detail = await getCharacterApi().getCharacter(id, signal);
      if (requestSequence === detailRequestSequence) currentCharacter.value = detail;

      return detail;
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return null;
      if (requestSequence === detailRequestSequence) detailError.value = 'Не удалось загрузить персонажа';

      return null;
    } finally {
      if (requestSequence === detailRequestSequence) detailLoading.value = false;
    }
  }

  function clearCurrent() {
    detailRequestSequence += 1;
    currentCharacter.value = null;
  }

  function applyDetail(detail: CharacterDetail) {
    detailRequestSequence += 1;
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
