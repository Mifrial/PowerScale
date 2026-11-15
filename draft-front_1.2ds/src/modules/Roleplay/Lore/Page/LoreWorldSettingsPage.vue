<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import type { LoreConflict } from '@/modules/Roleplay/Lore/Dto/LoreConflict';
import type { LoreSectionInput } from '@/modules/Roleplay/Lore/Dto/LoreSectionInput';
import type { LoreWorldDetail } from '@/modules/Roleplay/Lore/Dto/LoreWorldDetail';
import type { LoreWorldVisibility } from '@/modules/Roleplay/Lore/Enum/LoreWorldVisibility';
import { LORE_WORLD_VISIBILITY_OPTIONS } from '@/modules/Roleplay/Lore/Constant/LORE_VISIBILITY_OPTIONS';
import { getLoreApi } from '@/modules/Roleplay/Lore/init';
import { LoreApiError } from '@/modules/Roleplay/Lore/Service/LoreApiError';
import { useSpaceCatalog } from '@/modules/Roleplay/RuleSpace/init';
import LoreConflictDialog from '@/modules/Roleplay/Lore/Component/LoreConflictDialog.vue';
import LoreExportDialog from '@/modules/Roleplay/Lore/Component/LoreExportDialog.vue';
import LoreImportDialog from '@/modules/Roleplay/Lore/Component/LoreImportDialog.vue';
import LoreNotice from '@/modules/Roleplay/Lore/Component/LoreNotice.vue';
import LoreSectionEditor from '@/modules/Roleplay/Lore/Component/LoreSectionEditor.vue';
import { useLoreCrumbStore } from '@/modules/Roleplay/Lore/Store/crumbs';

const route = useRoute();
const router = useRouter();
const spaceCatalog = useSpaceCatalog();
const crumbStore = useLoreCrumbStore();

const worldCode = computed(() => String(route.params.worldCode ?? ''));
const world = ref<LoreWorldDetail | null>(null);
const loading = ref(false);
const saving = ref(false);
const error = ref<string | null>(null);
const notice = ref<{ text: string; color: 'success' | 'error' | 'info' | 'warning' } | null>(null);
const conflict = ref<LoreConflict | null>(null);
const conflictTarget = ref<'meta' | 'sections' | 'archive'>('meta');
const exportOpen = ref(false);
const importOpen = ref(false);
const activeTab = ref<'main' | 'sections'>('main');
const sectionEditor = ref<InstanceType<typeof LoreSectionEditor> | null>(null);

const meta = reactive({
  name: '',
  description: '',
  visibility: 'private' as LoreWorldVisibility,
  spaceCode: '',
  version: 0,
});
const sections = ref<LoreSectionInput[]>([]);

const spaceItems = computed(() => {
  const items = spaceCatalog.spaces.value.map((space) => ({ title: space.name, value: space.code }));
  if (meta.spaceCode !== '' && !items.some((item) => item.value === meta.spaceCode)) {
    items.unshift({ title: `${meta.spaceCode} (нет доступа)`, value: meta.spaceCode });
  }

  return [{ title: '— Без пространства —', value: '' }, ...items];
});
const articleCounts = computed<Record<string, number>>(() => {
  const counts: Record<string, number> = {};
  for (const article of world.value?.articles ?? []) {
    if (article.sectionCode === null) continue;
    counts[article.sectionCode] = (counts[article.sectionCode] ?? 0) + 1;
  }

  return counts;
});

onMounted(async () => {
  await load();
  await spaceCatalog.fetchSpaces();
});

async function load(): Promise<void> {
  loading.value = true;
  error.value = null;
  try {
    const detail = await getLoreApi().getWorld(worldCode.value);
    world.value = detail;
    crumbStore.setWorld(worldCode.value, detail.name);
    meta.name = detail.name;
    meta.description = detail.description;
    meta.visibility = detail.visibility;
    meta.spaceCode = detail.spaceCode;
    meta.version = detail.version;
    sections.value = detail.sections.map((section) => ({
      code: section.code,
      name: section.name,
      description: section.description,
      parentCode: section.parentCode,
      sortOrder: section.sortOrder,
      icon: section.icon,
    }));
  } catch (exception) {
    error.value = exception instanceof LoreApiError ? exception.message : 'Не удалось загрузить мир';
  } finally {
    loading.value = false;
  }
}

async function saveCurrent(): Promise<void> {
  if (activeTab.value === 'sections') {
    await saveSections();

    return;
  }
  await saveMeta();
}

async function saveMeta(forcedVersion?: number): Promise<void> {
  if (meta.name.trim() === '') {
    error.value = 'Укажите название мира.';

    return;
  }
  saving.value = true;
  error.value = null;
  try {
    const saved = await getLoreApi().saveWorld({
      world: {
        code: worldCode.value,
        name: meta.name.trim(),
        description: meta.description,
        visibility: meta.visibility,
        spaceCode: meta.spaceCode,
      },
      version: forcedVersion ?? meta.version,
    });
    meta.version = saved.version;
    if (world.value) world.value = { ...world.value, ...saved };
    crumbStore.setWorld(worldCode.value, saved.name);
    conflict.value = null;
    notice.value = { text: 'Настройки мира сохранены.', color: 'success' };
  } catch (exception) {
    if (exception instanceof LoreApiError && exception.code === 'LORE_VERSION_CONFLICT') {
      conflict.value = exception.details as LoreConflict;
      conflictTarget.value = 'meta';
    } else {
      error.value = exception instanceof LoreApiError ? exception.message : 'Не удалось сохранить мир';
    }
  } finally {
    saving.value = false;
  }
}

