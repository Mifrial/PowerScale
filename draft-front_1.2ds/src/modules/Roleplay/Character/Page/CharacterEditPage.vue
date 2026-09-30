<script setup lang="ts">
import { useSpaceRevision } from '@/modules/Roleplay/RuleSpace/init';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';
import { useCharacterStore } from '@/modules/Roleplay/Character/Store/characters';
import { useCurrentUser } from '@/modules/Core/User/init';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import {
  characterPatchService,
  CharacterApiError,
  getCharacterApi,
} from '@/modules/Roleplay/Character/init';
import { characterAccessService } from '@/modules/Roleplay/Character/Service/Instance/characterAccessService';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CharacterSaveAttempt } from '@/modules/Roleplay/Character/Dto/Editor/CharacterSaveAttempt';
import CharacterSheetEditor from '@/modules/Roleplay/Character/Component/Editor/CharacterSheetEditor.vue';

const route = useRoute();
const router = useRouter();
const draftStore = useCharacterDraftStore();
const characterStore = useCharacterStore();
const { currentUser } = useCurrentUser();
const spaceRevision = useSpaceRevision();
const { signal } = useAbortable();

const isNew = computed(() => route.name === 'CharacterNewEditor');

const characterId = computed<number | null>(() => {
  if (isNew.value) return null;
  const raw = route.params.id;
  const id = typeof raw === 'string' ? Number(raw) : Number.NaN;

  return Number.isFinite(id) && id > 0 ? id : null;
});

/** Контекст маршрута in-game editor; не является выбором Character storage path. */
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
const loadError = ref<string | null>(null);
const saveError = ref<string | null>(null);
const saveConflict = ref(false);
const saving = ref(false);

const pendingSave = ref<CharacterSaveAttempt | null>(null);

const draft = computed(() => draftStore.draftOf(draftKey.value));

async function loadRules(spaceId: number, revision: number): Promise<Rule[]> {
  const revisionResult = await spaceRevision.fetchRevision(spaceId, revision, signal.value);

  return revisionResult.rules;
}

async function load(): Promise<void> {
  loading.value = true;
  loadError.value = null;
  saveError.value = null;
  saveConflict.value = false;
  pendingSave.value = null;

  try {
    if (isNew.value) return;

    const id = characterId.value;
    if (id === null) {
      router.replace({ name: 'NotFound' });

      return;
    }
    characterStore.clearCurrent();
    const detail = await characterStore.fetchCharacter(id, signal.value);
    if (!detail || !characterAccessService.canViewCharacter(currentUser.value, detail.character)) {
      router.replace({ name: 'NotFound' });

      return;
    }

    if (!draftStore.hasDraft(draftKey.value)) {
      const rules = await loadRules(detail.character.spaceId, detail.version.rulesRevision);
      const build = characterBuildService.fromVersion(detail.version, detail.character.spaceId, rules);
      const baseline = {
        inventory: build.inventory.map((item) => ({ ...item })),
        money: build.money,
      };
      draftStore.initDraft(
        draftKey.value,
        build,
        {
          osTotal: detail.version.budgets?.osTotal ?? null,
          orTotal: detail.version.points.orTotal ?? null,
          moneyBudget: detail.version.budgets?.moneyBudget ?? null,
        },
        baseline,
      );
    }
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return;
    loadError.value = e instanceof Error ? e.message : 'Не удалось загрузить редактор';
  } finally {
    loading.value = false;
  }
}

async function executeSave(attempt: CharacterSaveAttempt): Promise<void> {
  if (attempt.kind === 'create') {
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
    await router.push(`/characters/${created.character.id}`);
    draftStore.discard(null);
    pendingSave.value = null;

    return;
  }

  const validation = await getCharacterApi().validateCharacter(
    { mode: 'update', characterId: attempt.characterId, patch: attempt.patch },
    signal.value,
  );
  if (!validation.valid) {
    throw new CharacterApiError('CHARACTER_VALIDATION_FAILED', 'Персонаж не прошёл проверку', {
      kind: 'validation',
      problems: validation.problems,
    });
  }
  const updated = await getCharacterApi().updateCharacter(attempt.characterId, { patch: attempt.patch }, signal.value);
  characterStore.applyDetail(updated);
  await router.push(gameId.value !== null ? `/games/${gameId.value}` : `/characters/${attempt.characterId}`);
  draftStore.discard(draftKey.value);
  pendingSave.value = null;
}

