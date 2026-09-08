<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useSectionCatalogEdit } from '@/modules/Roleplay/RuleSpace/Composables/useSectionCatalogEdit';
import { useSpaceContext } from '@/modules/Roleplay/RuleSpace/Composables/useSpaceContext';
import { useSpaceRevisionStore } from '@/modules/Roleplay/RuleSpace/Store/spaceRevision';
import { abilitySectionTreeService } from '@/modules/Roleplay/RuleSpace/Service/Instance/abilitySectionTreeService';
import SectionTreeEditor from '@/modules/Roleplay/RuleSpace/Component/Section/SectionTreeEditor.vue';
import SectionEditorDialog from '@/modules/Roleplay/RuleSpace/Component/Section/SectionEditorDialog.vue';
import PublishDialog from '@/modules/Roleplay/RuleSpace/Component/PublishDialog.vue';
import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';
import type { SectionDropPlacement } from '@/modules/Roleplay/RuleSpace/Enum/SectionDropPlacement';

const route = useRoute();
const router = useRouter();
const context = useSpaceContext();
const revisionStore = useSpaceRevisionStore();
const {
  space,
  sections,
  loading,
  loadError,
  mutationErrors,
  canEdit,
  canManageCatalog,
  isDirty,
  isDraftContext,
  viewingRevision,
  latestPublishedRevision,
  load,
  addSection,
  updateSection,
  dropSection,
  removeSection,
  removeBlockReason,
  reset,
} = useSectionCatalogEdit();

const showEditor = ref(false);
const showPublish = ref(false);
const editorMode = ref<'create' | 'edit'>('create');
const editorSection = ref<AbilitySection | null>(null);
const createParentCode = ref<string | null>(null);

const parentPath = computed(() => {
  const flat = abilitySectionTreeService.flatten(sections.value);
  if (editorMode.value === 'edit' && editorSection.value) {
    return flat.find((row) => row.code === editorSection.value?.code)?.path ?? '';
  }
  if (createParentCode.value) {
    return flat.find((row) => row.code === createParentCode.value)?.path ?? '';
  }

  return '';
});

const deleteBlocked = computed(() => {
  if (editorMode.value !== 'edit' || !editorSection.value) return null;

  return removeBlockReason(editorSection.value.code);
});

const ctx = computed(() => String(route.params.ctx ?? 'draft'));

const revisionsList = computed(() => {
  const spaceId = space.value?.id ?? 0;
  const meta = revisionStore.revisionsMeta.get(spaceId) ?? [];
  const items = meta.map((item) => ({
    label: `v${item.revision}`,
    value: item.revision,
  }));
  if (canManageCatalog.value) {
    items.push({ label: 'Черновик', value: -1 });
  }

  return items.reverse();
});

const selectedRevision = computed<number | null>({
  get() {
    if (ctx.value === 'draft') return -1;
    if (/^\d+$/.test(ctx.value)) return Number(ctx.value);

    return null;
  },
  set(value) {
    if (!space.value || value === null) return;
    void router.push(`/space/${space.value.code}/${value === -1 ? 'draft' : value}/sections`);
  },
});

watch(
  () => [context.value.space?.id, ctx.value] as const,
  () => {
    void load();
  },
);

onMounted(() => {
  void load();
});

function openCreate(parentCode: string | null): void {
  if (!canEdit.value) return;
  editorMode.value = 'create';
  editorSection.value = null;
  createParentCode.value = parentCode;
  showEditor.value = true;
}

function openEdit(code: string): void {
  if (!canEdit.value) return;
  editorMode.value = 'edit';
  editorSection.value = sections.value.find((section) => section.code === code) ?? null;
  createParentCode.value = editorSection.value?.parentCode ?? null;
  showEditor.value = true;
}

function onSave(payload: { code: string; name: string; catalogRootFor: string | null }): void {
  const ok =
    editorMode.value === 'create'
      ? addSection({
          code: payload.code,
          name: payload.name,
          parentCode: createParentCode.value,
          sortOrder: 0,
          ...(payload.catalogRootFor ? { catalogRootFor: payload.catalogRootFor } : {}),
        })
      : editorSection.value
        ? updateSection(editorSection.value.code, {
            name: payload.name,
            catalogRootFor: payload.catalogRootFor,
          })
        : false;
  if (ok) showEditor.value = false;
}

function onRemove(): void {
  if (!editorSection.value) return;
  if (removeSection(editorSection.value.code)) showEditor.value = false;
}

function onDrop(sourceCode: string, targetCode: string, placement: SectionDropPlacement): void {
  dropSection(sourceCode, targetCode, placement);
}

function goToDraft(): void {
  if (!space.value) return;
  void router.push(`/space/${space.value.code}/draft/sections`);
}

function onPublished(revision: number): void {
  if (!space.value) return;
  void router.push(`/space/${space.value.code}/${revision}/sections`);
}

function onPublishError(message: string): void {
  mutationErrors.value = [message];
}
</script>

<template>
  <v-container>
    <Teleport to="#editor-actions">
      <v-btn v-if="canManageCatalog && !isDraftContext" variant="tonal" size="small" @click="goToDraft">
        К черновику
      </v-btn>
      <v-btn v-if="canEdit" variant="tonal" size="small" prepend-icon="mdi-plus" @click="openCreate(null)">
        Добавить корень
      </v-btn>
      <v-btn v-if="canEdit && isDirty" variant="text" size="small" @click="reset">Сбросить</v-btn>
      <v-btn v-if="canEdit && isDirty" color="success" variant="tonal" size="small" @click="showPublish = true">
        К публикации
      </v-btn>
    </Teleport>

    <div class="d-flex align-center mb-3 ga-2">
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
      <v-chip v-if="isDirty" color="primary" variant="tonal" size="small"> Есть черновик </v-chip>
    </div>

    <v-alert v-if="!isDraftContext" type="info" variant="tonal" density="compact" class="mb-3">
      Снимок ревизии {{ viewingRevision || ctx }}. Правки — в черновике относительно v{{
        latestPublishedRevision || '—'
      }}.
    </v-alert>

    <v-alert v-if="loadError" type="error" class="mb-4" closable @click:close="loadError = null">
      {{ loadError }}
      <template #append>
        <v-btn size="small" variant="tonal" @click="load">Повторить</v-btn>
      </template>
    </v-alert>
    <v-alert v-if="mutationErrors.length" type="error" class="mb-4">
      <div v-for="(message, index) in mutationErrors" :key="index">{{ message }}</div>
    </v-alert>

    <div v-if="loading" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate width="2" size="28" color="primary" />
    </div>
    <SectionTreeEditor
      v-else
      :sections="sections"
      :readonly="!canEdit"
      @edit="openEdit"
      @add-child="openCreate"
      @drop="onDrop"
    />

    <SectionEditorDialog
      v-model="showEditor"
      :mode="editorMode"
      :section="editorSection"
      :parent-path="parentPath"
      :delete-blocked="deleteBlocked"
      @save="onSave"
      @remove="onRemove"
    />

    <PublishDialog v-model="showPublish" :space="space" @published="onPublished" @error="onPublishError" />
  </v-container>
</template>
