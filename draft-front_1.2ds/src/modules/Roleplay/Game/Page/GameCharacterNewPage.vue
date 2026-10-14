<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useGameStore } from '@/modules/Roleplay/Game/Store/games';
import { useCurrentUser } from '@/modules/Core/User/init';
import {
  CharacterApiError,
  CharacterSheetEditor,
  getCharacterApi,
  useCharacterDraft,
} from '@/modules/Roleplay/Character/init';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { createRandomId } from '@/modules/Core/Engine/Utils/createRandomId';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { CharacterSaveAttempt } from '@/modules/Roleplay/Character/Dto/Editor/CharacterSaveAttempt';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

const route = useRoute();
const router = useRouter();
const gameStore = useGameStore();
const { currentUser } = useCurrentUser();
const draftStore = useCharacterDraft();
const { signal } = useAbortable();

const loading = ref(true);
const saveError = ref<string | null>(null);

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

async function handleSaveChoices(build: CharacterBuild, _preview: CharacterVersion): Promise<void> {
  const gid = gameId.value;
  const key = draftKey.value;
  if (!Number.isFinite(gid) || !key) {
    saveError.value = 'Не удалось создать персонажа в игре';

    return;
  }
  const current = draftStore.draftOf(key);
  if (!current) {
    saveError.value = 'Черновик не загружен';

    return;
  }
  saveError.value = null;
  try {
    const attempt: CharacterSaveAttempt = {
      kind: 'create',
      request: {
        commandId: createRandomId(),
        choices: cloneData(build),
        creationConfig: cloneData(current.config),
      },
    };
    const validation = await getCharacterApi().validateCharacter(
      {
        mode: 'create',
        choices: attempt.request.choices,
        creationConfig: attempt.request.creationConfig,
      },
      signal.value,
    );
    if (!validation.valid) {
      throw new CharacterApiError('CHARACTER_VALIDATION_FAILED', 'Персонаж не прошёл проверку', {
        kind: 'validation',
        problems: validation.problems,
      });
    }
    const created = await getCharacterApi().createCharacter(attempt.request, signal.value);
    await getGameApi().submitCharacterToGame(gid, created.character.id, signal.value);
    await router.push(`/games/${gid}`);
    draftStore.discard(draftKey.value);
  } catch (e) {
    const code = e instanceof CharacterApiError ? e.code : '';
    saveError.value =
      code === 'AUTH_DENIED'
        ? 'Нет права создавать персонажа (нужен ключ character.create)'
        : e instanceof Error
          ? e.message
          : 'Не удалось создать персонажа в игре';
    throw e instanceof Error ? e : new Error(saveError.value);
  }
}

onMounted(load);
</script>

<template>
  <v-container fluid class="pa-0">
    <div v-if="loading" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate width="2" size="28" color="primary" />
    </div>

    <CharacterSheetEditor
      v-else-if="draftKey"
      :draft-key="draftKey"
      :require-race="true"
      :save-choices="handleSaveChoices"
    />
    <v-alert v-if="saveError" type="error" variant="tonal" class="ma-4">{{ saveError }}</v-alert>
  </v-container>
</template>
