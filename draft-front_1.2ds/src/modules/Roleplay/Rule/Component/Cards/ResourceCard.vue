<script setup lang="ts">
import { computed } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ResourceSpec } from '@/modules/Roleplay/Rule/Dto/ResourceSpec';
import { ruleViewLabelService } from '@/modules/Roleplay/Rule/Service/Instance/ruleViewLabelService';

const props = defineProps<{
  rule: Rule;
  rules: Rule[];
}>();

const spec = computed<ResourceSpec | null>(() =>
  props.rule.type === 'resource' ? ((props.rule.spec as ResourceSpec | undefined) ?? null) : null,
);

const adjustments = computed(() => spec.value?.limit?.adjustments ?? []);
</script>

<template>
  <v-card v-if="spec" variant="tonal" class="mb-3">
    <v-card-text>
      <div class="text-body-2">
        Шкала: <strong>{{ spec.is_dimensional ? 'размерное число' : 'целое' }}</strong>
      </div>
      <div v-if="spec.auto_add" class="text-body-2 mt-1">Добавляется всем персонажам автоматически</div>
      <div v-if="spec.limit" class="text-body-2 mt-1">
        Базовый лимит: <strong>{{ ruleViewLabelService.amount(spec.limit.base, rules) }}</strong>
      </div>
      <div v-for="(entry, index) in adjustments" :key="index" class="text-body-2 mt-1">
        {{ ruleViewLabelService.formula(entry.value, rules) }}
        ({{ ruleViewLabelService.ruleName(rules, entry.source_code) }})
      </div>
    </v-card-text>
  </v-card>
</template>
