<script setup lang="ts">
import { ref, watch } from 'vue';
import type { StateDecay } from '@/modules/Roleplay/Rule/Dto/State/StateDecay';

const props = defineProps<{
  modelValue: StateDecay | undefined;
  /** Характеристики (для режимов «значение характеристики» и «проверка»). */
  characteristics: { title: string; value: string }[];
}>();

const emit = defineEmits<{
  'update:modelValue': [value: StateDecay];
}>();

const modeItems = [
  { title: 'Число', value: 'fixed' },
  { title: 'Размерное число', value: 'dimensional' },
  { title: 'Значение характеристики', value: 'characteristic' },
  { title: 'Результат проверки', value: 'check' },
];

const inner = ref<StateDecay>({ ...(props.modelValue ?? { kind: 'fixed', value: 0 }) });

watch(inner, (value) => emit('update:modelValue', { ...value }), { deep: true });
watch(
  () => props.modelValue,
  (value) => {
    if (value) inner.value = { ...value };
  },
);

function setKind(kind: StateDecay['kind']): void {
  if (kind === inner.value.kind) return;

  switch (kind) {
    case 'fixed':
      inner.value = { kind, value: 0 };
      break;
    case 'dimensional':
      inner.value = { kind, base: 1, size: 0 };
      break;
    case 'characteristic':
      inner.value = { kind, characteristic_code: props.characteristics[0]?.value ?? '' };
      break;
    case 'check':
      inner.value = { kind, characteristic_code: props.characteristics[0]?.value ?? '' };
      break;
  }
}
</script>

<template>
  <div>
    <v-select
      :model-value="inner.kind"
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

    <template v-else-if="inner.kind === 'characteristic'">
      <v-select
        v-model="inner.characteristic_code"
        :items="characteristics"
        label="Характеристика"
        density="compact"
        hide-details
        class="mt-2"
      />
      <v-text-field
        v-model.number="inner.modifier"
        label="Модификатор"
        type="number"
        density="compact"
        hide-details
        class="mt-2"
      />
    </template>

    <template v-else-if="inner.kind === 'check'">
      <v-select
        v-model="inner.characteristic_code"
        :items="characteristics"
        label="Проверка по характеристике"
        density="compact"
        hide-details
        class="mt-2"
      />
    </template>
  </div>
</template>
