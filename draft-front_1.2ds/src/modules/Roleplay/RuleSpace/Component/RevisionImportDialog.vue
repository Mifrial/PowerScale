<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';
import type { RevisionFile } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFile';
import type { RevisionFileImportPreview } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileImportPreview';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RevisionFileConflictPolicy } from '@/modules/Roleplay/RuleSpace/Enum/RevisionFileConflictPolicy';
import { revisionFileCatalogService } from '@/modules/Roleplay/RuleSpace/Service/Instance/revisionFileCatalogService';
import { revisionFileCatalogSyncService } from '@/modules/Roleplay/RuleSpace/Service/Instance/revisionFileCatalogSyncService';
import { revisionFileImportService } from '@/modules/Roleplay/RuleSpace/Service/Instance/revisionFileImportService';
import { revisionFileService } from '@/modules/Roleplay/RuleSpace/Service/Instance/revisionFileService';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import type { RevisionFileCatalogPlan } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileCatalogPlan';

const props = defineProps<{
  modelValue: boolean;
  allowCurrent: boolean;
  spaceId: number;
  draftRules: Rule[];
  draftRemovedCodes: string[];
  draftSections: AbilitySection[] | null;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  confirm: [payload: { preview: RevisionFileImportPreview; intoCurrent: boolean; policy: RevisionFileConflictPolicy }];
}>();

const open = computed({
  get: () => props.modelValue,
  set: (v: boolean) => emit('update:modelValue', v),
});

const { signal } = useAbortable();
const parseError = ref<string | null>(null);
const previewError = ref<string | null>(null);
const parsed = ref<RevisionFile | null>(null);
const preview = ref<RevisionFileImportPreview | null>(null);
const catalogPlan = ref<RevisionFileCatalogPlan | null>(null);
const target = ref<'current' | 'new'>('current');
const removeMissing = ref(false);
const picking = ref(false);
const previewLoading = ref(false);

watch(
  () => props.modelValue,
  (isOpen) => {
    if (!isOpen) return;
    parseError.value = null;
    previewError.value = null;
    parsed.value = null;
    preview.value = null;
    catalogPlan.value = null;
    target.value = props.allowCurrent ? 'current' : 'new';
    removeMissing.value = false;
  },
);

const intoCurrent = computed(() => props.allowCurrent && target.value === 'current');

async function onFile(files: File | File[] | null): Promise<void> {
  const file = Array.isArray(files) ? (files[0] ?? null) : files;
  parseError.value = null;
  previewError.value = null;
  parsed.value = null;
  preview.value = null;
  catalogPlan.value = null;
  if (!file) return;
  picking.value = true;
  try {
    parsed.value = revisionFileService.parse(await file.text());
    await rebuildPreview();
  } catch (error) {
    parseError.value = error instanceof Error ? error.message : 'Не удалось прочитать файл';
  } finally {
    picking.value = false;
  }
}

async function rebuildPreview(): Promise<void> {
  const file = parsed.value;
  if (!file) return;
  previewLoading.value = true;
  previewError.value = null;
  try {
    const catalogs = await revisionFileCatalogService.load(signal.value);
    const latest =
      intoCurrent.value && props.spaceId
        ? await revisionFileImportService.loadLatest(props.spaceId, signal.value)
        : revisionFileImportService.emptySlice();
    catalogPlan.value = revisionFileCatalogSyncService.plan(file, catalogs.keywords, catalogs.mechanics);
    const merged = revisionFileCatalogSyncService.merge(file, catalogs.keywords, catalogs.mechanics);
    const built = revisionFileImportService.prepare(file, merged, {
      spaceId: intoCurrent.value ? props.spaceId : 0,
      latest,
      removeMissing: intoCurrent.value && removeMissing.value,
      draftRules: intoCurrent.value ? props.draftRules : [],
      draftRemovedCodes: intoCurrent.value ? props.draftRemovedCodes : [],
      draftSections: intoCurrent.value ? props.draftSections : null,
    });
    const extra = (catalogPlan.value.cannotReactivate ?? []).map((code) => ({
      path: '/keywords',
      message: `Нельзя включить признак ${code}: реактивации нет`,
    }));
    preview.value = extra.length ? { ...built, warnings: [...built.warnings, ...extra] } : built;
  } catch (error) {
    preview.value = null;
    catalogPlan.value = null;
    previewError.value = error instanceof Error ? error.message : 'Не удалось подготовить импорт';
  } finally {
    previewLoading.value = false;
  }
}

