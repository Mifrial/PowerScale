<script setup lang="ts">
import { computed, defineAsyncComponent, ref, watch } from 'vue';
import type { LoreWorldDetail } from '@/modules/Roleplay/Lore/Dto/LoreWorldDetail';
import LoreArticleSlider from '@/modules/Roleplay/Lore/Component/LoreArticleSlider.vue';
import LoreNotice from '@/modules/Roleplay/Lore/Component/LoreNotice.vue';
import { getLoreApi } from '@/modules/Roleplay/Lore/init';
import { getRuleSpaceApi } from '@/modules/Roleplay/RuleSpace/init';

const RuleSlider = defineAsyncComponent(() => import('@/modules/Roleplay/Rule/Component/RuleSlider.vue'));

/** Слайдер статьи из inline-ссылки: вложенные статьи и правила внутри кодекса. */
const props = defineProps<{
  worldCode: string;
  articleCode: string;
}>();

const open = defineModel<boolean>('open', { default: false });

const world = ref<LoreWorldDetail | null>(null);
const currentCode = ref(props.articleCode);
const notice = ref<string | null>(null);
const ruleOpen = ref(false);
const ruleCode = ref<string | null>(null);
const ruleSpaceId = ref<number | null>(null);
const ruleSpaceCode = ref<string | null>(null);
const ruleRevision = ref<number | null>(null);
let worldRequest = 0;

const knownCodes = computed(() => (world.value ? world.value.articles.map((article) => article.code) : undefined));
const spaceCode = computed(() => world.value?.spaceCode ?? '');

watch(
  () => [props.worldCode, props.articleCode, open.value] as const,
  ([worldCode, articleCode, isOpen]) => {
    if (!isOpen) return;
    currentCode.value = articleCode;
    void loadWorld(worldCode);
  },
  { immediate: true },
);

async function loadWorld(worldCode: string): Promise<void> {
  const request = ++worldRequest;
  world.value = null;
  try {
    const detail = await getLoreApi().getWorld(worldCode);
    if (request !== worldRequest) return;
    world.value = detail;
  } catch {
    if (request === worldRequest) world.value = null;
  }
}

function openLore(code: string): void {
  const codes = knownCodes.value;
  if (codes === undefined || codes.includes(code)) {
    currentCode.value = code;

    return;
  }
  notice.value = `Статья «${code}» ещё не написана`;
}

async function openRule(code: string): Promise<void> {
  if (spaceCode.value === '') {
    notice.value = 'У мира не указано пространство правил';

    return;
  }
  try {
    const space = await getRuleSpaceApi().getSpaceByCode(spaceCode.value);
    ruleSpaceId.value = space.id;
    ruleSpaceCode.value = space.code;
    ruleRevision.value = space.revision;
    ruleCode.value = code;
    ruleOpen.value = true;
  } catch {
    notice.value = `Пространство правил «${spaceCode.value}» не найдено`;
  }
}
</script>

<template>
  <LoreArticleSlider
    v-model:open="open"
    :world-code="worldCode"
    :article-code="currentCode"
    :known-codes="knownCodes"
    @open-lore="openLore"
    @open-rule="openRule"
  />

  <RuleSlider
    v-if="ruleOpen"
    v-model:open="ruleOpen"
    :rule-code="ruleCode"
    :space-id="ruleSpaceId"
    :space-code="ruleSpaceCode"
    :rules-revision="ruleRevision"
  />

  <LoreNotice :model-value="notice !== null" :text="notice ?? ''" color="warning" @update:model-value="notice = null" />
</template>
