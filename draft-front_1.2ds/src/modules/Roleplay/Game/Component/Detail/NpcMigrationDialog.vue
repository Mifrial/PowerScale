<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useSpaceStore } from '@/modules/Roleplay/Space/Store/spaces';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { characterMigrationService } from '@/modules/Roleplay/Character/Service/Instance/characterMigrationService';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { CharacterSheetEditor } from '@/modules/Roleplay/Character/init';
import MigrationReport from '@/modules/Roleplay/Character/Component/MigrationReport.vue';
import SlidePanel from '@/modules/Core/UI/Component/SlidePanel.vue';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { MigrationResult } from '@/modules/Roleplay/Character/Service/CharacterMigrationService';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { isEmptyMembershipDiff, membershipDiff } from '@/modules/Roleplay/Game/Utils/membershipDiff';

const props = defineProps<{
  gameSpaceId: number;
  gameSpaceCode: string;
  gameRulesRevision: number;
  npc: GameNpc | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const emit = defineEmits<{
  applied: [];
}>();

const spaceStore = useSpaceStore();
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
const originalVersion = ref<CharacterVersion | null>(null);
const oldRules = ref<Rule[]>([]);

const npcLimits = computed<CharacterCreationConfig>(() => ({
  osTotal: null,
  orTotal: null,
  moneyBudget: null,
}));

const draftKey = computed(() => (props.npc ? `npc:${props.npc.id}:migrate` : null));

const sheetUnchanged = computed(() => {
  const source = props.npc?.version;
  const pending = result.value?.version;
  if (!source || !pending) return false;

  return isEmptyMembershipDiff(membershipDiff(source, pending));
});

function resolve(ruleId: string): string {
  return names.value[ruleId] ?? ruleId;
}

const compareResult = computed<MigrationResult | null>(() => {
  if (!editorCompareOpen.value) return result.value;
  if (phase.value === 'editor') {
    const original = originalVersion.value;
    const draft = draftKey.value ? draftStore.draftOf(draftKey.value) : undefined;
    if (!original || !draft?.build || targetRules.value.length === 0) return result.value;

    return characterMigrationService.compareCurrent(
      original,
      draft.build,
      oldRules.value,
      targetRules.value,
      npcLimits.value,
    );
  }

  return result.value;
});

async function applyVersion(version: CharacterVersion): Promise<void> {
  const npc = props.npc;
  if (!npc) return;
  await getGameApi().updateNpc(npc.id, {
    name: version.name,
    shortDescription: version.shortDescription,
    fullDescription: version.fullDescription,
    tags: npc.tags,
    visibility: npc.visibility,
    version,
  });
  if (draftKey.value) draftStore.discard(draftKey.value);
  open.value = false;
  emit('applied');
}

async function run(): Promise<void> {
  const npc = props.npc;
  const source = npc?.version;
  if (!npc || !source) return;
  loading.value = true;
  error.value = null;
  try {
    const oldSpace = await spaceStore.fetchSpaceByCode(source.spaceCode, signal.value);
    const oldRevision = await spaceRevisionStore.fetchRevision(oldSpace.id, source.rulesRevision, signal.value);
    originalVersion.value = source;
    oldRules.value = oldRevision.rules;
    const newRevision = await spaceRevisionStore.fetchRevision(
      props.gameSpaceId,
      props.gameRulesRevision,
      signal.value,
    );
    names.value = Object.fromEntries(newRevision.rules.map((rule) => [rule.id, rule.name]));
    targetRules.value = newRevision.rules;
    result.value = characterMigrationService.migrate({
      version: source,
      oldRules: oldRevision.rules,
      oldSpaceId: oldSpace.id,
      newRules: newRevision.rules,
      newSpaceId: props.gameSpaceId,
      newSpaceCode: props.gameSpaceCode,
      newRevision: props.gameRulesRevision,
      effectiveLimits: npcLimits.value,
    });
    phase.value = 'report';
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось запустить миграцию';
  } finally {
    loading.value = false;
  }
}

function submit(): void {
  const migration = result.value;
  if (!migration) return;
  submitting.value = true;
  error.value = null;
  void (async () => {
    try {
      await applyVersion(migration.version);
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Не удалось применить перевод';
    } finally {
      submitting.value = false;
    }
  })();
}

function openEditor(): void {
  const migration = result.value;
  if (!migration || !props.npc || draftKey.value === null) return;
  const build = characterBuildService.fromVersion(migration.version, props.gameSpaceId, targetRules.value as never);
  draftStore.initDraft(draftKey.value, build, npcLimits.value);
  phase.value = 'editor';
}

async function handleEditorSave(version: CharacterVersion): Promise<void> {
  await applyVersion(version);
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
          <span class="font-weight-bold text-body-2">Перевод на новую ревизию — {{ npc?.name }}</span>
          <span class="text-caption text-medium-emphasis"
            >Всё, чего нет или чьим требованиям лист не соответствует, сбрасывается. Результат сразу в лист НПС.</span
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
          {{
            sheetUnchanged
              ? 'Изменений листа нет — перевод применится сразу.'
              : 'Лист НПС переводится на ревизию игры и сразу сохранится (без модерации).'
          }}
        </p>
        <MigrationReport :result="result" :resolve="resolve" />
      </template>

      <template v-if="phase === 'editor' && draftKey">
        <div class="migration-editor">
          <CharacterSheetEditor
            :draft-key="draftKey"
            :require-race="false"
            actions-target="#npc-migration-editor-actions"
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
          <v-btn color="primary" variant="tonal" :loading="submitting" @click="submit">Применить</v-btn>
        </template>
      </div>
      <div
        v-if="phase === 'editor' && draftKey"
        id="npc-migration-editor-actions"
        class="d-flex justify-end ga-2 pa-3"
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
.migration-header {
  margin: -2px;
}
</style>

<style>
.migration-editor .editor-stage-nav.editor-nav-bleed {
  margin: -16px -16px 8px;
}
</style>