async function saveSections(forcedVersion?: number): Promise<void> {
  saving.value = true;
  error.value = null;
  try {
    const detail = await getLoreApi().saveTree(worldCode.value, sections.value, forcedVersion ?? meta.version);
    world.value = detail;
    meta.version = detail.version;
    conflict.value = null;
    notice.value = { text: 'Дерево разделов сохранено.', color: 'success' };
  } catch (exception) {
    if (exception instanceof LoreApiError && exception.code === 'LORE_VERSION_CONFLICT') {
      conflict.value = exception.details as LoreConflict;
      conflictTarget.value = 'sections';
    } else {
      error.value = exception instanceof LoreApiError ? exception.message : 'Не удалось сохранить дерево';
    }
  } finally {
    saving.value = false;
  }
}

async function toggleArchive(forcedVersion?: number): Promise<void> {
  if (!world.value) return;
  saving.value = true;
  error.value = null;
  try {
    const saved = await getLoreApi().archiveWorld(
      worldCode.value,
      !world.value.isArchived,
      forcedVersion ?? meta.version,
    );
    meta.version = saved.version;
    world.value = { ...world.value, ...saved };
    notice.value = {
      text: saved.isArchived ? 'Мир перемещён в архив.' : 'Мир восстановлен из архива.',
      color: 'success',
    };
  } catch (exception) {
    if (exception instanceof LoreApiError && exception.code === 'LORE_VERSION_CONFLICT') {
      conflict.value = exception.details as LoreConflict;
      conflictTarget.value = 'archive';
    } else {
      error.value = exception instanceof LoreApiError ? exception.message : 'Не удалось изменить архив';
    }
  } finally {
    saving.value = false;
  }
}

function overwrite(): void {
  const currentVersion = conflict.value?.currentVersion;
  if (currentVersion === undefined) return;
  if (conflictTarget.value === 'sections') {
    void saveSections(currentVersion);
  } else if (conflictTarget.value === 'archive') {
    void toggleArchive(currentVersion);
  } else {
    void saveMeta(currentVersion);
  }
}

function reload(): void {
  conflict.value = null;
  void load();
}

function addSection(): void {
  sectionEditor.value?.openRootCreate();
}

function imported(worldCodeValue: string): void {
  importOpen.value = false;
  void router.push({ name: 'LoreWorld', params: { worldCode: worldCodeValue } });
}
</script>

<template>
  <v-container>
    <Teleport to="#editor-actions" defer>
      <v-btn v-if="world" variant="tonal" size="small" prepend-icon="mdi-download" @click="exportOpen = true">
        Экспорт
      </v-btn>
      <v-btn v-if="world" variant="tonal" size="small" prepend-icon="mdi-upload" @click="importOpen = true">
        Импорт
      </v-btn>
      <v-btn
        v-if="world && world.canEdit && activeTab === 'sections'"
        variant="tonal"
        size="small"
        prepend-icon="mdi-plus"
        @click="addSection"
      >
        Раздел
      </v-btn>
      <v-btn v-if="world && world.canEdit" color="primary" size="small" :loading="saving" @click="saveCurrent">
        Сохранить
      </v-btn>
    </Teleport>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
    <div v-if="loading && !world" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate width="2" size="28" color="primary" />
    </div>

    <template v-if="world">
      <v-tabs v-model="activeTab" color="primary" density="compact" class="mb-4">
        <v-tab value="main">Основное</v-tab>
        <v-tab value="sections">Разделы</v-tab>
      </v-tabs>

      <v-window v-model="activeTab">
        <v-window-item value="main">
          <v-card max-width="760">
            <v-card-text>
              <v-text-field v-model="meta.name" label="Название" class="mb-4" />
              <v-select
                v-model="meta.spaceCode"
                :items="spaceItems"
                item-title="title"
                item-value="value"
                label="Пространство правил"
                hint="Необязательно: по нему открываются ссылки на правила из статей"
                persistent-hint
                variant="outlined"
                density="comfortable"
                class="mb-4"
              />
              <v-textarea v-model="meta.description" label="Описание" rows="5" auto-grow class="mb-4" />
              <v-select
                v-model="meta.visibility"
                :items="LORE_WORLD_VISIBILITY_OPTIONS"
                item-title="title"
                item-value="value"
                label="Видимость"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
              />
              <div class="text-caption text-medium-emphasis mt-4">Версия: {{ meta.version }}</div>
            </v-card-text>
            <v-card-actions v-if="world.canEdit">
              <v-btn
                variant="text"
                :color="world.isArchived ? 'success' : 'warning'"
                :prepend-icon="world.isArchived ? 'mdi-archive-arrow-up' : 'mdi-archive-arrow-down'"
                :loading="saving"
                @click="toggleArchive"
              >
                {{ world.isArchived ? 'Восстановить мир' : 'В архив' }}
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-window-item>

        <v-window-item value="sections">
          <v-card>
            <v-card-text>
              <LoreSectionEditor ref="sectionEditor" v-model="sections" :article-counts="articleCounts" />
            </v-card-text>
          </v-card>
        </v-window-item>
      </v-window>
    </template>

    <LoreExportDialog v-if="world" v-model="exportOpen" :world="world" />
    <LoreImportDialog
      v-model="importOpen"
      :worlds="world ? [{ code: world.code, name: world.name }] : []"
      @imported="imported"
    />

    <LoreConflictDialog :conflict="conflict" :current-version="meta.version" @reload="reload" @overwrite="overwrite" />

    <LoreNotice
      :model-value="notice !== null"
      :text="notice?.text ?? ''"
      :color="notice?.color ?? 'success'"
      @update:model-value="notice = null"
    />
  </v-container>
</template>
