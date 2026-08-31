import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';

export function useSpaceRevision() {
  const store = useSpaceRevisionStore();

  return {
    fetchRevision: store.fetchRevision,
    fetchRevisionsMeta: store.fetchRevisionsMeta,
  };
}
