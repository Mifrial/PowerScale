<script setup lang="ts">
import { computed } from 'vue';
import type { ProcessTransition } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessTransition';
import type { ProcessSpec } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessSpec';
import { PROCESS_TRANSITION_MODES } from '@/modules/Roleplay/Rule/Constant/PROCESS_TRANSITION_MODES';
import { processSpecService } from '@/modules/Roleplay/Rule/Service/Spec/ProcessSpecService';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { useVModelSync } from '@/modules/Core/UI/Composables/useVModelSync';

const props = defineProps<{
  transition: ProcessTransition;
  stepRefs: { name: string; code: string }[];
}>();

const emit = defineEmits<{
  'update:transition': [value: ProcessTransition];
}>();

const directionOptions = [
  { label: 'В обе стороны', value: 'both' },
  { label: 'Только вперёд', value: 'forward' },
];

const { inner } = useVModelSync<ProcessTransition>({
  modelValue: () => props.transition,
  onCommit: (value) => emit('update:transition', value),
  clone: true,
});

function transitionSpec(transition: ProcessTransition): ProcessSpec {
  return { steps: [], start_step_code: undefined, transition, failure: null };
}

function updateMode(mode: string) {
  inner.value = processSpecService.updateTransitionMode(transitionSpec(inner.value), mode).transition;
}

function patchTransition(key: string, value: unknown) {
  inner.value = processSpecService.patchTransition(transitionSpec(inner.value), key, value).transition;
}

function patchEdge(edgeIndex: number, key: string, value: unknown) {
  inner.value = processSpecService.patchEdge(transitionSpec(inner.value), edgeIndex, key, value).transition;
}

function addEdge() {
  inner.value = processSpecService.addEdge(transitionSpec(inner.value)).transition;
}

function removeEdge(edgeIndex: number) {
  inner.value = processSpecService.removeEdge(transitionSpec(inner.value), edgeIndex).transition;
}

const maxShift = computed<number>(() => {
  const t = inner.value;

  return t.mode === 'chain' ? t.max_shift : 1;
});

const direction = computed<'forward' | 'both'>(() => {
  const t = inner.value;

  return t.mode === 'chain' ? (t.direction ?? 'both') : 'both';
});

const edges = computed(() => {
  const t = inner.value;

  return t.mode === 'custom' ? (t.edges ?? []) : [];
});
</script>

<template>
  <div>
    <div class="text-subtitle-2 mb-1">Переходы между шагами</div>
    <v-select
      :model-value="inner.mode"
      @update:model-value="updateMode"
      :items="PROCESS_TRANSITION_MODES"
      item-title="label"
      item-value="value"
      label="Режим переходов"
      density="compact"
      hide-details
    />

    <template v-if="inner.mode === 'chain'">
      <div class="d-flex gap-2 mt-2">
        <ClampedNumberField
          :model-value="maxShift"
          @update:model-value="(v) => patchTransition('max_shift', v)"
          label="Радиус (±шагов)"
          :min="1"
          density="compact"
          hide-details
          style="min-width: 120px"
        />
        <v-select
          :model-value="direction"
          @update:model-value="(v) => patchTransition('direction', v)"
          :items="directionOptions"
          item-title="label"
          item-value="value"
          label="Направление"
          density="compact"
          hide-details
          class="flex-grow-1"
        />
      </div>
      <div class="text-body-2 text-medium-emphasis mt-1">
        Шаги линейны по порядку; с шага можно перейти на ±радиус (shift 0 = повторить шаг).
      </div>
    </template>

    <div v-else-if="inner.mode === 'free'" class="text-body-2 text-medium-emphasis mt-2">
      С любого шага можно перейти на любой другой (включая повтор текущего).
    </div>

    <template v-else>
      <div class="text-body-2 text-medium-emphasis mt-2 mb-1">
        Явные переходы графа. Повтор шага = ребро «из шага в себя».
      </div>
      <div v-for="(edge, edgeIndex) in edges" :key="`edge-${edgeIndex}`" class="d-flex gap-2 mb-1">
        <v-autocomplete
          :model-value="edge.from"
          @update:model-value="(v) => patchEdge(edgeIndex, 'from', v)"
          :items="stepRefs"
          item-title="name"
          item-value="code"
          label="Из"
          density="compact"
          hide-details
          clearable
          class="flex-grow-1"
        />
        <v-icon class="align-self-center">mdi-arrow-right</v-icon>
        <v-autocomplete
          :model-value="edge.to"
          @update:model-value="(v) => patchEdge(edgeIndex, 'to', v)"
          :items="stepRefs"
          item-title="name"
          item-value="code"
          label="В"
          density="compact"
          hide-details
          clearable
          class="flex-grow-1"
        />
        <v-btn icon size="small" color="error" variant="text" @click="removeEdge(edgeIndex)">
          <v-icon>mdi-delete</v-icon>
        </v-btn>
      </div>
      <v-btn variant="text" color="primary" size="small" @click="addEdge">
        <v-icon start>mdi-plus</v-icon>
        Добавить переход
      </v-btn>
    </template>
  </div>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
