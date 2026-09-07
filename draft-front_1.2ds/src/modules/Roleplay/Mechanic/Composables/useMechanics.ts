import { computed } from 'vue';
import { useMechanicStore } from '@/modules/Roleplay/Mechanic/Store/mechanics';

export function useMechanics() {
  const store = useMechanicStore();

  return {
    mechanics: computed(() => store.mechanics),
    error: computed(() => store.error),
    fetchMechanics: (signal?: AbortSignal) => store.fetchMechanics(signal),
  };
}
