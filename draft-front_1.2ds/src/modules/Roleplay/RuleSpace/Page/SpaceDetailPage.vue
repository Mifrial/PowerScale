<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useSpaceStore } from '@/modules/Roleplay/RuleSpace/Store/spaces';
import { useSpaceRevisionStore } from '@/modules/Roleplay/RuleSpace/Store/spaceRevision';
import { useSectionCatalogStore } from '@/modules/Roleplay/RuleSpace/Store/sectionCatalog';
import { useRuleDrafts } from '@/modules/Roleplay/Rule/init';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { useSpaceContext } from '@/modules/Roleplay/RuleSpace/Composables/useSpaceContext';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import PublishDialog from '@/modules/Roleplay/RuleSpace/Component/PublishDialog.vue';
import RevisionImportDialog from '@/modules/Roleplay/RuleSpace/Component/RevisionImportDialog.vue';
import RuleListPanel from '@/modules/Roleplay/RuleSpace/Component/RuleListPanel.vue';
import { downloadJson } from '@/modules/Core/UI/Utils/downloadJson';
import { revisionFileCatalogService } from '@/modules/Roleplay/RuleSpace/Service/Instance/revisionFileCatalogService';
import { revisionFileCatalogSyncService } from '@/modules/Roleplay/RuleSpace/Service/Instance/revisionFileCatalogSyncService';
import { revisionFileImportService } from '@/modules/Roleplay/RuleSpace/Service/Instance/revisionFileImportService';
import { revisionFileService } from '@/modules/Roleplay/RuleSpace/Service/Instance/revisionFileService';
import type { RevisionFileImportPlan } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileImportPlan';
import type { RevisionFileImportPreview } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileImportPreview';
import type { RevisionFileImportContext } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileImportContext';
import type { RevisionFile } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFile';
import type { RevisionFileConflictPolicy } from '@/modules/Roleplay/RuleSpace/Enum/RevisionFileConflictPolicy';

const route = useRoute();
const router = useRouter();
const spaceStore = useSpaceStore();
const revisionStore = useSpaceRevisionStore();
const sectionCatalog = useSectionCatalogStore();
const drafts = useRuleDrafts();
const { signal } = useAbortable();
const context = useSpaceContext();
const exporting = ref(false);

const space = computed(() => context.value.space);
const showPublishDialog = ref(false);
const showImportDialog = ref(false);
const showDiscardDialog = ref(false);
const ruleToDiscard = ref<Rule | null>(null);
const snackbar = ref({ show: false, text: '', color: '' });

onMounted(() => {
  if (drafts.storageDiscarded.value) {
    snackbar.value = {
      show: true,
      text: 'Черновик правил в браузере повреждён и сброшен',
      color: 'error',
    };
    drafts.acknowledgeStorageDiscarded();
  }
});

const ctx = computed(() => route.params.ctx as string | undefined);
const isDraftContext = computed(() => ctx.value === 'draft');

const hasLocalDraft = computed(() => {
  const spaceId = space.value?.id ?? 0;
  if (!spaceId) return false;

  return drafts.hasDraft(spaceId) || sectionCatalog.isDirty(spaceId, revisionStore.activeRevision?.sections ?? []);
});

const draftRuleCodes = computed(() => new Set(drafts.getDraftRules(space.value?.id ?? 0).map((r) => r.code)));

function formatPublished(unix: number): string {
  const d = new Date(unix * 1000);
  const date = d.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' });
  const time = d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });

  return `${date}, ${time}`;
}

const revisionsList = computed(() => {
  const meta = revisionStore.revisionsMeta.get(space.value?.id ?? 0) ?? [];
  const items = meta.map((m) => ({
    label: `v${m.revision}: ${formatPublished(m.publishedAt)}`,
    value: m.revision,
  }));
  if (hasLocalDraft.value) {
    items.push({ label: 'Черновик', value: -1 });
  }

  return items.reverse();
});

const selectedRevision = computed<number | null>({
  get() {
    const c = ctx.value;
    if (c === 'draft') return -1;
    if (c && /^\d+$/.test(c)) return Number(c);

    return null;
  },
  set(v) {
    if (!space.value || v === null) return;
    router.push(`/space/${space.value.code}/${v === -1 ? 'draft' : v}`);
  },
});

