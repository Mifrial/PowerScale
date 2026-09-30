import { computed, ref } from 'vue';
import type { GameLifecycleResult } from '@/modules/Roleplay/Game/Dto/GameLifecycleResult';
import type { GameStateSnapshot } from '@/modules/Roleplay/Game/Dto/GameStateSnapshot';

export function useGameStateSnapshot() {
  const snapshot = ref<GameStateSnapshot | null>(null);
  const lastLifecycleResult = ref<GameLifecycleResult | null>(null);

  const isSessionActive = computed(() => snapshot.value?.session?.status === 'active');
  const isBattleActive = computed(() => snapshot.value?.battle?.status === 'active');
  const activeBattleId = computed(() => snapshot.value?.session?.activeBattleId ?? null);
  const isBattleEnded = computed(
    () =>
      lastLifecycleResult.value?.kind === 'transition' && lastLifecycleResult.value.transition.type === 'battle_ended',
  );
  const isSessionStopped = computed(
    () =>
      lastLifecycleResult.value?.kind === 'transition' &&
      lastLifecycleResult.value.transition.type === 'session_stopped',
  );

  function applySnapshot(nextSnapshot: GameStateSnapshot): void {
    snapshot.value = nextSnapshot;
    lastLifecycleResult.value = null;
  }

  function applyLifecycleResult(result: GameLifecycleResult): void {
    snapshot.value = result.snapshot;
    lastLifecycleResult.value = result;
  }

  return {
    snapshot,
    lastLifecycleResult,
    isSessionActive,
    isBattleActive,
    activeBattleId,
    isBattleEnded,
    isSessionStopped,
    applySnapshot,
    applyLifecycleResult,
  };
}
