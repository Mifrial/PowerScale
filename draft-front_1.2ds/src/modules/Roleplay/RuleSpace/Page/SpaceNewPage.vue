<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useSpaceStore } from '@/modules/Roleplay/RuleSpace/Store/spaces';
import { useSectionCatalogStore } from '@/modules/Roleplay/RuleSpace/Store/sectionCatalog';
import { useRuleDrafts } from '@/modules/Roleplay/Rule/init';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import type { PendingRevisionImport } from '@/modules/Roleplay/RuleSpace/Dto/PendingRevisionImport';
import { revisionFileCatalogService } from '@/modules/Roleplay/RuleSpace/Service/Instance/revisionFileCatalogService';
import { revisionFileCatalogSyncService } from '@/modules/Roleplay/RuleSpace/Service/Instance/revisionFileCatalogSyncService';
import { revisionFileImportService } from '@/modules/Roleplay/RuleSpace/Service/Instance/revisionFileImportService';
import { revisionFileService } from '@/modules/Roleplay/RuleSpace/Service/Instance/revisionFileService';

const router = useRouter();
const store = useSpaceStore();
const sectionCatalog = useSectionCatalogStore();
const drafts = useRuleDrafts();
const { signal } = useAbortable();

const name = ref('');
const description = ref('');
const inheritFrom = ref<number | null>(null);
const imported = ref<PendingRevisionImport | null>(null);
const importError = ref<string | null>(null);
const catalogHint = ref('');
const saving = ref(false);
const saveError = ref<string | null>(null);

const spaceOptions = computed(() => store.spaces.filter((s) => s.active).map((s) => ({ title: s.name, value: s.id })));

const inheritedSpace = computed(() => store.spaces.find((s) => s.id === inheritFrom.value));

onMounted(() => {
  if (store.spaces.length === 0) {
    store.fetchSpaces(signal.value);
  }
  if (store.pendingImported) {
    imported.value = store.pendingImported;
    store.pendingImported = null;
    inheritFrom.value = null;
    void refreshCatalogHint();
  }
});

async function refreshCatalogHint(): Promise<void> {
  const pending = imported.value;
  if (!pending) {
    catalogHint.value = '';

    return;
  }
  try {
    const catalogs = await revisionFileCatalogService.load(signal.value);
    const merged = revisionFileCatalogSyncService.merge(pending.file, catalogs.keywords, catalogs.mechanics);
    revisionFileImportService.prepare(pending.file, merged, {
      spaceId: 0,
      latest: revisionFileImportService.emptySlice(),
      removeMissing: false,
      draftRules: [],
      draftRemovedCodes: [],
      draftSections: null,
    });
    catalogHint.value = revisionFileCatalogSyncService.formatSummary(
      revisionFileCatalogSyncService.plan(pending.file, catalogs.keywords, catalogs.mechanics),
    );
  } catch (error) {
    imported.value = null;
    catalogHint.value = '';
    importError.value = error instanceof Error ? error.message : 'Не удалось прочитать файл';
  }
}

async function onImportFile(files: File | File[] | null): Promise<void> {
  const file = Array.isArray(files) ? (files[0] ?? null) : files;
  importError.value = null;
  if (!file) return;
  try {
    const parsed = revisionFileService.parse(await file.text());
    imported.value = {
      file: parsed,
      label: `${parsed.source.spaceName} v${parsed.source.revision}, правил: ${parsed.rules.length}`,
    };
    inheritFrom.value = null;
    await refreshCatalogHint();
  } catch (error) {
    imported.value = null;
    catalogHint.value = '';
    importError.value = error instanceof Error ? error.message : 'Не удалось прочитать файл';
  }
}

function clearImport(): void {
  imported.value = null;
  importError.value = null;
  catalogHint.value = '';
}

watch(inheritFrom, (value) => {
  if (value !== null) clearImport();
});

