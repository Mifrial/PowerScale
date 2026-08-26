<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useGameStore } from '@/modules/Roleplay/Game/Store/games';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { gameAccessService } from '@/modules/Roleplay/Game/Service/Instance/gameAccessService';

import { toCreateGameData } from '@/modules/Roleplay/Game/Utils/toCreateGameData';
import type { CreateGameData } from '@/modules/Roleplay/Game/Dto/CreateGameData';
import GameForm from '@/modules/Roleplay/Game/Component/GameForm.vue';

const route = useRoute();
const router = useRouter();
const store = useGameStore();
const userStore = useUserStore();
const { signal } = useAbortable();

const saving = ref(false);
const saveError = ref<string | null>(null);

const gameId = computed(() => {
  const raw = route.params.id;
  if (typeof raw !== 'string') return Number.NaN;

  return Number(raw);
});

const detail = computed(() => store.currentGame);
const detailLoading = computed(() => store.detailLoading);
const detailError = computed(() => store.detailError);

// Редактирование не даёт менять привязку к ревизии правил: персонажи игры привязаны к ней.
const initial = computed<CreateGameData | null>(() => {
  const current = detail.value;
  if (!current) return null;

  return toCreateGameData(current);
});

async function load(): Promise<void> {
  const id = gameId.value;
  if (!Number.isFinite(id) || id <= 0) {
    router.replace({ name: 'NotFound' });

    return;
  }
  store.clearCurrent();
  const loaded = await store.fetchGame(id, signal.value);
  if (loaded && !gameAccessService.canEditGame(userStore.currentUser, loaded)) {
    router.replace({ name: 'NotFound' });
  }
}

async function handleSubmit(data: CreateGameData): Promise<void> {
  saving.value = true;
  saveError.value = null;
  try {
    await getGameApi().updateGame(gameId.value, data);
    void router.push(`/games/${gameId.value}`);
  } catch (e) {
    saveError.value = e instanceof Error ? e.message : 'Не удалось сохранить игру';
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>

<template>
  <v-container>
    <div v-if="detailError" class="text-center pa-8">
      <v-icon icon="mdi-alert-circle" size="64" color="error" class="mb-4" />
      <p class="text-body-1 mb-4">{{ detailError }}</p>
      <v-btn color="primary" @click="load">Попробовать снова</v-btn>
    </div>

    <div v-else-if="detailLoading || !detail" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate width="2" size="28" color="primary" />
    </div>

    <template v-else>
      <h1 class="text-h5 font-weight-bold mb-1">Настройки игры</h1>
      <p class="text-body-1 text-medium-emphasis mb-6">Название, статус, доступ и лимиты.</p>

      <GameForm
        :initial="initial"
        submit-label="Сохранить"
        :submit-loading="saving"
        :submit-error="saveError"
        @submit="handleSubmit"
        @cancel="router.push(`/games/${gameId}`)"
      />
    </template>
  </v-container>
</template>
