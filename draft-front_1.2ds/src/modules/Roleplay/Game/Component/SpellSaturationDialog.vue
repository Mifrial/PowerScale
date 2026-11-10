<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import SpellSaturationPanel from '@/modules/Roleplay/Game/Component/SpellSaturationPanel.vue';
import type { GamePhpPendingSpellCast } from '@/modules/Roleplay/Game/Dto/GamePhpPendingSpellCast';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { findRuleByRef } from '@/modules/Roleplay/Game/Utils/combatActions';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { gamePhpBattleContext } from '@/modules/Roleplay/Game/Service/Instance/gamePhpBattleContext';

const props = defineProps<{
  open: boolean;
  pending: GamePhpPendingSpellCast | null;
  gameId: number;
  rules: Rule[];
  canResolve: boolean;
  canAbort: boolean;
}>();

const emit = defineEmits<{
  (event: 'update:open', value: boolean): void;
  (event: 'resolved'): void;
  (event: 'aborted'): void;
}>();

const steps = ref(0);
const busy = ref(false);
const error = ref<string | null>(null);
const stale = ref(false);

const saturation = computed(() => props.pending?.saturation ?? null);
const abortAllowed = computed(() => props.canAbort || stale.value);
const sourceName = computed(() => {
  const code = saturation.value?.sourceCode;
  if (!code) return 'Энергонасыщение';

  return findRuleByRef(props.rules, code)?.name ?? code;
});

watch(
  () => [props.open, props.pending?.castId],
  () => {
    steps.value = 0;
    error.value = null;
    stale.value = false;
  },
);

async function apply(stepsToSend: number | null = null): Promise<void> {
  const pending = props.pending;
  const battleId = gamePhpBattleContext.battleId;
  if (!pending || !props.canResolve || busy.value || battleId === null) return;
  const chosen = Math.min(stepsToSend ?? steps.value, saturation.value?.maxSteps ?? 0);
  busy.value = true;
  error.value = null;
  try {
    await getGameApi().resolveSpellCast({
      gameId: props.gameId,
      battleId,
      idempotencyKey: `${pending.castId}:resolve`,
      expectedVersion: gamePhpBattleContext.battleVersion,
      castId: pending.castId,
      saturationSteps: Math.max(0, Math.trunc(chosen)),
    });
    emit('resolved');
    emit('update:open', false);
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Не удалось завершить сотворение';
    if ((caught as { code?: unknown } | null)?.code === 'GAME_CONFLICT') {
      stale.value = true;
    }
  } finally {
    busy.value = false;
  }
}

async function abort(): Promise<void> {
  const pending = props.pending;
  const battleId = gamePhpBattleContext.battleId;
  if (!pending || !abortAllowed.value || busy.value || battleId === null) return;
  busy.value = true;
  error.value = null;
  try {
    await getGameApi().abortSpellCast({
      gameId: props.gameId,
      battleId,
      idempotencyKey: `${pending.castId}:abort`,
      expectedVersion: gamePhpBattleContext.battleVersion,
      castId: pending.castId,
    });
    emit('aborted');
    emit('update:open', false);
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Не удалось прервать сотворение';
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <v-dialog :model-value="open" max-width="520" @update:model-value="emit('update:open', $event)">
    <v-card>
      <v-card-title class="text-body-1">{{ sourceName }}</v-card-title>
      <v-card-text>
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">{{ error }}</v-alert>
        <SpellSaturationPanel
          v-if="saturation"
          v-model="steps"
          :rating="saturation.rating ?? 0"
          :max-steps="saturation.maxSteps ?? 0"
          :rating-per-step="saturation.ratingPerStep ?? 0"
          :power-per-step="saturation.powerPerStep ?? 0"
          :source-name="sourceName"
          :busy="busy"
        />
        <div v-else class="text-body-2">Сотворение ждёт завершения.</div>
      </v-card-text>
      <v-card-actions>
        <v-btn v-if="abortAllowed" variant="text" color="error" :disabled="busy" @click="abort">Прервать</v-btn>
        <v-spacer />
        <v-btn v-if="canResolve" variant="text" :disabled="busy" @click="apply(0)">Без насыщения</v-btn>
        <v-btn v-if="canResolve" color="primary" :loading="busy" @click="apply()">Применить</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
