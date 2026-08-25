<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';
import { useCharacterStore } from '@/modules/Roleplay/Character/Store/characters';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { getCharacterApi } from '@/modules/Roleplay/Character/init';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { canViewCharacter } from '@/modules/Roleplay/Character/Utils/access';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CreateCharacterData } from '@/modules/Roleplay/Character/Dto/Editor/CreateCharacterData';
import type { UpdateCharacterData } from '@/modules/Roleplay/Character/Dto/Editor/UpdateCharacterData';
import CharacterSheetEditor from '@/modules/Roleplay/Character/Component/Editor/CharacterSheetEditor.vue';

const route = useRoute();
const router = useRouter();
const draftStore = useCharacterDraftStore();
const characterStore = useCharacterStore();
const userStore = useUserStore();
const spaceRevisionStore = useSpaceRevisionStore();
const { signal } = useAbortable();

const isNew = computed(() => route.name === 'CharacterNewEditor');

const characterId = computed<number | null>(() => {
  if (isNew.value) return null;
  const raw = route.params.id;
  const id = typeof raw === 'string' ? Number(raw) : Number.NaN;

  return Number.isFinite(id) && id > 0 ? id : null;
});

/** Контекст in-game редактора (`?gameId=`): правки идут в сессионный оверлей (модель версий — Баг 1). */
const gameId = computed<number | null>(() => {
  const raw = route.query.gameId;
  const id = typeof raw === 'string' ? Number(raw) : Number.NaN;

  return Number.isFinite(id) && id > 0 ? id : null;
});

// Черновик in-game редактора изолирован от standalone (ключ с gameId) — правки сессии не
// пересекаются с правками карточки.
const draftKey = computed<string | null>(() => {
  if (characterId.value === null) return null;
  const base = `character:${characterId.value}`;

  return gameId.value !== null ? `${base}:game:${gameId.value}` : base;
});

const loading = ref(false);
const saveError = ref<string | null>(null);

const draft = computed(() => draftStore.draftOf(draftKey.value));

async function loadRules(spaceId: number, revision: number): Promise<Rule[]> {
  const revisionResult = await spaceRevisionStore.fetchRevision(spaceId, revision, signal.value);

  return revisionResult.rules;
}

async function load(): Promise<void> {
  loading.value = true;
  saveError.value = null;

  if (isNew.value) {
    loading.value = false;

    return;
  }

  const id = characterId.value;
  if (id === null) {
    router.replace({ name: 'NotFound' });

    return;
  }
  characterStore.clearCurrent();
  const detail = await characterStore.fetchCharacter(id, signal.value);
  if (!detail || !canViewCharacter(userStore.currentUser, detail.character)) {
    router.replace({ name: 'NotFound' });

    return;
  }

  if (!draftStore.hasDraft(draftKey.value)) {
    // База черновика: in-game редактор стартует с эффективной версии игры (approved + оверлей),
    // standalone — с latest.
    let baseVersion = detail.version;
    if (gameId.value !== null) {
      try {
        const memberships = await getGameApi().getGameCharacters(gameId.value, signal.value);
        const membership = memberships.find((m) => m.characterId === id);
        if (membership) {
          baseVersion = membership.overlay?.sheet ?? membership.activeVersion ?? detail.version;
        }
      } catch {
        // игра недоступна — остаёмся на latest
      }
    }
    const rules = await loadRules(detail.character.spaceId, baseVersion.rulesRevision);
    const build = characterBuildService.fromVersion(baseVersion, detail.character.spaceId, rules);
    const baseline = {
      inventory: build.inventory.map((item) => ({ ...item })),
      money: build.money,
    };
    draftStore.initDraft(
      draftKey.value,
      build,
      {
        osTotal: baseVersion.budgets?.osTotal ?? null,
        orTotal: baseVersion.points.orTotal ?? null,
        moneyBudget: baseVersion.budgets?.moneyBudget ?? null,
      },
      baseline,
    );
  }
  loading.value = false;
}

/** Сохранение через character API (лист персонажа); ошибка пробрасывается в редактор. */
async function handleSave(version: CharacterVersion): Promise<void> {
  const current = draft.value;
  if (!current) return;
  saveError.value = null;
  try {
    if (isNew.value) {
      const data: CreateCharacterData = {
        spaceId: current.build.spaceId,
        spaceCode: current.build.spaceCode,
        rulesRevision: current.build.rulesRevision,
        version,
        // Редактор вне игры — финализация: статус листа «Готов». (В игре модерацию несёт членство.)
        status: 'ready',
      };
      const created = await getCharacterApi().createCharacter(data, signal.value);
      // Сначала уходим с роута редактора, потом чистим черновик: иначе на кадре перехода
      // редактор отрисует пустой черновик («Сначала задайте правила и лимиты»).
      await router.push(`/characters/${created.character.id}`);
      draftStore.discard(null);
    } else if (characterId.value !== null) {
      const data: UpdateCharacterData = { version, status: 'ready' };
      if (gameId.value !== null) data.gameId = gameId.value;
      await getCharacterApi().updateCharacter(characterId.value, data, signal.value);
      // In-game редактор возвращает в игру; standalone — на страницу персонажа.
      await router.push(gameId.value !== null ? `/games/${gameId.value}` : `/characters/${characterId.value}`);
      draftStore.discard(draftKey.value);
    }
  } catch (e) {
    throw e instanceof Error ? e : new Error('Не удалось сохранить персонажа');
  }
}

watch(() => [route.name, route.params.id], load, { immediate: true });
</script>

<template>
  <v-container fluid class="pa-0">
    <div v-if="loading" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate width="2" size="28" color="primary" />
    </div>

    <div v-else-if="isNew && draft === undefined">
      <v-card max-width="520" class="mx-auto mt-8">
        <v-card-text class="text-center pa-8">
          <v-icon icon="mdi-dice-multiple" size="56" class="mb-4" color="primary" />
          <p class="text-body-1 mb-4">Сначала задайте правила и лимиты создания.</p>
          <v-btn color="primary" :to="'/characters/new'">Настройка создания</v-btn>
        </v-card-text>
      </v-card>
    </div>

    <div v-else-if="draft === undefined">
      <v-card max-width="520" class="mx-auto mt-8">
        <v-card-text class="text-center pa-8">
          <p class="text-body-1 mb-4">Персонаж не найден.</p>
          <v-btn color="primary" :to="'/characters'">К списку</v-btn>
        </v-card-text>
      </v-card>
    </div>

    <CharacterSheetEditor v-else :draft-key="draftKey" :require-race="true" @save="handleSave" />
  </v-container>
</template>
