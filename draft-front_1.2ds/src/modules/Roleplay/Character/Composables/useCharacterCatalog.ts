import { useCharacterStore } from '@/modules/Roleplay/Character/Store/characters';

export function useCharacterCatalog() {
  return useCharacterStore();
}
