<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import SlidePanel from '@/modules/Core/UI/Component/SlidePanel.vue';
import { RULE_TYPE_LABELS } from '@/modules/Roleplay/Rule/Constant/RULE_TYPE_LABELS';
import { ruleRevisionResolverService } from '@/modules/Roleplay/Rule/Service/Instance/ruleRevisionResolverService';

const props = defineProps<{
  ruleId: string | null;
  /** Контекст ревизии (пространство + номер ревизии): резолвим правило из её среза, а не каталога. */
  spaceId?: number | null;
  rulesRevision?: number | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const ruleData = ref<Rule | null>(null);
const error = ref<string | null>(null);

const typeLabel = computed(() =>
  ruleData.value ? (RULE_TYPE_LABELS[ruleData.value.type] ?? ruleData.value.type) : '',
);

async function loadRule(id: string | null) {
  if (id == null) {
    ruleData.value = null;
    error.value = null;

    return;
  }
  error.value = null;
  ruleData.value = null;
  try {
    ruleData.value = await ruleRevisionResolverService.resolveRuleFromRevision({
      spaceId: props.spaceId ?? null,
      rulesRevision: props.rulesRevision ?? null,
      ruleId: id,
    });
    if (ruleData.value == null) error.value = 'Правило не найдено в ревизии';
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return;
    error.value = 'Не удалось загрузить правило';
  }
}

watch(() => props.ruleId, loadRule, { immediate: true });
</script>

<template>
  <SlidePanel v-model="open" width="520px">
    <template #header>
      <div class="d-flex align-center ga-2 w-100">
        <v-btn icon variant="text" size="small" @click="open = false">
          <v-icon>mdi-close</v-icon>
        </v-btn>
        <span class="font-weight-bold text-body-1">Правило</span>
      </div>
    </template>

    <div v-if="ruleData" class="pa-6">
      <div class="d-flex align-center mb-2 ga-2">
        <h2 class="text-h6 font-weight-medium">{{ ruleData.name }}</h2>
        <v-chip size="x-small" variant="tonal">{{ typeLabel }}</v-chip>
      </div>
      <div class="text-caption text-medium-emphasis mb-4">
        <code>{{ ruleData.code }}</code>
      </div>
      <v-card variant="outlined">
        <v-card-title class="text-body-2 font-weight-bold">Описание</v-card-title>
        <v-card-text class="rule-slider__description">{{ ruleData.description }}</v-card-text>
      </v-card>
    </div>
    <v-alert v-else-if="error" type="error" class="ma-6">
      {{ error }}
      <template #append>
        <v-btn size="small" variant="tonal" @click="loadRule(props.ruleId)">Попробовать снова</v-btn>
      </template>
    </v-alert>
    <div v-else class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate width="2" size="28" color="primary" />
    </div>
  </SlidePanel>
</template>

<style scoped>
/* Переносы строк внутри описания правила (текст моков содержит \n). */
.rule-slider__description {
  white-space: pre-line;
}
</style>
