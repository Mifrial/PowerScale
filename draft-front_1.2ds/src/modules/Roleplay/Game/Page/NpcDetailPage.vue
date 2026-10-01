<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useGameStore } from '@/modules/Roleplay/Game/Store/games';
import { useCurrentUser } from '@/modules/Core/User/init';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { CharacterSheetBody, sheetAccessService } from '@/modules/Roleplay/Character/init';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { gameAccessService } from '@/modules/Roleplay/Game/Service/Instance/gameAccessService';
import { npcDetailAccessService } from '@/modules/Roleplay/Game/Service/Instance/npcDetailAccessService';
import type { GameDetail } from '@/modules/Roleplay/Game/Dto/GameDetail';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { SheetSection } from '@/modules/Roleplay/Character/Enum/SheetSection';

const route = useRoute();
const router = useRouter();
const gameStore = useGameStore();
const { currentUser } = useCurrentUser();
const { signal } = useAbortable();

const loading = ref(false);
const loadError = ref<string | null>(null);
const npc = ref<GameNpc | null>(null);
const gameDetail = ref<GameDetail | null>(null);

const gameId = computed(() => {
  const raw = route.params.id;
  const id = typeof raw === 'string' ? Number(raw) : Number.NaN;

  return Number.isFinite(id) && id > 0 ? id : Number.NaN;
});

const npcId = computed(() => {
  const raw = route.params.npcId;
  const id = typeof raw === 'string' ? Number(raw) : Number.NaN;

  return Number.isFinite(id) && id > 0 ? id : Number.NaN;
});

const canEdit = computed(() => {
  const detail = gameDetail.value;
  if (!detail) return false;

  return gameAccessService.canEditGame(currentUser.value, detail);
});

const visibleSections = computed<SheetSection[]>(() => {
  const current = npc.value;
  const user = currentUser.value;
  if (!current || !user) return [];

  return sheetAccessService.visibleSheetSections(user, current.visibility, {
    user,
    ownerId: null,
    characterId: current.id,
    gameId: current.gameId,
  });
});

const showsFullSheet = computed(() =>
  npcDetailAccessService.showsFullSheet(npc.value?.version ?? null, visibleSections.value),
);

async function load(): Promise<void> {
  const gid = gameId.value;
  const nid = npcId.value;
  if (!Number.isFinite(gid) || !Number.isFinite(nid)) {
    router.replace({ name: 'NotFound' });

    return;
  }
  loading.value = true;
  loadError.value = null;
  npc.value = null;
  gameStore.clearCurrent();
  try {
    const loadedGame = await gameStore.fetchGame(gid, signal.value);
    if (
      !loadedGame ||
      !gameAccessService.canViewGame(
        currentUser.value,
        loadedGame.game,
        loadedGame.members.map((member) => member.userId),
      )
    ) {
      router.replace({ name: 'NotFound' });

      return;
    }
    const loadedNpc = await getGameApi().getNpc(gid, nid, signal.value);
    if (!npcDetailAccessService.canOpen(currentUser.value, loadedGame, loadedNpc)) {
      router.replace({ name: 'NotFound' });

      return;
    }
    gameDetail.value = loadedGame;
    npc.value = loadedNpc;
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return;
    const message = e instanceof Error ? e.message : '';
    if (message === 'НПС недоступен' || message === 'НПС не найден') {
      router.replace({ name: 'NotFound' });

      return;
    }
    loadError.value = message || 'Не удалось загрузить НПС';
  } finally {
    loading.value = false;
  }
}

watch([gameId, npcId], load, { immediate: true });
</script>

<template>
  <v-container>
    <div v-if="loadError" class="text-center pa-8">
      <p class="text-body-1 mb-4">{{ loadError }}</p>
      <v-btn color="primary" @click="load">Попробовать снова</v-btn>
    </div>

    <div v-else-if="loading || !npc || !gameDetail" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate width="2" size="28" color="primary" />
    </div>

    <template v-else>
      <div class="d-flex align-center mb-4 ga-2 flex-wrap">
        <h1 class="text-h5">{{ npc.name }}</h1>
        <v-chip v-if="npc.status === 'proposed'" color="warning" variant="tonal" size="small">На модерации</v-chip>
        <v-spacer />
        <v-btn v-if="canEdit" variant="tonal" size="small" prepend-icon="mdi-pencil">Редактировать</v-btn>
        <v-btn v-if="canEdit && npc.status === 'proposed'" variant="tonal" size="small">Модерация</v-btn>
        <v-btn v-if="canEdit && showsFullSheet" variant="tonal" size="small">Боевая карточка</v-btn>
      </div>

      <CharacterSheetBody
        :name="npc.name"
        :version="npc.version"
        :space-id="gameDetail.game.spaceId"
        :visible-sections="visibleSections"
        :short-description="npc.shortDescription"
        :full-description="npc.fullDescription"
      />
    </template>
  </v-container>
</template>
