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

function selectedOf(group: string): string {
  const option = props.options.find(
    (entry) => entry.exclusiveGroup === group && props.modelValue.includes(entry.optionId),
  );

  return option?.optionId ?? '';
}

function setGroup(group: string, value: unknown): void {
  const optionId = typeof value === 'string' ? value : '';
  const next = props.modelValue.filter((code) => {
    const option = props.options.find((entry) => entry.optionId === code);

    return option?.exclusiveGroup !== group;
  });
  if (optionId !== '') next.push(optionId);
  emit('update:modelValue', next);
}

function descriptionOf(option: StrikeUpgradeOption): string {
  if (option.description) return option.description;
  const delta = option.mode.injury_check_advantage;
  if (delta === 0) return '';

  return `${delta > 0 ? '+' : ''}${delta} к проверке увечья`;
}
</script>

<template>
  <div v-if="options.length" class="d-flex flex-column ga-3">
    <div v-for="entry in groups" :key="entry.group">
      <div class="text-subtitle-2 mb-1">{{ entry.title }}</div>
      <v-radio-group
        :model-value="selectedOf(entry.group)"
        density="compact"
        hide-details
        @update:model-value="(value) => setGroup(entry.group, value)"
      >
        <v-radio value="" density="compact">
          <template #label>
            <span class="text-body-2">Не применять</span>
          </template>
        </v-radio>
        <v-radio v-for="option in entry.options" :key="option.optionId" :value="option.optionId" density="compact">
          <template #label>
            <div class="text-body-2">{{ option.mode.label || option.name }}</div>
            <div v-if="descriptionOf(option)" class="text-caption text-medium-emphasis">
              {{ descriptionOf(option) }}
            </div>
          </template>
        </v-radio>
      </v-radio-group>
    </div>
  </div>
</template>
