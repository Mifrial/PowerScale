<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import type { LoreWorldDetail } from '@/modules/Roleplay/Lore/Dto/LoreWorldDetail';
import { LORE_VISIBILITY_LABELS } from '@/modules/Roleplay/Lore/Constant/LORE_STATUS_LABELS';
import { getLoreApi } from '@/modules/Roleplay/Lore/init';
import { LoreApiError } from '@/modules/Roleplay/Lore/Service/LoreApiError';
import { buildLoreTree } from '@/modules/Roleplay/Lore/Utils/loreTree';
import LoreWorldNav from '@/modules/Roleplay/Lore/Component/LoreWorldNav.vue';

const route = useRoute();
const router = useRouter();
const world = ref<LoreWorldDetail | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
let requestId = 0;

const worldCode = computed(() => String(route.params.worldCode ?? ''));
const tree = computed(() => (world.value ? buildLoreTree(world.value.sections, world.value.articles) : []));
const recentArticles = computed(() =>
  world.value ? [...world.value.articles].sort((left, right) => right.updatedAt - left.updatedAt).slice(0, 8) : [],
);
const emptySections = computed(() =>
  world.value ? world.value.articles.filter((article) => article.sectionCode === null) : [],
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

function formatDate(unix: number): string {
  return new Date(unix * 1000).toLocaleDateString();
}
</script>

<template>
  <v-container fluid class="pa-6">
    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">
      {{ error }}
      <v-btn variant="text" size="small" @click="load">Повторить</v-btn>
    </v-alert>

    <v-progress-linear v-if="loading && !world" indeterminate class="mb-4" />

    <template v-if="world">
      <div class="d-flex align-center mb-4 ga-3 flex-wrap">
        <div>
          <h1 class="text-h5">
            {{ world.name }}
            <v-chip v-if="world.isArchived" size="small" class="ml-2">архив</v-chip>
          </h1>
          <div class="text-body-2 text-medium-emphasis">
            {{ LORE_VISIBILITY_LABELS[world.visibility] ?? world.visibility }} · {{ world.sectionCount }} разделов ·
            {{ world.articleCount }} статей
          </div>
        </div>
        <v-spacer />
        <v-btn v-if="world.canEdit" variant="tonal" prepend-icon="mdi-cog" @click="openSettings">Настройки</v-btn>
        <v-btn v-if="world.canEdit" color="primary" prepend-icon="mdi-plus" @click="createArticle">
          Новая статья
        </v-btn>
      </div>

      <v-row>
        <v-col cols="12" md="3">
          <LoreWorldNav :world="world" @open="openArticle" />
        </v-col>

        <v-col cols="12" md="9">
          <v-card variant="outlined" class="mb-4">
            <v-card-title>О мире</v-card-title>
            <v-card-text class="lore-world-description">
              <p v-if="world.description.trim() === ''" class="text-medium-emphasis">
                Описание мира пока не заполнено.
              </p>
              <p v-else>{{ world.description }}</p>
            </v-card-text>
          </v-card>

          <v-card variant="outlined" class="mb-4">
            <v-card-title>Разделы</v-card-title>
            <v-card-text>
              <div v-if="tree.length === 0" class="text-medium-emphasis">Разделов пока нет.</div>
              <div v-for="node in tree" :key="node.section.code" class="mb-3">
                <div class="text-subtitle-2">
                  <v-icon size="small" class="mr-1">{{ node.section.icon || 'mdi-folder' }}</v-icon>
                  {{ node.section.name }}
                  <span class="text-caption text-medium-emphasis">({{ node.articles.length }})</span>
                </div>
                <div v-if="node.section.description" class="text-caption text-medium-emphasis">
                  {{ node.section.description }}
                </div>
                <div class="d-flex flex-wrap ga-2 mt-1">
                  <v-chip
                    v-for="article in node.articles"
                    :key="article.code"
                    size="small"
                    variant="tonal"
                    @click="openArticle(article.code)"
                  >
                    {{ article.title }}
                  </v-chip>
                  <span v-if="node.articles.length === 0" class="text-caption text-medium-emphasis"> Статей нет </span>
                </div>
              </div>
              <div v-if="emptySections.length > 0" class="mt-3">
                <div class="text-subtitle-2 mb-1">Без раздела</div>
                <div class="d-flex flex-wrap ga-2">
                  <v-chip
                    v-for="article in emptySections"
                    :key="article.code"
                    size="small"
                    variant="tonal"
                    @click="openArticle(article.code)"
                  >
                    {{ article.title }}
                  </v-chip>
                </div>
              </div>
            </v-card-text>
          </v-card>

          <v-card variant="outlined">
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
        </v-col>
      </v-row>
    </template>
  </v-container>
</template>

<style scoped>
.lore-world-description {
  white-space: pre-wrap;
}
</style>
