<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { LoreArticleDetail } from '@/modules/Roleplay/Lore/Dto/LoreArticleDetail';
import { LORE_STATUS_LABELS, LORE_VISIBILITY_LABELS } from '@/modules/Roleplay/Lore/Constant/LORE_STATUS_LABELS';
import { getLoreApi } from '@/modules/Roleplay/Lore/init';
import { LoreApiError } from '@/modules/Roleplay/Lore/Service/LoreApiError';
import SlidePanel from '@/modules/Core/UI/Component/SlidePanel.vue';
import LoreArticleBody from '@/modules/Roleplay/Lore/Component/LoreArticleBody.vue';

const props = defineProps<{
  worldCode: string;
  articleCode: string | null;
  knownCodes: string[];
}>();

const open = defineModel<boolean>('open', { default: false });

const emit = defineEmits<{
  'open-lore': [code: string];
  'open-rule': [code: string];
}>();

const article = ref<LoreArticleDetail | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
let requestId = 0;

const pageTo = computed(() => {
  const code = props.articleCode;
  if (code === null) return null;

  return `/lore/${props.worldCode}/article/${code}`;
});

watch(
  () => [props.articleCode, open.value] as const,
  ([code, isOpen]) => {
    if (!isOpen || code === null) return;
    void load(code);
  },
  { immediate: true },
);

async function load(code: string): Promise<void> {
  const current = ++requestId;
  loading.value = true;
  error.value = null;
  try {
    const detail = await getLoreApi().getArticle(props.worldCode, code);
    if (current !== requestId) return;
    article.value = detail;
  } catch (exception) {
    if (current !== requestId) return;
    error.value = exception instanceof LoreApiError ? exception.message : 'Не удалось загрузить статью';
  } finally {
    if (current === requestId) loading.value = false;
  }
}

function formatDate(unix: number): string {
  return new Date(unix * 1000).toLocaleDateString();
}
</script>

<template>
  <SlidePanel v-model="open" :page-to="pageTo" page-label="Открыть страницу статьи" width="560px">
    <template #header>
      <div class="lore-slider-header">
        <div class="text-subtitle-1 font-weight-medium">{{ article?.title ?? props.articleCode ?? 'Статья' }}</div>
        <div class="d-flex align-center flex-wrap ga-2">
          <v-chip v-if="article?.status === 'draft'" size="x-small" color="warning" variant="tonal">
            {{ LORE_STATUS_LABELS.draft }}
          </v-chip>
          <v-chip v-if="article?.visibility === 'secret'" size="x-small" color="warning" variant="tonal">
            {{ LORE_VISIBILITY_LABELS.secret }}
          </v-chip>
          <span v-if="article" class="text-caption text-medium-emphasis">
            обновлено {{ formatDate(article.updatedAt) }}
          </span>
        </div>
      </div>
    </template>

    <v-progress-linear v-if="loading" indeterminate />

    <v-alert v-if="error" type="error" variant="tonal" density="compact" class="ma-4">{{ error }}</v-alert>

    <div v-if="article" class="pa-4">
      <p v-if="article.summary.trim() !== ''" class="text-body-2 font-italic text-medium-emphasis mb-3">
        {{ article.summary }}
      </p>
      <LoreArticleBody
        :html="article.bodyHtml"
        :known-codes="props.knownCodes"
        @open-lore="emit('open-lore', $event)"
        @open-rule="emit('open-rule', $event)"
      />
    </div>
  </SlidePanel>
</template>

<style scoped>
.lore-slider-header {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px 16px;
}
</style>
