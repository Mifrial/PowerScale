<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { InlineSegment } from '@/modules/Messages/Inline/Dto/InlineSegment';
import LoreArticleLinkSlider from '@/modules/Roleplay/Lore/Component/Inline/LoreArticleLinkSlider.vue';
import { loreInlineArticleService, loreInlineValueService } from '@/modules/Roleplay/Lore/init';

/** Чип статьи кодекса в заметке/чате: название по доступу и слайдер по клику. */
const props = defineProps<{
  segment: Extract<InlineSegment, { kind: 'token' }>;
}>();

type ChipState = 'loading' | 'ready' | 'missing' | 'error';

const state = ref<ChipState>('loading');
const title = ref('');
const sliderOpen = ref(false);

const target = computed(() => loreInlineValueService.parse(String(props.segment.params[0] ?? '')));
const storedLabel = computed(() => String(props.segment.params[1] ?? '').trim());
const worldCode = computed(() => target.value?.worldCode ?? '');
const articleCode = computed(() => target.value?.articleCode ?? '');
const label = computed(() => title.value || 'Статья');

watch(
  () => props.segment.params[0],
  () => void load(),
  { immediate: true },
);

async function load(): Promise<void> {
  const link = target.value;
  title.value = storedLabel.value;
  if (!link) {
    state.value = 'missing';

    return;
  }
  state.value = 'loading';
  try {
    const article = await loreInlineArticleService.resolve(link.worldCode, link.articleCode);
    if (article === null) {
      state.value = 'missing';

      return;
    }
    title.value = article.title;
    state.value = 'ready';
  } catch {
    state.value = 'error';
  }
}

function open(): void {
  if (state.value === 'missing' || target.value === null) return;
  sliderOpen.value = true;
}
</script>

<template>
  <span v-if="state === 'missing'" class="lore-inline-chip lore-inline-chip--hidden">
    <v-icon size="x-small" class="mr-1">mdi-book-lock</v-icon>
    <span>Объект скрыт</span>
  </span>
  <span
    v-else
    class="lore-inline-chip"
    :class="`lore-inline-chip--${state}`"
    role="button"
    tabindex="0"
    @click="open"
    @keydown.enter.prevent="open"
    @keydown.space.prevent="open"
  >
    <v-icon size="x-small" class="mr-1">mdi-book-open-page-variant</v-icon>
    <span class="lore-inline-chip__name">{{ label }}</span>
  </span>

  <LoreArticleLinkSlider
    v-if="sliderOpen"
    v-model:open="sliderOpen"
    :world-code="worldCode"
    :article-code="articleCode"
  />
</template>

<style scoped>
.lore-inline-chip {
  display: inline-flex;
  align-items: center;
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
  cursor: pointer;
  text-decoration: underline dotted;
  text-underline-offset: 3px;
}

.lore-inline-chip__name {
  font-weight: 600;
}

.lore-inline-chip--loading,
.lore-inline-chip--error {
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.lore-inline-chip--hidden {
  color: rgba(var(--v-theme-on-surface), var(--v-text-disabled-opacity));
  cursor: default;
  font-style: italic;
  text-decoration: none;
}
</style>
