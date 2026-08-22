<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { characterMigrationService } from '@/modules/Roleplay/Character/Service/Instance/characterMigrationService';
import { getCharacterApi } from '@/modules/Roleplay/Character/init';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { CharacterSheetEditor } from '@/modules/Roleplay/Character/init';
import MigrationReport from '@/modules/Roleplay/Character/Component/MigrationReport.vue';
import SlidePanel from '@/modules/Core/UI/Component/SlidePanel.vue';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { MigrationResult } from '@/modules/Roleplay/Character/Service/CharacterMigrationService';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const props = defineProps<{
  gameId: number;
  /** Цель миграции — ревизия игры (персонажи в игре должны быть той же ревизии). */
  gameSpaceId: number;
  gameSpaceCode: string;
  gameRulesRevision: number;
  /** Лимиты игры (для реальных лимитов персонажа = лимит + гранты членства). */
  gameOsLimit: number | null;
  gameOrLimit: number | null;
  gameMoneyLimit: number | null;
  /** Текущее членство: активная версия (старая) + гранты ГМ. */
  membership: GameCharacterMembership | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const spaceRevisionStore = useSpaceRevisionStore();
const draftStore = useCharacterDraftStore();
const { signal } = useAbortable();

const loading = ref(false);
const error = ref<string | null>(null);
const submitting = ref(false);
const result = ref<MigrationResult | null>(null);
const phase = ref<'report' | 'editor'>('report');
const editorCompareOpen = ref(false);

const names = ref<Record<string, string>>({});
const targetRules = ref<Rule[]>([]);

/** Исходная версия (до миграции) и правила старой ревизии — для живого сравнения в редакторе. */
const originalVersion = ref<CharacterVersion | null>(null);
const oldRules = ref<Rule[]>([]);

/** Реальные лимиты персонажа: лимит игры + гранты ГМ. */
const effectiveLimits = computed<CharacterCreationConfig>(() => ({
  osTotal: props.gameOsLimit,
  orTotal: props.gameOrLimit,
  moneyBudget: props.gameMoneyLimit,
}));

const draftKey = computed(() =>
  props.membership ? `character:${props.membership.characterId}:migrate:game:${props.gameId}` : null,
);

function resolve(ruleId: string): string {
  return names.value[ruleId] ?? ruleId;
}

/**
 * Данные для «Сравнить до/после»: в конфликт-редакторе — живое сравнение исходной версии
 * с ТЕКУЩИМ черновиком (реактивно на правки); в фазе отчёта — статичный результат миграции.
 */
const compareResult = computed<MigrationResult | null>(() => {
  if (phase.value === 'editor') {
    const original = originalVersion.value;
    const draft = draftKey.value ? draftStore.draftOf(draftKey.value) : undefined;
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

async function run(): Promise<void> {
  const membership = props.membership;
  if (!membership?.activeVersion) return;
  loading.value = true;
  error.value = null;
  try {
    const character = await getCharacterApi().getCharacter(membership.characterId);
    const oldRevision = await spaceRevisionStore.fetchRevision(
      character.character.spaceId,
      membership.activeVersion.rulesRevision,
      signal.value,
    );
    originalVersion.value = membership.activeVersion;
    oldRules.value = oldRevision.rules;
    const newRevision = await spaceRevisionStore.fetchRevision(
      props.gameSpaceId,
      props.gameRulesRevision,
      signal.value,
    );
    names.value = Object.fromEntries(newRevision.rules.map((rule) => [rule.id, rule.name]));
    targetRules.value = newRevision.rules;
    result.value = characterMigrationService.migrate({
      version: membership.activeVersion,
      oldRules: oldRevision.rules,
      oldSpaceId: character.character.spaceId,
      newRules: newRevision.rules,
      newSpaceId: props.gameSpaceId,
      newSpaceCode: props.gameSpaceCode,
      newRevision: props.gameRulesRevision,
      effectiveLimits: effectiveLimits.value,
    });
    phase.value = 'report';
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось запустить миграцию';
  } finally {
    loading.value = false;
  }
}

function submit(): void {
  const membership = props.membership;
  const migration = result.value;
  if (!membership || !migration) return;
  submitting.value = true;
  error.value = null;
  void (async () => {
    try {
      await getGameApi().submitCharacterMigration(props.gameId, membership.characterId, migration.version);
      open.value = false;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Не удалось отправить на модерацию';
    } finally {
      submitting.value = false;
    }
  })();
}

function openEditor(): void {
  const migration = result.value;
  if (!migration || !props.membership || draftKey.value === null) return;
  const build = characterBuildService.fromVersion(migration.version, props.gameSpaceId, targetRules.value as never);
  draftStore.initDraft(draftKey.value, build, effectiveLimits.value);
  phase.value = 'editor';
}

/** Сохранение из конфликт-редактора: исправленная версия уходит на модерацию. */
async function handleEditorSave(version: CharacterVersion): Promise<void> {
  if (!props.membership) return;
  await getGameApi().submitCharacterMigration(props.gameId, props.membership.characterId, version);
  if (draftKey.value) draftStore.discard(draftKey.value);
  open.value = false;
}

watch(open, (value) => {
  if (value) {
    result.value = null;
    phase.value = 'report';
    void run();
  }
});
</script>
<template>
  <SlidePanel v-model="open" width="760px">
    <template #header>
      <div class="d-flex align-center ga-2 w-100 migration-header">
        <div class="d-flex flex-column">
          <span class="font-weight-bold text-body-2">Перевод на новую ревизию — {{ membership?.characterName }}</span>
          <span class="text-caption text-medium-emphasis"
            >Всё, чего нет или чьим требованиям персонаж не соответствует, сбрасывается.</span
          >
        </div>
        <v-spacer />
        <v-btn size="small" variant="text" prepend-icon="mdi-compare-horizontal" @click="editorCompareOpen = true">
          Сравнить до/после
        </v-btn>
      </div>
    </template>

    <div class="pa-4 d-flex flex-column ga-3">
      <v-alert v-if="error" type="error" variant="tonal" density="compact">{{ error }}</v-alert>
      <div v-if="loading" class="d-flex justify-center pa-4">
        <v-progress-circular indeterminate width="2" size="28" color="primary" />
      </div>

      <template v-if="result && phase === 'report'">
        <p class="text-body-2 text-medium-emphasis">
          Персонаж переводится на ревизию игры. Результат уйдёт на модерацию ведущему.
        </p>
        <MigrationReport :result="result" :resolve="resolve" />
      </template>

      <template v-if="phase === 'editor' && draftKey">
        <div class="migration-editor">
          <CharacterSheetEditor
            :draft-key="draftKey"
            :require-race="true"
            actions-target="#migration-editor-actions"
            @save="handleEditorSave"
          />
        </div>
      </template>
    </div>

    <template #footer>
      <div v-if="result && phase === 'report'" class="d-flex justify-end ga-2 pa-3">
        <template v-if="result.kind === 'conflicts'">
          <v-btn color="warning" variant="tonal" prepend-icon="mdi-account-wrench" @click="openEditor">
            Разрешить конфликт
          </v-btn>
        </template>
        <template v-else>
          <v-btn color="primary" variant="tonal" :loading="submitting" @click="submit"> Отправить на модерацию </v-btn>
        </template>
      </div>
      <div
        v-if="phase === 'editor' && draftKey"
        class="d-flex justify-end ga-2 pa-3"
        id="migration-editor-actions"
      ></div>
    </template>

    <v-dialog v-model="editorCompareOpen" max-width="720">
      <v-card v-if="compareResult">
        <v-card-title class="text-subtitle-1">Сравнение до миграции и после</v-card-title>
        <v-card-text>
          <MigrationReport :result="compareResult" :resolve="resolve" />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="editorCompareOpen = false">Закрыть</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </SlidePanel>
</template>

<style scoped>
/* Шапка слайдера чуть уже: компенсируем внутренний padding SlidePanel (−2px со всех сторон). */
.migration-header {
  margin: -2px;
}
</style>

<style>
/* Панель шагов редактора в слайдере миграции: подтянута к бордеру шапки.
   Перебивает штатный editor-nav-bleed (−24px) — в слайдере body имеет паддинг 16px,
   вертикальный margin = −16px (панель флэш к бордеру), по бокам тоже −16px. */
.migration-editor .editor-stage-nav.editor-nav-bleed {
  margin: -16px -16px 8px;
}
</style>