async function exportRevision(): Promise<void> {
  const revision = revisionStore.activeRevision;
  if (!revision || isDraftContext.value) return;
  exporting.value = true;
  try {
    const catalogs = await revisionFileCatalogService.load(signal.value);
    const file = revisionFileService.assemble(revision, catalogs.keywords, catalogs.mechanics);
    downloadJson(`${revision.spaceCode}-v${revision.revision}.json`, file);
  } catch (error) {
    snackbar.value = {
      show: true,
      text: error instanceof Error ? error.message : 'Не удалось выгрузить ревизию',
      color: 'error',
    };
  } finally {
    exporting.value = false;
  }
}

function applyImportPlan(spaceId: number, plan: RevisionFileImportPlan): void {
  if (plan.saveRules.length) drafts.saveRules(spaceId, plan.saveRules);
  for (const code of plan.clearDraftCodes) {
    drafts.removeRule(spaceId, code);
  }
  if (plan.shouldSetRemovedCodes) drafts.setRemovedCodes(spaceId, plan.removedCodes);
  if (plan.sectionAction === 'save') sectionCatalog.saveDraft(spaceId, plan.sections);
  if (plan.sectionAction === 'discard') sectionCatalog.discardDraft(spaceId);
}

async function applyFileToSpace(
  file: RevisionFile,
  spaceId: number,
  policy: RevisionFileConflictPolicy,
  context: Omit<RevisionFileImportContext, 'spaceId' | 'latest'> & { latest?: RevisionFileImportContext['latest'] },
): Promise<{ plan: RevisionFileImportPlan; catalogDirty: boolean; catalogSummary: string }> {
  const live = await revisionFileCatalogService.load(signal.value);
  const catalogPlan = revisionFileCatalogSyncService.plan(file, live.keywords, live.mechanics);
  const catalogDirty = revisionFileCatalogSyncService.isDirty(catalogPlan);
  if (catalogDirty) await revisionFileCatalogSyncService.apply(catalogPlan, signal.value);
  const catalogs = catalogDirty ? await revisionFileCatalogService.load(signal.value) : live;
  const latest = context.latest ?? (await revisionFileImportService.loadLatest(spaceId, signal.value));
  const preview = revisionFileImportService.prepare(file, catalogs, {
    spaceId,
    latest,
    removeMissing: context.removeMissing,
    draftRules: context.draftRules,
    draftRemovedCodes: context.draftRemovedCodes,
    draftSections: context.draftSections,
  });
  const plan = revisionFileImportService.planApply(preview, policy);
  if (!plan.isNoOp) applyImportPlan(spaceId, plan);

  return {
    plan,
    catalogDirty,
    catalogSummary: revisionFileCatalogSyncService.formatSummary(catalogPlan),
  };
}

async function onImportConfirm(payload: {
  preview: RevisionFileImportPreview;
  intoCurrent: boolean;
  policy: RevisionFileConflictPolicy;
}): Promise<void> {
  try {
    if (!payload.intoCurrent) {
      spaceStore.pendingImported = {
        file: payload.preview.file,
        label: `${payload.preview.file.source.spaceName} v${payload.preview.file.source.revision}`,
      };
      router.push('/spaces/new');

      return;
    }
    const spaceId = space.value?.id ?? 0;
    if (!spaceId) return;
    const result = await applyFileToSpace(payload.preview.file, spaceId, payload.policy, {
      removeMissing: payload.preview.removeMissing,
      draftRules: drafts.getDraftRules(spaceId),
      draftRemovedCodes: drafts.getRemovedCodes(spaceId),
      draftSections: sectionCatalog.getDraftSections(spaceId),
    });
    if (result.plan.isNoOp && !result.catalogDirty) {
      snackbar.value = { show: true, text: result.plan.summary, color: 'info' };

      return;
    }
    const text = result.catalogDirty ? `${result.plan.summary}. ${result.catalogSummary}` : result.plan.summary;
    snackbar.value = { show: true, text, color: 'success' };
    router.push(`/space/${space.value?.code}/draft`);
  } catch (error) {
    snackbar.value = {
      show: true,
      text: error instanceof Error ? error.message : 'Не удалось импортировать',
      color: 'error',
    };
  }
}

