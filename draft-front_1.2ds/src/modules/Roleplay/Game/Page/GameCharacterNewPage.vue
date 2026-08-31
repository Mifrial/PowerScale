<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useGameStore } from '@/modules/Roleplay/Game/Store/games';
import { useCurrentUser } from '@/modules/Core/User/init';
import { useCharacterDraft } from '@/modules/Roleplay/Character/init';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { CharacterSheetEditor } from '@/modules/Roleplay/Character/init';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { CreateCharacterData } from '@/modules/Roleplay/Character/Dto/Editor/CreateCharacterData';

const route = useRoute();
const router = useRouter();
const gameStore = useGameStore();
const { currentUser } = useCurrentUser();
const draftStore = useCharacterDraft();
const { signal } = useAbortable();

const loading = ref(false);

const gameId = computed(() => {
  const raw = route.params.id;
  const id = typeof raw === 'string' ? Number(raw) : Number.NaN;

  return Number.isFinite(id) && id > 0 ? id : Number.NaN;
});

const draftKey = computed<string | null>(() =>
  Number.isFinite(gameId.value) ? `character:new:${gameId.value}` : null,
);

async function load(): Promise<void> {
  const gid = gameId.value;
  if (!Number.isFinite(gid) || !draftKey.value) {
    router.replace({ name: 'NotFound' });

    return;
  }
  loading.value = true;
  gameStore.clearCurrent();
  const gameDetail = await gameStore.fetchGame(gid, signal.value);
  const user = currentUser.value;
  const isMember = gameDetail?.members.some((member) => member.userId === user?.id) ?? false;
  if (!gameDetail || !isMember) {
    router.replace({ name: 'NotFound' });

    return;
  }

  if (!draftStore.hasDraft(draftKey.value)) {
    const build: CharacterBuild = {
      name: '',
      shortDescription: null,
      fullDescription: null,
      spaceId: gameDetail.game.spaceId,
      spaceCode: gameDetail.game.spaceCode,
      rulesRevision: gameDetail.game.rulesRevision,
      raceRuleCode: null,
      characteristicPurchases: [],
      abilities: [],
      resources: [],
      inventory: [],
      states: [],
      money: 0,
      ageYears: null,
      olTotal: 0,
    };
    // Лимиты создания берутся из игры (пустые — без лимита).
    const config: CharacterCreationConfig = {
      osTotal: gameDetail.osPointsLimit,
      orTotal: gameDetail.orPointsLimit,
      moneyBudget: gameDetail.moneyLimit,
    };
    draftStore.initDraft(draftKey.value, build, config);
  }
  loading.value = false;
}

/** Сохранение: персонаж создаётся и сразу подаётся в игру (членство pending на модерацию ГМ). */
async function handleSave(version: CharacterVersion): Promise<void> {
  const gid = gameId.value;
  if (!Number.isFinite(gid) || !draftKey.value) return;
  const current = draftStore.draftOf(draftKey.value);
  if (!current) return;
  try {
    const data: CreateCharacterData = {
      spaceId: current.build.spaceId,
      spaceCode: current.build.spaceCode,
      rulesRevision: current.build.rulesRevision,
      version,
      // Полный созданный лист; модерацию несёт членство игры (pending).
      status: 'ready',
    };
    await getGameApi().createGameCharacter(gid, data);
    await router.push(`/games/${gid}`);
    draftStore.discard(draftKey.value);
  } catch (e) {
    throw e instanceof Error ? e : new Error('Не удалось создать персонажа в игре');
  }
}

onMounted(load);
</script>

<template>
  <v-container fluid class="pa-0">
    <div v-if="loading" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate width="2" size="28" color="primary" />
    </div>

    <CharacterSheetEditor v-else-if="draftKey" :draft-key="draftKey" :require-race="true" @save="handleSave" />
  </v-container>
</template>
