<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { ResourceOverview } from '@/modules/Roleplay/Character/Dto/Overview/ResourceOverview';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { RuleLink } from '@/modules/Roleplay/Character/init';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';

const props = defineProps<{
  resource: ResourceOverview;
  rules: Rule[];
  canEdit?: boolean;
}>();

const emit = defineEmits<{
  apply: [current: DimensionalNumberValue];
}>();

const draft = ref(props.resource.current.base);
const fieldMax = computed(() =>
  Math.max(new DimensionalNumber(props.resource.max).toNumber(), new DimensionalNumber(props.resource.current).toNumber()),
);

watch(
  () => props.resource.current.base,
  (base) => {
    draft.value = base;
  },
);

function signed(delta: number): string {
  if (delta > 0) return `+${delta}`;

  return String(delta);
}

function submit(): void {
  emit('apply', { base: draft.value, size: props.resource.current.size });
}
</script>

<template>
  <v-card class="rounded border" elevation="3" style="width: max-content; min-width: 280px; max-width: 460px">
    <v-card-title class="text-body-1">
      <span class="text-truncate">{{ resource.name }}</span>
    </v-card-title>
    <v-card-text class="pt-0">
      <div class="d-flex align-center justify-space-between py-1 text-body-2">
        <span class="text-medium-emphasis">Текущее</span>
        <span class="font-weight-medium">{{ resource.currentLabel }} / {{ resource.maxLabel }}</span>
      </div>
      <div class="d-flex align-center justify-space-between py-1 text-body-2">
        <span class="text-medium-emphasis">Максимум</span>
        <span class="font-weight-medium">{{ resource.maxLabel }}</span>
      </div>
      <div class="d-flex align-center justify-space-between py-1 text-body-2">
        <span class="text-medium-emphasis">База</span>
        <span class="font-weight-medium">{{ resource.baseLabel }}</span>
      </div>

      <template v-if="resource.bonuses.length > 0">
        <v-divider class="my-2" />
        <div class="text-caption text-medium-emphasis mb-1">Бонусы лимита</div>
        <div
          v-for="(bonus, index) in resource.bonuses"
          :key="`${bonus.sourceRuleCode}_${bonus.delta}_${index}`"
          class="d-flex align-center flex-wrap ga-2 py-1"
        >
          <span class="font-weight-medium">{{ signed(bonus.delta) }}</span>
          <span class="text-medium-emphasis">|</span>
          <RuleLink v-if="bonus.sourceRuleCode" :rule-code="bonus.sourceRuleCode" class="text-body-2">
            {{ bonus.source }}
          </RuleLink>
          <span v-else class="text-body-2 text-medium-emphasis">{{ bonus.source }}</span>
        </div>
      </template>

      <div v-if="resource.bonuses.length === 0" class="d-flex align-center justify-space-between py-1 text-body-2">
        <span class="text-medium-emphasis">Бонусы лимита</span>
        <span class="text-medium-emphasis">нет</span>
      </div>

      <template v-if="canEdit">
        <ClampedNumberField
          v-model="draft"
          class="mt-3"
          label="Задать текущее"
          :min="0"
          :max="fieldMax"
          density="compact"
          hide-details
        />
        <v-btn class="mt-3" color="primary" variant="tonal" size="small" block type="button" @click.stop="submit">
          Применить
        </v-btn>
      </template>
    </v-card-text>
  </v-card>
</template>