function openPublishDialog() {
  showPublishDialog.value = true;
}

function onPublished(revision: number) {
  const s = space.value;
  if (!s) return;
  if (spaceStore.currentSpace) spaceStore.currentSpace = { ...spaceStore.currentSpace, revision };
  router.push(`/space/${s.code}/${revision}`);
}

function showDiscardRuleDialog(rule: Rule) {
  ruleToDiscard.value = rule;
  showDiscardDialog.value = true;
}

function discardRule() {
  if (!space.value || !ruleToDiscard.value) return;
  drafts.removeRule(space.value.id, ruleToDiscard.value.code);
  showDiscardDialog.value = false;
  ruleToDiscard.value = null;

  // Если черновиков больше нет, переходим на последнюю ревизию
  if (!hasLocalDraft.value) {
    router.replace(`/space/${space.value.code}/${space.value.revision < 1 ? 'draft' : space.value.revision}`);
  }
}
</script>

<template>
  <v-container v-if="space">
    <div class="d-flex align-center mb-4">
      <h1 class="text-h5">{{ space.name }}</h1>
      <v-spacer />
      <v-btn variant="text" prepend-icon="mdi-cog" @click="router.push(`/space/${space.code}/settings`)">
        Настройки
      </v-btn>
    </div>

    <v-card-subtitle class="mb-4">{{ space.description }}</v-card-subtitle>

    <!-- Контекст просмотра -->
    <div class="d-flex align-center mb-4 gap-2">
      <v-select
        v-model="selectedRevision"
        :items="revisionsList"
        item-title="label"
        item-value="value"
        label="Версия"
        density="compact"
        hide-details
        style="max-width: 220px"
      />

      <v-chip v-if="hasLocalDraft" color="primary" variant="tonal" size="small"> Есть черновик </v-chip>

      <v-spacer />

      <v-btn
        v-if="!isDraftContext"
        variant="tonal"
        size="small"
        prepend-icon="mdi-download"
        :loading="exporting"
        @click="exportRevision"
      >
        Экспорт
      </v-btn>
      <v-btn variant="tonal" size="small" prepend-icon="mdi-upload" @click="showImportDialog = true"> Импорт </v-btn>

      <template v-if="hasLocalDraft && isDraftContext">
        <v-btn variant="tonal" color="success" size="small" prepend-icon="mdi-source-branch" @click="openPublishDialog">
          Опубликовать
        </v-btn>
      </template>
    </div>

    <RuleListPanel
      :rules="revisionStore.effectiveRules"
      :space-code="space.code"
      :ctx="ctx"
      :is-draft-context="isDraftContext"
      :draft-rule-codes="draftRuleCodes"
      @discard="showDiscardRuleDialog"
    />

    <!-- Publish dialog -->
    <PublishDialog
      v-model="showPublishDialog"
      :space="space"
      @published="onPublished"
      @error="(m) => (snackbar = { show: true, text: m, color: 'error' })"
    />

    <RevisionImportDialog
      v-model="showImportDialog"
      :allow-current="true"
      :space-id="space.id"
      :draft-rules="drafts.getDraftRules(space.id)"
      :draft-removed-codes="drafts.getRemovedCodes(space.id)"
      :draft-sections="sectionCatalog.getDraftSections(space.id)"
      @confirm="onImportConfirm"
    />

    <!-- Discard rule dialog -->
    <v-dialog v-model="showDiscardDialog" max-width="500">
      <v-card>
        <v-card-title>Откатить изменения</v-card-title>
        <v-card-text>
          <div class="text-body-2 mb-4">
            Вы уверены, что хотите откатить изменения в правиле "{{ ruleToDiscard?.name }}"?
          </div>
          <div class="text-body-2 text-medium-emphasis">
            Правило вернётся к состоянию из последней опубликованной версии.
          </div>
        </v-card-text>
        <v-card-actions>
          <v-btn variant="text" @click="showDiscardDialog = false">Отмена</v-btn>
          <v-btn color="error" variant="tonal" @click="discardRule"> Откатить </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">
      {{ snackbar.text }}
    </v-snackbar>
  </v-container>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}
.cursor-pointer {
  cursor: pointer;
}
</style>
