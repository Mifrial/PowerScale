import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';

export function useCharacterDraft() {
  return useCharacterDraftStore();
}
