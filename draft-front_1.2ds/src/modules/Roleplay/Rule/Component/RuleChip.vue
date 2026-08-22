<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRuleCatalogStore } from '@/modules/Roleplay/Rule/Store/ruleCatalog';
import type { InlineSegment } from '@/modules/Messages/Chat/Dto/InlineSegment';
import RuleSlider from '@/modules/Roleplay/Rule/Component/RuleSlider.vue';

const props = defineProps<{
  segment: Extract<InlineSegment, { kind: 'token' }>;
  /** Непрозрачный контекст хоста чата: имена правил ревизии игры (D72) — приоритетнее каталога. */
  context?: {
    ruleNames?: Record<string, string>;
    /** Ревизия контекста — слайдер резолвит правило из её среза (Слой 1, §7.20). */
    spaceId?: number | null;
    rulesRevision?: number | null;
  };
}>();

const sliderOpen = ref(false);
const loaded = ref(false);
const ruleCatalogStore = useRuleCatalogStore();

const code = computed(() => props.segment.params[0] ?? '');

// Имя из ревизии игры (переданной контекстом), иначе — из глобального каталога.
const ruleName = computed(() => props.context?.ruleNames?.[code.value] ?? null);
const rule = computed(() => {
  if (props.context?.ruleNames) return ruleName.value ? { id: code.value, name: ruleName.value } : null;

  return ruleCatalogStore.findRule(code.value) ?? null;
});

watch(
  code,
  async (value) => {
    // С контекстом ревизии имя известно сразу — каталог не грузим.
    if (props.context?.ruleNames) {
      loaded.value = true;

      return;
    }
    loaded.value = false;
    if (!value) return;
    try {
      await ruleCatalogStore.ensureLoaded();
    } catch {
      // каталог останется пустым — чип покажет «Объект скрыт»
    } finally {
      loaded.value = true;
    }
  },
  { immediate: true },
);
</script>

<template>
  <span v-if="rule" class="chat-rule-chip" @click="sliderOpen = true">
    <v-icon size="x-small" class="mr-1">mdi-book-open-variant</v-icon>
    <span class="chat-rule-chip__name">{{ rule.name }}</span>
  </span>
  <span v-else-if="loaded" class="chat-rule-chip chat-rule-chip--hidden">
    <v-icon size="x-small" class="mr-1">mdi-book-lock</v-icon>
    <span>Объект скрыт</span>
  </span>
  <span v-else class="chat-rule-chip chat-rule-chip--loading">
    <v-progress-circular indeterminate size="10" width="2" />
  </span>

  <RuleSlider
    v-model:open="sliderOpen"
    :rule-id="rule?.id ?? null"
    :space-id="props.context?.spaceId ?? null"
    :rules-revision="props.context?.rulesRevision ?? null"
  />
</template>

<style scoped>
.chat-rule-chip {
  display: inline-flex;
  align-items: center;
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
  cursor: pointer;
  text-decoration: underline dotted;
  text-underline-offset: 3px;
}

.chat-rule-chip__name {
  font-weight: 600;
}

.chat-rule-chip--hidden {
  color: rgba(var(--v-theme-on-surface), var(--v-text-disabled-opacity));
  cursor: default;
  font-style: italic;
  text-decoration: none;
}

.chat-rule-chip--loading {
  color: rgba(var(--v-theme-on-surface), var(--v-text-disabled-opacity));
  cursor: default;
  text-decoration: none;
}
</style>
