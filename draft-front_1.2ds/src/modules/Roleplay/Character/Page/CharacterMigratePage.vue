<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCharacterStore } from '@/modules/Roleplay/Character/Store/characters';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { useSpaceStore } from '@/modules/Roleplay/Space/Store/spaces';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { characterMigrationService } from '@/modules/Roleplay/Character/Service/Instance/characterMigrationService';
import { getCharacterApi } from '@/modules/Roleplay/Character/init';
import { CharacterSheetEditor } from '@/modules/Roleplay/Character/init';
import MigrationReport from '@/modules/Roleplay/Character/Component/MigrationReport.vue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { MigrationResult } from '@/modules/Roleplay/Character/Service/CharacterMigrationService';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const route = useRoute();
const router = useRouter();
const characterStore = useCharacterStore();
const userStore = useUserStore();
const spaceStore = useSpaceStore();
const spaceRevisionStore = useSpaceRevisionStore();
const draftStore = useCharacterDraftStore();
const { signal } = useAbortable();

const loading = ref(false);
const loadError = ref<string | null>(null);

const selectedSpaceId = ref<number | null>(null);
const selectedRevision = ref<number | null>(null);
const revisions = ref<number[]>([]);
const targetRules = ref<Rule[]>([]);

const running = ref(false);
const migrating = ref(false);
const result = ref<MigrationResult | null>(null);
const phase = ref<'report' | 'editor'>('report');
const editorCompareOpen = ref(false);

/** Исходная версия (до миграции) и правила её ревизии — для живого сравнения в редакторе. */
const originalVersion = ref<CharacterVersion | null>(null);
const oldRules = ref<Rule[]>([]);

const characterId = computed(() => {
  const raw = route.params.id;
  const id = typeof raw === 'string' ? Number(raw) : Number.NaN;

  return Number.isFinite(id) && id > 0 ? id : null;
});

const spaces = computed(() => spaceStore.spaces);
const currentVersion = computed<CharacterVersion | null>(() => characterStore.currentCharacter?.version ?? null);

const canStart = computed(() => selectedSpaceId.value !== null && selectedRevision.value !== null);

const revisionItems = computed(() => revisions.value.map((value) => ({ title: `Ревизия ${value}`, value })));

const effectiveLimits = computed<CharacterCreationConfig>(() => {
  const version = currentVersion.value;
  if (!version) return { osTotal: null, orTotal: null, moneyBudget: null };

  return {
    osTotal: version.budgets?.osTotal ?? null,
    orTotal: version.points.orTotal,
    moneyBudget: version.budgets?.moneyBudget ?? null,
  };
});

const migrationDraftKey = computed(() =>
  characterId.value !== null && selectedRevision.value !== null
    ? `character:${characterId.value}:migrate:${selectedRevision.value}`
    : null,
);

/**
 * Данные для «Сравнить до/после»: в конфликт-редакторе — живое сравнение исходной версии
 * с ТЕКУЩИМ черновиком (реактивно на правки); в фазе отчёта — статичный результат миграции.
 */
const compareResult = computed<MigrationResult | null>(() => {
  if (phase.value === 'editor') {
    const original = originalVersion.value;
    const draft = migrationDraftKey.value ? draftStore.draftOf(migrationDraftKey.value) : undefined;
    if (!original || !draft?.build) return result.value;
    if (targetRules.value.length === 0) return result.value;

    return characterMigrationService.compareCurrent(
      original,
      draft.build,
      oldRules.value,
      targetRules.value,
      effectiveLimits.value,
    );
  }

  return result.value;
});

async function load(): Promise<void> {
  const id = characterId.value;
  if (id === null) {
    router.replace({ name: 'NotFound' });

    return;
  }
  loading.value = true;
  loadError.value = null;
  characterStore.clearCurrent();
  const detail = await characterStore.fetchCharacter(id, signal.value);
  if (!detail) {
    router.replace({ name: 'NotFound' });

    return;
  }
  if (detail.character.ownerId !== userStore.currentUser?.id) {
    router.replace({ name: 'NotFound' });

    return;
  }
  originalVersion.value = detail.version;
  try {
    oldRules.value = (
      await spaceRevisionStore.fetchRevision(detail.character.spaceId, detail.version.rulesRevision, signal.value)
    ).rules;
  } catch {
    oldRules.value = [];
  }
  // Цель по умолчанию — текущее пространство, его последняя ревизия.
  await onSpaceSelect(detail.character.spaceId, detail.character.rulesRevision);
  loading.value = false;
}

async function onSpaceSelect(spaceId: number | null, preferRevision?: number): Promise<void> {
  if (spaceId === null) {
    revisions.value = [];
    selectedRevision.value = null;
    targetRules.value = [];

    return;
  }
  selectedSpaceId.value = spaceId;
  try {
    const meta = await spaceRevisionStore.fetchRevisionsMeta(spaceId, signal.value);
    revisions.value = meta.map((entry) => entry.revision).sort((a, b) => b - a);
  } catch {
    const space = spaces.value.find((entry) => entry.id === spaceId);
    revisions.value = space ? [space.revision] : [];
  }
  selectedRevision.value = preferRevision ?? revisions.value[0] ?? null;
}

async function runMigration(): Promise<void> {
  const id = characterId.value;
  if (id === null || selectedSpaceId.value === null || selectedRevision.value === null) return;
  migrating.value = true;
  loadError.value = null;
  try {
    result.value = await getCharacterApi().migrateCharacter(id, {
      toSpaceId: selectedSpaceId.value,
      toRevision: selectedRevision.value,
    });
    phase.value = 'report';
  } catch (e) {
    loadError.value = e instanceof Error ? e.message : 'Не удалось запустить миграцию';
  } finally {
    migrating.value = false;
  }
}

