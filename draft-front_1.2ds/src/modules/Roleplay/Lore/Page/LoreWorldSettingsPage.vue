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
import LoreConflictDialog from '@/modules/Roleplay/Lore/Component/LoreConflictDialog.vue';
import LoreExportDialog from '@/modules/Roleplay/Lore/Component/LoreExportDialog.vue';
import LoreImportDialog from '@/modules/Roleplay/Lore/Component/LoreImportDialog.vue';
import LoreSectionEditor from '@/modules/Roleplay/Lore/Component/LoreSectionEditor.vue';

const route = useRoute();
const router = useRouter();
const worldCode = computed(() => String(route.params.worldCode ?? ''));
const world = ref<LoreWorldDetail | null>(null);
const loading = ref(false);
const saving = ref(false);
const error = ref<string | null>(null);
const snackbar = ref<string | null>(null);
const conflict = ref<LoreConflict | null>(null);
const exportOpen = ref(false);
const importOpen = ref(false);

const meta = reactive({
  name: '',
  description: '',
  visibility: 'private' as LoreWorldVisibility,
  spaceCode: '',
  version: 0,
});
const sections = ref<LoreSectionInput[]>([]);

onMounted(load);

async function load(): Promise<void> {
  loading.value = true;
  error.value = null;
  try {
    const detail = await getLoreApi().getWorld(worldCode.value);
    world.value = detail;
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

async function saveMeta(forcedVersion?: number): Promise<void> {
  if (meta.name.trim() === '') {
    error.value = 'Укажите название мира.';

    return;
  }
  saving.value = true;
  error.value = null;
  try {
    await getLoreApi().saveWorld({
      world: {
        code: worldCode.value,
        name: meta.name.trim(),
        description: meta.description,
        visibility: meta.visibility,
        spaceCode: meta.spaceCode.trim(),
      },
      version: forcedVersion ?? meta.version,
    });
    conflict.value = null;
    snackbar.value = 'Настройки мира сохранены.';
    await load();
  } catch (exception) {
    if (exception instanceof LoreApiError && exception.code === 'LORE_VERSION_CONFLICT') {
      conflict.value = exception.details as LoreConflict;
    } else {
      error.value = exception instanceof LoreApiError ? exception.message : 'Не удалось сохранить мир';
    }
  } finally {
    saving.value = false;
  }
}

async function saveSections(): Promise<void> {
  saving.value = true;
  error.value = null;
  try {
    const detail = await getLoreApi().saveTree(worldCode.value, sections.value, meta.version);
    world.value = detail;
    meta.version = detail.version;
    snackbar.value = 'Дерево разделов сохранено.';
  } catch (exception) {
    if (exception instanceof LoreApiError && exception.code === 'LORE_VERSION_CONFLICT') {
      conflict.value = exception.details as LoreConflict;
    } else {
      error.value = exception instanceof LoreApiError ? exception.message : 'Не удалось сохранить дерево';
    }
  } finally {
    saving.value = false;
  }
}

async function toggleArchive(): Promise<void> {
  if (!world.value) return;
  saving.value = true;
  error.value = null;
  try {
    await getLoreApi().archiveWorld(worldCode.value, !world.value.isArchived, meta.version);
    await load();
    snackbar.value = world.value?.isArchived ? 'Мир в архиве.' : 'Мир восстановлен.';
  } catch (exception) {
    if (exception instanceof LoreApiError && exception.code === 'LORE_VERSION_CONFLICT') {
      conflict.value = exception.details as LoreConflict;
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
  void saveMeta(currentVersion);
}

function reload(): void {
  conflict.value = null;
  void load();
}

function imported(worldCodeValue: string): void {
  importOpen.value = false;
  void router.push({ name: 'LoreWorld', params: { worldCode: worldCodeValue } });
}
</script>

<template>
  <v-container fluid class="pa-6">
    <div class="d-flex align-center mb-4 ga-3 flex-wrap">
      <div>
        <h1 class="text-h5">Настройки мира</h1>
        <div class="text-body-2 text-medium-emphasis">{{ worldCode }}</div>
      </div>
      <v-spacer />
      <v-btn
        variant="text"
        prepend-icon="mdi-arrow-left"
        @click="router.push({ name: 'LoreWorld', params: { worldCode } })"
      >
        К миру
      </v-btn>
      <v-btn v-if="world" variant="tonal" prepend-icon="mdi-upload" @click="exportOpen = true">Экспорт</v-btn>
      <v-btn v-if="world" variant="tonal" prepend-icon="mdi-download" @click="importOpen = true">Импорт</v-btn>
    </div>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
    <v-progress-linear v-if="loading" indeterminate class="mb-4" />

    <v-row v-if="world">
      <v-col cols="12" md="7">
        <v-card variant="outlined">
          <v-card-title>Мир</v-card-title>
          <v-card-text>
            <v-text-field v-model="meta.name" label="Название" />
            <v-text-field
              v-model="meta.spaceCode"
              label="Код пространства правил"
              hint="Необязательно: для ссылок на правила"
              persistent-hint
            />
            <v-textarea v-model="meta.description" label="Описание" rows="4" auto-grow class="mt-4" />
            <v-select
              v-model="meta.visibility"
              :items="LORE_WORLD_VISIBILITY_OPTIONS"
              item-title="title"
              item-value="value"
              label="Видимость"
              class="mt-2"
            />
            <div class="text-caption text-medium-emphasis">Версия: {{ meta.version }}</div>
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn v-if="world.isArchived" variant="tonal" @click="toggleArchive">Восстановить мир</v-btn>
            <v-btn v-else variant="tonal" color="warning" @click="toggleArchive">В архив</v-btn>
            <v-btn color="primary" :loading="saving" @click="saveMeta()">Сохранить</v-btn>
          </v-card-actions>
        </v-card>
      </v-col>

      <v-col cols="12" md="5">
        <v-card variant="outlined">
          <v-card-title>Дерево разделов</v-card-title>
          <v-card-text>
            <LoreSectionEditor v-model="sections" />
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn color="primary" :loading="saving" @click="saveSections">Сохранить дерево</v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>

    <LoreExportDialog v-if="world" v-model="exportOpen" :world="world" />
    <LoreImportDialog
      v-model="importOpen"
      :worlds="world ? [{ code: world.code, name: world.name }] : []"
      @imported="imported"
    />

    <LoreConflictDialog :conflict="conflict" :current-version="meta.version" @reload="reload" @overwrite="overwrite" />

    <v-snackbar :model-value="snackbar !== null" :timeout="4000" @update:model-value="snackbar = null">
      {{ snackbar }}
    </v-snackbar>
  </v-container>
</template>
