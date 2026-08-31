<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useSpaceStore } from '@/modules/Roleplay/Space/Store/spaces';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { useRuleDrafts } from '@/modules/Roleplay/Rule/init';
import { useSpaceContext } from '@/modules/Roleplay/Space/Composables/useSpaceContext';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import PublishDialog from '@/modules/Roleplay/Space/Component/PublishDialog.vue';
import RevisionImportDialog from '@/modules/Roleplay/Space/Component/RevisionImportDialog.vue';
import RuleListPanel from '@/modules/Roleplay/Space/Component/RuleListPanel.vue';
import { downloadJson } from '@/modules/Core/UI/Utils/downloadJson';
import { revisionFileService } from '@/modules/Roleplay/Space/Service/Instance/revisionFileService';
import type { RevisionFile } from '@/modules/Roleplay/Space/Dto/RevisionFile';

const route = useRoute();
const router = useRouter();
const spaceStore = useSpaceStore();
const revisionStore = useSpaceRevisionStore();
const drafts = useRuleDrafts();
const context = useSpaceContext();

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

const draftRuleCodes = computed(() => new Set(drafts.getDraftRules(space.value?.id ?? 0).map((r) => r.code)));

function formatPublished(iso: string): string {
  const d = new Date(iso);
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
  if (drafts.hasDraft(space.value?.id ?? 0)) {
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

function exportRevision(): void {
  const revision = revisionStore.activeRevision;
  if (!revision || isDraftContext.value) return;
  downloadJson(`${revision.spaceCode}-v${revision.revision}.json`, revisionFileService.serialize(revision));
}

function onImportConfirm(payload: { file: RevisionFile; intoCurrent: boolean; removeMissing: boolean }): void {
  if (!payload.intoCurrent) {
    spaceStore.pendingImportedRules = payload.file.revision.rules;
    router.push('/spaces/new');

    return;
  }
  const spaceId = space.value?.id;
  if (!spaceId) return;
  const published = revisionStore.activeRevision?.rules ?? [];
  const diff = revisionFileService.diffAgainstPublished(payload.file.revision.rules, published, spaceId, {
    removeMissing: payload.removeMissing,
    existingRemovedCodes: drafts.getRemovedCodes(spaceId),
  });
  if (revisionFileService.isEmptyDiff(diff)) {
    snackbar.value = { show: true, text: revisionFileService.formatImportSummary(diff), color: 'info' };

    return;
  }
  drafts.saveRules(spaceId, [...diff.changed, ...diff.added]);
  drafts.setRemovedCodes(spaceId, diff.removedCodes);
  snackbar.value = { show: true, text: revisionFileService.formatImportSummary(diff), color: 'success' };
  router.push(`/space/${space.value?.code}/draft`);
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
  if (!drafts.hasDraft(space.value.id)) {
    router.replace(`/space/${space.value.code}/${space.value.revision}`);
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

      <v-chip v-if="drafts.hasDraft(space.id)" color="primary" variant="tonal" size="small"> Есть черновик </v-chip>

      <v-spacer />

      <v-btn v-if="!isDraftContext" variant="tonal" size="small" prepend-icon="mdi-download" @click="exportRevision">
        Экспорт
      </v-btn>
      <v-btn variant="tonal" size="small" prepend-icon="mdi-upload" @click="showImportDialog = true"> Импорт </v-btn>

      <template v-if="drafts.hasDraft(space.id) && isDraftContext">
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

    <RevisionImportDialog v-model="showImportDialog" :allow-current="true" @confirm="onImportConfirm" />

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
