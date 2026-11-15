<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import DescriptionHtml from '@/modules/Core/UI/Component/DescriptionHtml.vue';
import type { LoreTocItem } from '@/modules/Roleplay/Lore/Dto/LoreTocItem';
import { loreTocService } from '@/modules/Roleplay/Lore/Service/Instance/loreTocService';

const props = defineProps<{
  html: string;
  knownCodes?: string[];
}>();

const emit = defineEmits<{
  'open-lore': [code: string];
  'open-rule': [code: string];
  toc: [items: LoreTocItem[]];
}>();

const root = ref<HTMLElement | null>(null);
let observer: MutationObserver | null = null;

function openLore(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) return;
  const link = target.closest<HTMLElement>('[data-lore-code]');
  const code = link?.dataset.loreCode;
  if (!code) return;
  event.preventDefault();
  event.stopPropagation();
  emit('open-lore', code);
}

function markMissingLinks(): void {
  if (!root.value || props.knownCodes === undefined) return;
  const known = new Set(props.knownCodes);
  root.value.querySelectorAll<HTMLElement>('[data-lore-code]').forEach((link) => {
    link.classList.toggle('lore-link--missing', !known.has(link.dataset.loreCode ?? ''));
  });
}

async function refreshToc(): Promise<void> {
  await nextTick();
  markMissingLinks();
  emit('toc', loreTocService.build(root.value));
}

function observe(): void {
  observer?.disconnect();
  if (!root.value) return;
  observer = new MutationObserver(() => void refreshToc());
  observer.observe(root.value, { childList: true, subtree: true, characterData: true });
}

onMounted(() => {
  void refreshToc();
  observe();
});

onBeforeUnmount(() => observer?.disconnect());

watch(
  () => [props.html, props.knownCodes] as const,
  () => {
    void refreshToc();
    observe();
  },
);
</script>

<template>
  <div ref="root" class="lore-article-body" @click.capture="openLore">
    <DescriptionHtml :html="props.html" @open-rule="emit('open-rule', $event)" />
  </div>
</template>

<style scoped>
.lore-article-body :deep(.description-html) {
  line-height: 1.6;
}

.lore-article-body :deep([data-lore-code]) {
  color: rgb(var(--v-theme-primary));
  cursor: pointer;
  text-decoration: underline;
  text-decoration-style: dotted;
}

.lore-article-body :deep([data-lore-code].lore-link--missing) {
  color: rgb(var(--v-theme-error));
  text-decoration-style: dashed;
}
</style>
