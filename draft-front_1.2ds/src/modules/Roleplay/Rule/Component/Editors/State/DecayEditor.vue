<script setup lang="ts">
import { ref, watch } from 'vue';
import type { StateDecay } from '@/modules/Roleplay/Rule/Dto/State/StateDecay';

const props = defineProps<{
  modelValue: StateDecay | undefined;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: StateDecay];
}>();

const modeItems = [
  { title: 'Число', value: 'fixed' },
  { title: 'Размерное число', value: 'dimensional' },
] as const;

const inner = ref<StateDecay>({ ...(props.modelValue ?? { kind: 'fixed', value: 0 }) });

watch(inner, (value) => emit('update:modelValue', { ...value }), { deep: true });
watch(
  () => props.modelValue,
  (value) => {
    if (value) inner.value = { ...value };
  },
);

function setKind(kind: 'fixed' | 'dimensional'): void {
  if (kind === inner.value.kind) return;
  if (kind === 'fixed') inner.value = { kind, value: 0 };
  else inner.value = { kind, base: 1, size: 0 };
}
</script>

<template>
  <div>
    <v-select
      :model-value="inner.kind === 'fixed' || inner.kind === 'dimensional' ? inner.kind : null"
      :items="modeItems"
      label="Затухание"
      density="compact"
      hide-details
      @update:model-value="setKind"
    />

    <template v-if="inner.kind === 'fixed'">
      <v-text-field
        v-model.number="inner.value"
        label="Значение"
        type="number"
        density="compact"
        hide-details
        class="mt-2"
      />
    </template>

    <template v-else-if="inner.kind === 'dimensional'">
      <v-row dense class="mt-2">
        <v-col cols="6">
          <v-text-field v-model.number="inner.base" label="База" type="number" density="compact" hide-details />
        </v-col>
        <v-col cols="6">
          <v-text-field v-model.number="inner.size" label="Размер" type="number" density="compact" hide-details />
        </v-col>
      </v-row>
    </template>
  </div>
</template>