/** Сохранение choices через typed Character contract; preview используется только для построения patch. */
async function handleSaveChoices(build: CharacterBuild, preview: CharacterVersion): Promise<void> {
  const current = draft.value;
  if (!current || saving.value) return;
  saveError.value = null;
  saveConflict.value = false;
  let attempt: CharacterSaveAttempt;
  if (isNew.value) {
    attempt = {
      kind: 'create',
      request: {
        commandId: crypto.randomUUID(),
        choices: structuredClone(build),
        creationConfig: structuredClone(current.config),
      },
    };
  } else {
    const detail = characterStore.currentCharacter;
    if (characterId.value === null || !detail) {
      saveError.value = 'Актуальный персонаж не загружен';

      return;
    }
    attempt = {
      kind: 'update',
      characterId: characterId.value,
      patch: characterPatchService.createPatch(detail.version, preview, crypto.randomUUID(), detail.actualVersion),
    };
  }

  pendingSave.value = attempt;
  saving.value = true;

  try {
    await executeSave(attempt);
  } catch (e) {
    saveError.value = e instanceof Error ? e.message : 'Не удалось сохранить персонажа';
    saveConflict.value = e instanceof CharacterApiError && e.code === 'CHARACTER_ACTUAL_CONFLICT';
  } finally {
    saving.value = false;
  }
}

async function retrySave(): Promise<void> {
  const attempt = pendingSave.value;
  if (!attempt || saving.value || saveConflict.value) return;
  saveError.value = null;
  saving.value = true;
  try {
    await executeSave(attempt);
  } catch (e) {
    saveError.value = e instanceof Error ? e.message : 'Не удалось сохранить персонажа';
    saveConflict.value = e instanceof CharacterApiError && e.code === 'CHARACTER_ACTUAL_CONFLICT';
  } finally {
    saving.value = false;
  }
}

async function reloadAfterConflict(): Promise<void> {
  if (saving.value || characterId.value === null) return;
  pendingSave.value = null;
  saveError.value = null;
  saveConflict.value = false;
  draftStore.discard(draftKey.value);
  await load();
}

async function handleIntegrityCancel(): Promise<void> {
  await router.push(gameId.value !== null ? `/games/${gameId.value}` : `/characters/${characterId.value ?? ''}`);
}

watch(() => [route.name, route.params.id, gameId.value], load, { immediate: true });
</script>

<template>
  <v-container fluid class="pa-0">
    <div v-if="loading" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate width="2" size="28" color="primary" />
    </div>

    <div v-else-if="loadError" class="text-center pa-8">
      <v-icon icon="mdi-alert-circle" size="64" color="error" class="mb-4" />
      <p class="text-body-1 mb-4">{{ loadError }}</p>
      <v-btn color="primary" @click="load">Попробовать снова</v-btn>
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

    <CharacterSheetEditor
      v-else
      :draft-key="draftKey"
      :require-race="true"
      @save-choices="handleSaveChoices"
      @cancel="handleIntegrityCancel"
    />
    <v-alert v-if="saveError" type="error" variant="tonal" class="ma-4">
      <div class="d-flex align-center justify-space-between ga-3">
        <span>{{ saveError }}</span>
        <v-btn variant="text" :loading="saving" @click="saveConflict ? reloadAfterConflict() : retrySave()">
          {{ saveConflict ? 'Загрузить актуальный лист' : 'Повторить' }}
        </v-btn>
      </div>
    </v-alert>
  </v-container>
</template>
