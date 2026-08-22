<script setup lang="ts">
import { computed } from 'vue';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import SlidePanel from '@/modules/Core/UI/Component/SlidePanel.vue';
import AbilityCard from '@/modules/Roleplay/Rule/Component/Cards/AbilityCard.vue';
import { useRuleDetailSlider } from '@/modules/Roleplay/Character/Composables/useRuleDetailSlider';

const props = defineProps<{
  /** Общий каталог правил редактора (для карточки способности). */
  rules: Rule[];
  keywords: Keyword[];
}>();

const { state, close } = useRuleDetailSlider();

// Открытие синхронизировано с модульным состоянием слайдера (кнопка в EditorAbilityRow).
const open = computed({
  get: () => state.open,
  set: (value: boolean) => {
    if (!value) close();
  },
});

const rule = computed(() => props.rules.find((entry) => entry.id === state.ruleId) ?? null);
</script>

<template>
  <SlidePanel v-model="open" width="640px">
    <template #header>
      <div class="d-flex align-center ga-2 w-100">
        <v-btn icon variant="text" size="small" @click="close()">
          <v-icon>mdi-close</v-icon>
        </v-btn>
        <span class="font-weight-bold text-body-1">Правило</span>
      </div>
    </template>

    <template v-if="rule">
      <div class="pa-4">
        <div class="d-flex align-center mb-3 ga-2">
          <h2 class="text-h6 font-weight-medium">{{ rule.name }}</h2>
          <code class="text-caption text-medium-emphasis">{{ rule.code }}</code>
        </div>
        <AbilityCard :rule="rule" :rules="props.rules" :keywords="keywords" />
      </div>
    </template>
    <div v-else class="pa-4 muted-text">Правило не найдено в каталоге.</div>
  </SlidePanel>
</template>

<style scoped>
.muted-text {
  color: rgba(var(--v-theme-on-surface), 0.72);
}
</style>