async function save() {
  if (!name.value.trim()) return;
  saving.value = true;
  saveError.value = null;
  try {
    const space = await store.createSpace(
      {
        name: name.value,
        description: description.value,
        inheritFrom: imported.value ? null : inheritFrom.value,
      },
      signal.value,
    );
    if (imported.value) {
      const live = await revisionFileCatalogService.load(signal.value);
      const catalogPlan = revisionFileCatalogSyncService.plan(imported.value.file, live.keywords, live.mechanics);
      if (revisionFileCatalogSyncService.isDirty(catalogPlan)) {
        await revisionFileCatalogSyncService.apply(catalogPlan, signal.value);
      }
      const catalogs = revisionFileCatalogSyncService.isDirty(catalogPlan)
        ? await revisionFileCatalogService.load(signal.value)
        : live;
      const preview = revisionFileImportService.prepare(imported.value.file, catalogs, {
        spaceId: space.id,
        latest: revisionFileImportService.emptySlice(),
        removeMissing: false,
        draftRules: [],
        draftRemovedCodes: [],
        draftSections: null,
      });
      const plan = revisionFileImportService.planApply(preview, 'prefer_file');
      if (plan.saveRules.length) drafts.saveRules(space.id, plan.saveRules);
      if (plan.sectionAction === 'save') sectionCatalog.saveDraft(space.id, plan.sections);
      router.push(`/space/${space.code}/draft`);

      return;
    }
    router.push(space.revision < 1 ? `/space/${space.code}/draft` : `/space/${space.code}`);
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return;
    saveError.value = e instanceof Error ? e.message : 'Не удалось создать пространство';
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <v-container>
    <h1 class="text-h5 mb-4">Создание пространства</h1>

    <v-alert v-if="saveError" type="error" class="mb-4" closable @click:close="saveError = null">
      {{ saveError }}
    </v-alert>

    <v-card>
      <v-card-text>
        <v-text-field v-model="name" label="Название" :rules="[(v) => !!v || 'Обязательное поле']" />

        <v-textarea v-model="description" label="Описание" rows="3" class="mt-4" />

        <v-divider class="my-6" />

        <v-select
          v-model="inheritFrom"
          :items="spaceOptions"
          label="Наследовать от (опционально)"
          hint="Все правила будут скопированы из выбранного пространства"
          persistent-hint
          clearable
          :disabled="!!imported"
        />

        <v-file-input
          class="mt-4"
          accept="application/json,.json"
          label="Или импорт ревизии из файла"
          prepend-icon="mdi-file-import"
          :disabled="inheritFrom !== null"
          @update:model-value="onImportFile"
        />
        <v-alert v-if="importError" type="error" class="mt-2" density="compact">{{ importError }}</v-alert>
        <v-chip v-if="imported" class="mt-2" closable @click:close="clearImport">{{ imported.label }}</v-chip>
        <div v-if="imported" class="text-caption text-medium-emphasis mt-2">
          Правила попадут в черновик. Опубликовать можно после проверки валидатором — первая ревизия будет v1.
        </div>
        <div v-if="catalogHint" class="text-caption mt-1">{{ catalogHint }}</div>

        <v-card v-if="inheritFrom && !imported" variant="tonal" color="info" class="mt-4">
          <v-card-text>
            <div class="d-flex align-center mb-2">
              <v-icon class="mr-2">mdi-information</v-icon>
              <strong>Будет скопировано</strong>
            </div>
            <div class="text-body-2">
              Правил: <strong>{{ inheritedSpace?.rulesCount ?? 0 }}</strong>
            </div>
            <div class="text-caption text-medium-emphasis mt-2">После создания пространство станет независимым.</div>
          </v-card-text>
        </v-card>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="router.back()">Отмена</v-btn>
        <v-btn color="primary" :loading="saving" @click="save">Создать</v-btn>
      </v-card-actions>
    </v-card>
  </v-container>
</template>