async function applyVersion(version: CharacterVersion): Promise<void> {
  const id = characterId.value;
  if (id === null) return;
  running.value = true;
  try {
    await getCharacterApi().applyMigration(id, version);
    await router.push(`/characters/${id}`);
  } catch (e) {
    loadError.value = e instanceof Error ? e.message : 'Не удалось применить миграцию';
  } finally {
    running.value = false;
  }
}

function openConflictEditor(): void {
  const migration = result.value;
  const id = characterId.value;
  const spaceId = selectedSpaceId.value;
  if (!migration || id === null || migrationDraftKey.value === null || spaceId === null) return;
  const build = characterBuildService.fromVersion(migration.version, spaceId, targetRules.value);
  draftStore.initDraft(migrationDraftKey.value, build, effectiveLimits.value);
  phase.value = 'editor';
}

/** Сохранение из конфликт-редактора: применённая (исправленная) версия миграции. */
async function handleEditorSave(version: CharacterVersion): Promise<void> {
  await applyVersion(version);
  if (migrationDraftKey.value) draftStore.discard(migrationDraftKey.value);
}

watch(selectedSpaceId, (value) => {
  if (value !== null && !running.value) void onSpaceSelect(value);
});

watch(
  () => characterStore.currentCharacter,
  (detail) => {
    if (!detail) return;
    void spaceRevisionStore
      .fetchRevision(detail.character.spaceId, selectedRevision.value ?? detail.version.rulesRevision, signal.value)
      .then((revision) => {
        targetRules.value = revision.rules;
      })
      .catch(() => {
        targetRules.value = [];
      });
  },
);

onMounted(load);
</script>

<template>
  <v-container fluid>
    <div v-if="loading" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate width="2" size="28" color="primary" />
    </div>

    <template v-else>
      <div class="d-flex align-center mb-4">
        <div class="d-flex flex-column ga-1">
          <h1 class="text-h5">Перевод на новую версию правил</h1>
          <span class="text-caption text-medium-emphasis"
            >{{ currentVersion?.name }} · ревизия {{ currentVersion?.rulesRevision }} → новая</span
          >
        </div>
        <v-spacer />
        <v-btn variant="text" prepend-icon="mdi-arrow-left" @click="router.push(`/characters/${characterId}`)">
          К персонажу
        </v-btn>
      </div>

      <v-alert v-if="loadError" type="error" variant="tonal" density="compact" class="mb-4">{{ loadError }}</v-alert>

      <!-- Цель миграции -->
      <v-card max-width="640" class="mb-4">
        <v-card-text>
          <div class="d-flex ga-4">
            <v-select
              v-model="selectedSpaceId"
              label="Пространство правил"
              :items="spaces"
              item-title="name"
              item-value="id"
              hint="Ремап ссылок — по коду правила, работает между пространствами"
              persistent-hint
            />
            <v-autocomplete
              v-model="selectedRevision"
              label="Ревизия правил"
              :items="revisionItems"
              item-title="title"
              item-value="value"
              hint="Целевая ревизия"
              persistent-hint
            />
          </div>
        </v-card-text>
        <v-card-actions class="pa-4">
          <v-btn color="primary" variant="tonal" :disabled="!canStart" :loading="migrating" @click="runMigration">
            Запустить миграцию
          </v-btn>
        </v-card-actions>
      </v-card>

      <!-- Отчёт миграции -->
      <v-card v-if="result && phase === 'report'" max-width="720">
        <v-card-title class="text-subtitle-1 d-flex align-center">
          <span>Результат миграции</span>
          <v-spacer />
          <v-btn size="small" variant="text" prepend-icon="mdi-compare-horizontal" @click="editorCompareOpen = true">
            Сравнить до/после
          </v-btn>
        </v-card-title>
        <v-card-text class="d-flex flex-column ga-3">
          <MigrationReport :result="result" />
        </v-card-text>
        <v-card-actions class="pa-4">
          <template v-if="result.kind === 'conflicts'">
            <v-btn color="warning" variant="tonal" prepend-icon="mdi-account-wrench" @click="openConflictEditor">
              Разрешить конфликт
            </v-btn>
          </template>
          <template v-else>
            <v-btn color="primary" variant="tonal" :loading="running" @click="applyVersion(result.version)">
              Перейти на версию
            </v-btn>
          </template>
          <v-btn variant="text" @click="result = null">Назад</v-btn>
        </v-card-actions>
      </v-card>

      <!-- Конфликт-редактор на новой ревизии (черновик, авто-сброс невалидного) -->
      <template v-else-if="phase === 'editor' && migrationDraftKey">
        <div class="d-flex align-center mb-2">
          <span class="text-caption text-medium-emphasis"
            >Способности/предметы с удалённым правилом и невыполненными требованиями сброшены. Правки сохраняются как
            черновик.</span
          >
          <v-spacer />
          <v-btn size="small" variant="text" prepend-icon="mdi-compare-horizontal" @click="editorCompareOpen = true">
            Сравнить до/после
          </v-btn>
        </div>
        <CharacterSheetEditor :draft-key="migrationDraftKey" :require-race="true" @save="handleEditorSave" />
      </template>
    </template>

    <v-dialog v-model="editorCompareOpen" max-width="720">
      <v-card v-if="compareResult">
        <v-card-title class="text-subtitle-1">Сравнение до миграции и после</v-card-title>
        <v-card-text>
          <MigrationReport :result="compareResult" />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="editorCompareOpen = false">Закрыть</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>
