<script setup lang="ts">
import { computed, defineAsyncComponent, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import type { LoreArticleDetail } from '@/modules/Roleplay/Lore/Dto/LoreArticleDetail';
import type { LoreTocItem } from '@/modules/Roleplay/Lore/Dto/LoreTocItem';
import type { LoreWorldDetail } from '@/modules/Roleplay/Lore/Dto/LoreWorldDetail';
import { LORE_STATUS_LABELS, LORE_VISIBILITY_LABELS } from '@/modules/Roleplay/Lore/Constant/LORE_STATUS_LABELS';
import { getLoreApi } from '@/modules/Roleplay/Lore/init';
import { LoreApiError } from '@/modules/Roleplay/Lore/Service/LoreApiError';
import { buildLoreTree } from '@/modules/Roleplay/Lore/Utils/loreTree';
import { flattenLoreNav } from '@/modules/Roleplay/Lore/Utils/loreNav';
import LoreArticleBody from '@/modules/Roleplay/Lore/Component/LoreArticleBody.vue';
import LoreWorldNav from '@/modules/Roleplay/Lore/Component/LoreWorldNav.vue';
import { getRuleApi } from '@/modules/Roleplay/Rule/init';
import { getRuleSpaceApi } from '@/modules/Roleplay/RuleSpace/init';

const RuleSlider = defineAsyncComponent(() => import('@/modules/Roleplay/Rule/Component/RuleSlider.vue'));

const route = useRoute();
const router = useRouter();
const world = ref<LoreWorldDetail | null>(null);
const article = ref<LoreArticleDetail | null>(null);
const toc = ref<LoreTocItem[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const ruleCode = ref<string | null>(null);
const ruleSpaceId = ref<number | null>(null);
const ruleOpen = ref(false);
const snackbar = ref<string | null>(null);
let requestId = 0;

const worldCode = computed(() => String(route.params.worldCode ?? ''));
const articleCode = computed(() => String(route.params.articleCode ?? ''));

const knownCodes = computed(() => (world.value ? world.value.articles.map((item) => item.code) : []));
const navArticles = computed(() =>
  world.value
    ? flattenLoreNav(buildLoreTree(world.value.sections, world.value.articles)).flatMap((row) =>
        row.kind === 'article' ? [row.article] : [],
      )
    : [],
);
const currentIndex = computed(() => navArticles.value.findIndex((item) => item.code === articleCode.value));
const previousArticle = computed(() =>
  currentIndex.value > 0 ? (navArticles.value[currentIndex.value - 1] ?? null) : null,
);
const nextArticle = computed(() =>
  currentIndex.value >= 0 ? (navArticles.value[currentIndex.value + 1] ?? null) : null,
);
const sectionName = computed(() => {
  const code = article.value?.sectionCode;
  if (!code || !world.value) return 'Без раздела';

  return world.value.sections.find((section) => section.code === code)?.name ?? code;
});
const missingLinks = computed(() =>
  article.value
    ? article.value.links.filter((link) => link.type === 'lore' && !knownCodes.value.includes(link.code))
    : [],
);

watch(
  () => [worldCode.value, articleCode.value] as const,
  () => void load(),
  { immediate: true },
);

async function load(): Promise<void> {
  if (worldCode.value === '' || articleCode.value === '') return;
  const current = ++requestId;
  loading.value = true;
  error.value = null;
  try {
    const [worldDetail, articleDetail] = await Promise.all([
      getLoreApi().getWorld(worldCode.value),
      getLoreApi().getArticle(worldCode.value, articleCode.value),
    ]);
    if (current !== requestId) return;
    world.value = worldDetail;
    article.value = articleDetail;
  } catch (exception) {
    if (current !== requestId) return;
    error.value = exception instanceof LoreApiError ? exception.message : 'Не удалось загрузить статью';
  } finally {
    if (current === requestId) loading.value = false;
  }
}

function openArticle(code: string): void {
  void router.push({ name: 'LoreArticle', params: { worldCode: worldCode.value, articleCode: code } });
}

function openLoreLink(code: string): void {
  if (knownCodes.value.includes(code)) {
    openArticle(code);

    return;
  }
  if (article.value?.canEdit) {
    void router.push({
      name: 'LoreArticleNew',
      params: { worldCode: worldCode.value },
      query: { code, title: code },
    });

    return;
  }
  snackbar.value = `Статья «${code}» ещё не написана`;
}

async function openRuleLink(code: string): Promise<void> {
  ruleCode.value = code;
  if (ruleSpaceId.value === null) {
    const spaceCode = world.value?.spaceCode ?? '';
    if (spaceCode === '') {
      snackbar.value = 'У мира не указано пространство правил';

      return;
    }
    try {
      const space = await getRuleSpaceApi().getSpaceByCode(spaceCode);
      ruleSpaceId.value = space.id;
      await getRuleApi().getRules(space.id);
    } catch {
      snackbar.value = `Пространство правил «${spaceCode}» не найдено`;

      return;
    }
  }
  ruleOpen.value = true;
}

function edit(): void {
  if (!article.value) return;
  void router.push({
    name: 'LoreArticleEdit',
    params: { worldCode: worldCode.value, articleCode: article.value.code },
  });
}

function formatDate(unix: number): string {
  return new Date(unix * 1000).toLocaleString();
}
</script>

<template>
  <v-container fluid class="pa-6">
    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">
      {{ error }}
      <v-btn variant="text" size="small" @click="load">Повторить</v-btn>
    </v-alert>

    <v-progress-linear v-if="loading && !article" indeterminate class="mb-4" />

    <v-row v-if="world && article">
      <v-col cols="12" md="3">
        <v-btn
          variant="text"
          prepend-icon="mdi-arrow-left"
          class="mb-2"
          @click="router.push({ name: 'LoreWorld', params: { worldCode } })"
        >
          {{ world.name }}
        </v-btn>
        <LoreWorldNav :world="world" :current-article-code="article.code" @open="openArticle" />
        <v-card v-if="missingLinks.length > 0" variant="outlined" class="mt-3">
          <v-card-title class="text-subtitle-2">Ненаписанные статьи</v-card-title>
          <v-card-text class="text-body-2">
            <v-chip
              v-for="link in missingLinks"
              :key="link.code"
              size="small"
              color="error"
              variant="tonal"
              class="mr-1 mb-1"
              @click="openLoreLink(link.code)"
            >
              {{ link.code }}
            </v-chip>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="9">
        <v-card variant="outlined">
          <v-card-title class="d-flex align-center flex-wrap ga-2">
            <span>{{ article.title }}</span>
            <v-chip v-if="article.status === 'draft'" size="small" color="warning" variant="tonal">
              {{ LORE_STATUS_LABELS.draft }}
            </v-chip>
            <v-chip v-if="article.visibility === 'secret'" size="small" color="warning" variant="tonal">
              {{ LORE_VISIBILITY_LABELS.secret }}
            </v-chip>
            <v-chip v-if="article.isArchived" size="small" variant="tonal">архив</v-chip>
            <v-spacer />
            <v-btn v-if="article.canEdit" size="small" variant="tonal" prepend-icon="mdi-pencil" @click="edit">
              Редактировать
            </v-btn>
          </v-card-title>
          <v-card-subtitle> {{ sectionName }} · обновлено {{ formatDate(article.updatedAt) }} </v-card-subtitle>
          <v-card-text v-if="article.summary.trim() !== ''" class="text-body-1 font-italic text-medium-emphasis">
            {{ article.summary }}
          </v-card-text>
          <v-card-text>
            <LoreArticleBody
              :html="article.bodyHtml"
              :known-codes="knownCodes"
              @open-lore="openLoreLink"
              @open-rule="openRuleLink"
              @toc="toc = $event"
            />
          </v-card-text>
          <v-card-actions>
            <v-btn
              v-if="previousArticle"
              variant="text"
              prepend-icon="mdi-arrow-left"
              @click="openArticle(previousArticle.code)"
            >
              {{ previousArticle.title }}
            </v-btn>
            <v-spacer />
            <v-btn
              v-if="nextArticle"
              variant="text"
              append-icon="mdi-arrow-right"
              @click="openArticle(nextArticle.code)"
            >
              {{ nextArticle.title }}
            </v-btn>
          </v-card-actions>
        </v-card>

        <v-row class="mt-2">
          <v-col cols="12" md="6">
            <v-card variant="outlined">
              <v-card-title class="text-subtitle-2">Оглавление</v-card-title>
              <v-card-text>
                <div v-if="toc.length === 0" class="text-caption text-medium-emphasis">В статье нет заголовков.</div>
                <div v-for="item in toc" :key="item.id" :style="{ paddingInlineStart: `${(item.level - 2) * 16}px` }">
                  <a :href="`#${item.id}`" class="text-body-2">{{ item.title }}</a>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="12" md="6">
            <v-card variant="outlined">
              <v-card-title class="text-subtitle-2">Ссылки сюда</v-card-title>
              <v-card-text>
                <div v-if="article.backlinks.length === 0" class="text-caption text-medium-emphasis">
                  На эту статью пока никто не ссылается.
                </div>
                <v-list v-else density="compact">
                  <v-list-item
                    v-for="backlink in article.backlinks"
                    :key="backlink.articleId"
                    :title="backlink.title"
                    @click="openArticle(backlink.code)"
                  />
                </v-list>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-col>
    </v-row>

    <RuleSlider v-model:open="ruleOpen" :rule-code="ruleCode" :space-id="ruleSpaceId" />

    <v-snackbar :model-value="snackbar !== null" :timeout="4000" @update:model-value="snackbar = null">
      {{ snackbar }}
    </v-snackbar>
  </v-container>
</template>