watch([target, removeMissing], () => {
  if (parsed.value) void rebuildPreview();
});

function confirm(policy: RevisionFileConflictPolicy): void {
  if (!preview.value) return;
  emit('confirm', {
    preview: preview.value,
    intoCurrent: intoCurrent.value,
    policy,
  });
  open.value = false;
}

const sourceLabel = computed(() => {
  const file = parsed.value;
  if (!file) return '';

  return `${file.source.spaceName} (${file.source.spaceCode}) v${file.source.revision}, правил: ${file.rules.length}`;
});

const summary = computed(() => {
  if (!preview.value) return '';

  return revisionFileService.formatImportSummary(preview.value.diff);
});

const catalogSummary = computed(() => {
  if (!catalogPlan.value) return '';

  return revisionFileCatalogSyncService.formatSummary(catalogPlan.value);
});

const showConflict = computed(() => intoCurrent.value && preview.value?.hasTargetDraft === true);

const canConfirm = computed(
  () => preview.value !== null && !parseError.value && !previewError.value && !previewLoading.value,
);
</script>

<template>
  <v-dialog v-model="open" max-width="640">
    <v-card>
      <v-card-title>Импорт ревизии</v-card-title>
      <v-card-text>
        <v-file-input
          accept="application/json,.json"
          label="Файл ревизии"
          prepend-icon="mdi-file-import"
          :loading="picking || previewLoading"
          @update:model-value="onFile"
        />
        <v-alert v-if="parseError" type="error" class="mt-2" density="compact">{{ parseError }}</v-alert>
        <v-alert v-else-if="previewError" type="error" class="mt-2" density="compact">{{ previewError }}</v-alert>
        <div v-if="parsed" class="text-body-2 mt-2">{{ sourceLabel }}</div>
        <div v-if="preview" class="text-body-2 mt-1">{{ summary }}</div>
        <div v-if="catalogPlan" class="text-body-2 mt-1">{{ catalogSummary }}</div>
        <ul v-if="preview?.warnings.length" class="text-caption mt-2">
          <li v-for="(warning, index) in preview.warnings" :key="index">{{ warning.message }}</li>
        </ul>

        <v-radio-group v-if="allowCurrent && parsed" v-model="target" class="mt-4" hide-details>
          <v-radio value="current" label="В это пространство (черновик)" />
          <v-radio value="new" label="Создать новое пространство" />
        </v-radio-group>

        <v-switch
          v-if="allowCurrent && target === 'current' && parsed"
          v-model="removeMissing"
          label="Убрать правила, которых нет в файле"
          color="primary"
          hide-details
          class="mt-2"
        />
        <div
          v-if="allowCurrent && target === 'current' && removeMissing"
          class="text-caption text-medium-emphasis mt-2"
        >
          В новой ревизии они получат маркер удаления. Уже опубликованные ревизии не изменятся.
        </div>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="open = false">Отмена</v-btn>
        <template v-if="showConflict">
          <v-btn color="secondary" variant="tonal" :disabled="!canConfirm" @click="confirm('prefer_draft')">
            Приоритет в черновике
          </v-btn>
          <v-btn color="primary" variant="tonal" :disabled="!canConfirm" @click="confirm('prefer_file')">
            Приоритет в выгрузке
          </v-btn>
        </template>
        <v-btn v-else color="primary" variant="tonal" :disabled="!canConfirm" @click="confirm('prefer_file')">
          Импортировать
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
