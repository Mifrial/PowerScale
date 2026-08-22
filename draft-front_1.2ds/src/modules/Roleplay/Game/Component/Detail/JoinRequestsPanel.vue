<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useGameStore } from '@/modules/Roleplay/Game/Store/games';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import type { GameJoinRequest } from '@/modules/Roleplay/Game/Dto/GameJoinRequest';

const props = defineProps<{
  /** Активна ли вкладка: перезагрузка при активации (v-window не размонтирует вкладки). */
  active: boolean;
  gameId: number;
  memberIds: number[];
}>();

const store = useGameStore();

const requests = ref<GameJoinRequest[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);

const pendingRequests = computed(() => requests.value.filter((request) => request.status === 'pending'));

async function load(): Promise<void> {
  loading.value = true;
  error.value = null;
  try {
    requests.value = await getGameApi().getJoinRequests(props.gameId);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить заявки на вступление';
  } finally {
    loading.value = false;
  }
}

async function respond(request: GameJoinRequest, action: 'accept' | 'decline'): Promise<void> {
  try {
    await getGameApi().respondJoinRequest(props.gameId, request.userId, action);
    // Принятие добавляет участника в gameDetails; синхронизируем открытую карточку (snapshot стора).
    if (action === 'accept') {
      store.addMember({ userId: request.userId, userName: request.userName, role: 'player', permissions: [] });
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось применить решение';
  }
  await load();
}

// Перезагрузка при активации вкладки (заявки подаются со страницы игры).
watch(
  () => props.active,
  (value) => {
    if (value) void load();
  },
  { immediate: true },
);
</script>

<template>
  <v-card>
    <v-card-title class="text-subtitle-1">Заявки на вступление</v-card-title>
    <v-card-text>
      <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">{{ error }}</v-alert>
      <div v-if="loading" class="d-flex justify-center pa-4">
        <v-progress-circular indeterminate width="2" size="28" color="primary" />
      </div>
      <v-list v-else-if="pendingRequests.length > 0" density="compact" class="pa-0">
        <v-list-item v-for="request in pendingRequests" :key="request.userId">
          <div class="d-flex align-center ga-2 py-1">
            <v-avatar color="primary" size="28" variant="tonal" class="flex-shrink-0">
              <span class="text-body-2">{{ request.userName.charAt(0) }}</span>
            </v-avatar>
            <span class="text-body-2">{{ request.userName }}</span>
            <v-spacer />
            <v-btn size="x-small" color="success" variant="tonal" @click="respond(request, 'accept')">Принять</v-btn>
            <v-btn size="x-small" color="error" variant="tonal" @click="respond(request, 'decline')">Отклонить</v-btn>
          </div>
        </v-list-item>
      </v-list>
      <div v-else-if="!loading" class="text-center text-medium-emphasis pa-4">Заявок на вступление нет</div>
    </v-card-text>
  </v-card>
</template>
