<script setup lang="ts">
import { computed } from 'vue';
import type { StrikeUpgradeOption } from '@/modules/Roleplay/Game/Dto/Strike/StrikeUpgradeOption';

const props = defineProps<{
  options: StrikeUpgradeOption[];
  modelValue: string[];
}>();

const emit = defineEmits<{
  'update:modelValue': [value: string[]];
}>();

const groups = computed(() => {
  const order: string[] = [];
  const byGroup = new Map<string, StrikeUpgradeOption[]>();
  for (const option of props.options) {
    const current = byGroup.get(option.exclusiveGroup);
    if (!current) {
      order.push(option.exclusiveGroup);
      byGroup.set(option.exclusiveGroup, [option]);
      continue;
    }
    current.push(option);
  }

  return order.map((group) => ({
    group,
    title: byGroup.get(group)?.[0]?.name ?? group,
    options: byGroup.get(group) ?? [],
  }));
});

function selectedOf(group: string): string | null {
  const option = props.options.find(
    (entry) => entry.exclusiveGroup === group && props.modelValue.includes(entry.optionId),
  );

  return option?.optionId ?? null;
}

function setGroup(group: string, optionId: string | null): void {
  const next = props.modelValue.filter((code) => {
    const option = props.options.find((entry) => entry.optionId === code);

    return option?.exclusiveGroup !== group;
  });
  if (optionId) next.push(optionId);
  emit('update:modelValue', next);
}

function chipLabel(option: StrikeUpgradeOption): string {
  const delta = option.mode.injury_check_advantage;
  if (delta > 0) return `${option.mode.label} · +${delta}`;
  if (delta < 0) return `${option.mode.label} · ${delta}`;

  return option.mode.label;
}
</script>

<template>
  <div v-if="options.length" class="d-flex flex-column ga-2">
    <div v-for="entry in groups" :key="entry.group">
      <div class="text-caption text-medium-emphasis">{{ entry.title }}</div>
      <v-btn-toggle
        :model-value="selectedOf(entry.group)"
        density="compact"
        variant="outlined"
        divided
        class="mt-1"
        @update:model-value="(value) => setGroup(entry.group, typeof value === 'string' ? value : null)"
      >
        <v-btn v-for="option in entry.options" :key="option.optionId" :value="option.optionId" size="small">
          {{ chipLabel(option) }}
        </v-btn>
      </v-btn-toggle>
    </div>
  </div>
</template>
