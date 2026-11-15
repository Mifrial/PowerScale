<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import type { LoreWorldDetail } from '@/modules/Roleplay/Lore/Dto/LoreWorldDetail';
import { LORE_VISIBILITY_LABELS } from '@/modules/Roleplay/Lore/Constant/LORE_STATUS_LABELS';
import { getLoreApi } from '@/modules/Roleplay/Lore/init';
import { LoreApiError } from '@/modules/Roleplay/Lore/Service/LoreApiError';
import { buildLoreTree } from '@/modules/Roleplay/Lore/Utils/loreTree';
import { flattenLoreNav } from '@/modules/Roleplay/Lore/Utils/loreNav';
import LoreExportDialog from '@/modules/Roleplay/Lore/Component/LoreExportDialog.vue';
import LoreImportDialog from '@/modules/Roleplay/Lore/Component/LoreImportDialog.vue';
import { useLoreCrumbStore } from '@/modules/Roleplay/Lore/Store/crumbs';

const route = useRoute();
const router = useRouter();
const crumbStore = useLoreCrumbStore();
const world = ref<LoreWorldDetail | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
const exportOpen = ref(false);
const importOpen = ref(false);
let requestId = 0;

const worldCode = computed(() => String(route.params.worldCode ?? ''));
const rows = computed(() =>
  world.value ? flattenLoreNav(buildLoreTree(world.value.sections, world.value.articles)) : [],
);
const recentArticles = computed(() =>
  world.value ? [...world.value.articles].sort((left, right) => right.updatedAt - left.updatedAt).slice(0, 8) : [],
);

watch(worldCode, () => void load(), { immediate: true });

async function load(): Promise<void> {
  if (worldCode.value === '') return;
  const current = ++requestId;
  loading.value = true;
  error.value = null;
  try {
    const detail = await getLoreApi().getWorld(worldCode.value);
    if (current !== requestId) return;
    world.value = detail;
    crumbStore.setWorld(worldCode.value, detail.name);
  } catch (exception) {
    if (current !== requestId) return;
    error.value = exception instanceof LoreApiError ? exception.message : 'Не удалось загрузить мир';
  } finally {
    if (current === requestId) loading.value = false;
  }
}

function openArticle(code: string): void {
  void router.push({ name: 'LoreArticle', params: { worldCode: worldCode.value, articleCode: code } });
}

function openSettings(): void {
  void router.push({ name: 'LoreWorldSettings', params: { worldCode: worldCode.value } });
}

function createArticle(): void {
  void router.push({ name: 'LoreArticleNew', params: { worldCode: worldCode.value } });
}

function imported(worldCodeValue: string): void {
  importOpen.value = false;
  if (worldCodeValue === worldCode.value) {
    void load();
  } else {
    void router.push({ name: 'LoreWorld', params: { worldCode: worldCodeValue } });
  }
}

function formatDate(unix: number): string {
  return new Date(unix * 1000).toLocaleDateString();
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
      <v-btn v-if="world?.canEdit" variant="tonal" size="small" prepend-icon="mdi-cog" @click="openSettings">
        Настройки
      </v-btn>
      <v-btn v-if="world?.canEdit" color="primary" size="small" prepend-icon="mdi-plus" @click="createArticle">
        Новая статья
      </v-btn>
    </Teleport>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">
      {{ error }}
      <template #append>
        <v-btn variant="text" size="small" @click="load">Повторить</v-btn>
      </template>
    </v-alert>

    <div v-if="loading && !world" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate width="2" size="28" color="primary" />
    </div>

    <template v-if="world">
      <v-row>
        <v-col cols="12" md="8">
          <v-card class="mb-4">
            <v-card-title class="d-flex align-center ga-2 flex-wrap">
              <span>{{ world.name }}</span>
              <v-chip v-if="world.isArchived" size="x-small" variant="tonal">архив</v-chip>
            </v-card-title>
            <v-card-text class="lore-world-description">
              <p v-if="world.description.trim() === ''" class="text-medium-emphasis">
                Описание мира пока не заполнено.
              </p>
              <p v-else>{{ world.description }}</p>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" md="4">
          <v-card class="mb-4">
            <v-card-text>
              <div class="lore-summary-row">
                <span class="text-medium-emphasis">Видимость</span>
                <span>{{ LORE_VISIBILITY_LABELS[world.visibility] ?? world.visibility }}</span>
              </div>
              <div class="lore-summary-row">
                <span class="text-medium-emphasis">Разделов</span>
                <span>{{ world.sectionCount }}</span>
              </div>
              <div class="lore-summary-row">
                <span class="text-medium-emphasis">Статей</span>
                <span>{{ world.articleCount }}</span>
              </div>
              <div class="lore-summary-row">
                <span class="text-medium-emphasis">Пространство правил</span>
                <span>{{ world.spaceCode || '—' }}</span>
              </div>
              <div class="lore-summary-row">
                <span class="text-medium-emphasis">Обновлён</span>
                <span>{{ formatDate(world.updatedAt) }}</span>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <v-card class="mb-4">
        <v-card-title>Разделы</v-card-title>
        <v-card-text>
          <div v-if="rows.length === 0" class="text-medium-emphasis">Разделов и статей пока нет.</div>
          <template v-for="row in rows" :key="row.kind === 'section' ? `s:${row.code}` : `a:${row.article.code}`">
            <div
              v-if="row.kind === 'section'"
              class="lore-section-row text-subtitle-2"
              :style="{ marginInlineStart: `${row.depth * 16}px` }"
            >
              <v-icon size="small" class="mr-1">{{ row.icon || 'mdi-folder-outline' }}</v-icon>
              {{ row.name }}
            </div>
            <div v-else class="d-flex flex-wrap ga-2 mb-2" :style="{ marginInlineStart: `${row.depth * 16}px` }">
              <v-chip size="small" variant="tonal" @click="openArticle(row.article.code)">
                {{ row.article.title }}
              </v-chip>
            </div>
          </template>
        </v-card-text>
      </v-card>

      <v-card>
        <v-card-title>Недавние статьи</v-card-title>
        <v-card-text>
          <div v-if="recentArticles.length === 0" class="text-medium-emphasis">Статей пока нет.</div>
          <v-list v-else density="compact">
            <v-list-item
              v-for="article in recentArticles"
              :key="article.code"
              :title="article.title"
              :subtitle="article.summary"
              @click="openArticle(article.code)"
            >
              <template #append>
                <span class="text-caption text-medium-emphasis">{{ formatDate(article.updatedAt) }}</span>
              </template>
            </v-list-item>
          </v-list>
        </v-card-text>
      </v-card>
    </template>

    <LoreExportDialog v-if="world" v-model="exportOpen" :world="world" />
    <LoreImportDialog
      v-model="importOpen"
      :worlds="world ? [{ code: world.code, name: world.name }] : []"
      @imported="imported"
    />
  </v-container>
</template>

<style scoped>
.lore-world-description {
  white-space: pre-wrap;
}

.lore-summary-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 4px 0;
}

.lore-section-row {
  margin-top: 12px;
  margin-bottom: 4px;
}

.lore-section-row:first-child {
  margin-top: 0;
}
</style>
