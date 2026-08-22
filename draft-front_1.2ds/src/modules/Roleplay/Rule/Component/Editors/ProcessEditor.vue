<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue';
import type { ProcessSpec } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessSpec';
import type { ProcessStep } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessStep';
import type { ProcessTransition } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessTransition';
import type { ResourceRef } from '@/modules/Roleplay/Rule/Dto/Ability/ResourceRef';
import { processSpecService } from '@/modules/Roleplay/Rule/Service/Instance/processSpecService';
import ProcessStepEditor from '@/modules/Roleplay/Rule/Component/Editors/ProcessStepEditor.vue';
import ProcessTransitionEditor from '@/modules/Roleplay/Rule/Component/Editors/ProcessTransitionEditor.vue';
import ProcessStartFailureEditor from '@/modules/Roleplay/Rule/Component/Editors/ProcessStartFailureEditor.vue';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';

const props = defineProps<{
  modelValue: ProcessSpec | null;
  resources: ResourceRef[];
}>();

const emit = defineEmits<{
  'update:modelValue': [value: ProcessSpec];
}>();

const inner = ref<ProcessSpec>(processSpecService.createEmpty());

const stepRefs = computed(() =>
  inner.value.steps.filter((s) => s.code).map((s) => ({ name: s.name || s.code, code: s.code })),
);

function addStep() {
  inner.value = processSpecService.addStep(inner.value);
}

function removeStep(index: number) {
  inner.value = processSpecService.removeStep(inner.value, index);
}

function updateStep(index: number, step: ProcessStep) {
  inner.value = processSpecService.patchSpec(
    inner.value,
    'steps',
    inner.value.steps.map((s, i) => (i === index ? step : s)),
  );
}

function updateTransition(transition: ProcessTransition) {
  inner.value = processSpecService.patchSpec(inner.value, 'transition', transition);
}

function updateStartStepCode(value: string | undefined) {
  inner.value = processSpecService.patchSpec(inner.value, 'start_step_code', value);
}

function updateFailure(value: string | null) {
  inner.value = processSpecService.patchSpec(inner.value, 'failure', value);
}

watch(
  inner,
  (value) => {
    emit('update:modelValue', cloneData(value));
  },
  { deep: true },
);

onMounted(() => {
  if (props.modelValue) {
    inner.value = processSpecService.normalize(cloneData(props.modelValue));
  }
});
</script>

<template>
  <div>
    <div class="text-body-2 text-medium-emphasis mb-2">
      Процесс — действие из связных шагов. Каждый шаг: название, описание и растрачиваемые ресурсы (обычно 1 ОД). Повтор
      шага — само-переход.
    </div>

    <div v-for="(step, index) in inner.steps" :key="`step-${index}`" class="pa-1 mb-2 rounded bg-accent">
      <div class="bg-surface rounded pa-2">
        <div class="d-flex align-center justify-space-between mb-1">
          <div class="text-subtitle-2">Шаг {{ index + 1 }}</div>
          <v-btn icon size="x-small" color="error" variant="text" @click="removeStep(index)">
            <v-icon>mdi-delete</v-icon>
          </v-btn>
        </div>
        <ProcessStepEditor
          :step="step"
          :step-index="index"
          :resources="resources"
          @update:step="(v) => updateStep(index, v)"
        />
      </div>
    </div>
    <v-btn variant="text" color="primary" size="small" @click="addStep">
      <v-icon start>mdi-plus</v-icon>
      Добавить шаг
    </v-btn>

    <div class="mt-4">
      <ProcessTransitionEditor
        :transition="inner.transition"
        :step-refs="stepRefs"
        @update:transition="updateTransition"
      />
    </div>

    <div class="mt-4">
      <ProcessStartFailureEditor
        :start-step-code="inner.start_step_code"
        :failure="inner.failure ?? null"
        :step-refs="stepRefs"
        @update:start-step-code="updateStartStepCode"
        @update:failure="updateFailure"
      />
    </div>
  </div>
</template>
