<template>
  <div>
    <div class="text-body-2 text-medium-emphasis mb-2">
      Процесс — действие из связных шагов. Каждый шаг: название, описание и растрачиваемые
      ресурсы (обычно 1 ОД). Повтор шага — само-переход.
    </div>

    <div
      v-for="(step, index) in inner.steps"
      :key="`step-${index}`"
      class="pa-1 mb-2 rounded bg-accent"
    >
      <div class="bg-surface rounded pa-2">
        <div class="d-flex align-center mb-1">
          <v-text-field
            :model-value="step.name"
            @update:model-value="patchStep(index, 'name', $event)"
            label="Название шага"
            density="compact"
            hide-details
            class="flex-grow-1"
          />
          <v-btn
            icon
            size="x-small"
            color="error"
            variant="text"
            class="ml-2"
            @click="removeStep(index)"
          >
            <v-icon>mdi-delete</v-icon>
          </v-btn>
        </div>
        <v-text-field
          :model-value="step.code"
          @update:model-value="patchStep(index, 'code', $event)"
          label="Код шага"
          density="compact"
          hide-details
          class="mb-2"
        />
        <v-textarea
          :model-value="step.description"
          @update:model-value="patchStep(index, 'description', $event)"
          label="Описание"
          density="compact"
          hide-details
          auto-grow
          rows="1"
          class="mb-2"
        />
        <div class="text-subtitle-2 mb-1">Ресурсы (стоимость шага)</div>
        <div
          v-for="(cost, costIndex) in step.costs"
          :key="`cost-${index}-${costIndex}`"
          class="d-flex gap-2 mb-1"
        >
          <v-autocomplete
            :model-value="cost.resource_code"
            @update:model-value="patchStepCost(index, costIndex, 'resource_code', $event)"
            :items="resources"
            item-title="name"
            item-value="code"
            label="Ресурс"
            density="compact"
            hide-details
            :clearable="!isMandatoryStepCost(index, costIndex)"
            :disabled="isMandatoryStepCost(index, costIndex)"
            class="flex-grow-1"
          />
          <DimensionalNumberInput
            v-if="stepCostIsDimensional(index, costIndex)"
            :model-value="(cost.amount as any) ?? { base: 0, size: 0 }"
            @update:model-value="patchStepCost(index, costIndex, 'amount', $event)"
            label="Стоимость"
            style="flex: 1 1 auto;"
          />
          <ClampedNumberField
            v-else
            :model-value="typeof cost.amount === 'number' ? cost.amount : 0"
            @update:model-value="patchStepCost(index, costIndex, 'amount', $event)"
            label="Стоимость"
            :min="1"
            density="compact"
            hide-details
            style="min-width: 110px;"
          />
          <v-btn
            icon
            size="small"
            color="error"
            variant="text"
            :disabled="isMandatoryStepCost(index, costIndex)"
            @click="removeStepCost(index, costIndex)"
          >
            <v-icon>mdi-delete</v-icon>
          </v-btn>
        </div>
        <v-btn
          variant="text"
          color="primary"
          size="small"
          @click="addStepCost(index)"
        >
          <v-icon start>mdi-plus</v-icon>
          Добавить ресурс
        </v-btn>
      </div>
    </div>
    <v-btn
      variant="text"
      color="primary"
      size="small"
      @click="addStep"
    >
      <v-icon start>mdi-plus</v-icon>
      Добавить шаг
    </v-btn>

    <div class="mt-4">
      <div class="text-subtitle-2 mb-1">Переходы между шагами</div>
      <v-select
        :model-value="inner.transition.mode"
        @update:model-value="updateTransitionMode"
        :items="transitionModes"
        item-title="label"
        item-value="value"
        label="Режим переходов"
        density="compact"
        hide-details
      />

      <template v-if="inner.transition.mode === 'chain'">
        <div class="d-flex gap-2 mt-2">
          <ClampedNumberField
            :model-value="(inner.transition as any).max_shift ?? 1"
            @update:model-value="patchTransition('max_shift', $event)"
            label="Радиус (±шагов)"
            :min="1"
            density="compact"
            hide-details
            style="min-width: 120px;"
          />
          <v-select
            :model-value="(inner.transition as any).direction ?? 'both'"
            @update:model-value="patchTransition('direction', $event)"
            :items="[
              { label: 'В обе стороны', value: 'both' },
              { label: 'Только вперёд', value: 'forward' },
            ]"
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

      <div v-else-if="inner.transition.mode === 'free'" class="text-body-2 text-medium-emphasis mt-2">
        С любого шага можно перейти на любой другой (включая повтор текущего).
      </div>

      <template v-else>
        <div class="text-body-2 text-medium-emphasis mt-2 mb-1">
          Явные переходы графа. Повтор шага = ребро «из шага в себя».
        </div>
        <div
          v-for="(edge, edgeIndex) in (inner.transition as any).edges ?? []"
          :key="`edge-${edgeIndex}`"
          class="d-flex gap-2 mb-1"
        >
          <v-autocomplete
            :model-value="edge.from"
            @update:model-value="patchEdge(edgeIndex, 'from', $event)"
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
            @update:model-value="patchEdge(edgeIndex, 'to', $event)"
            :items="stepRefs"
            item-title="name"
            item-value="code"
            label="В"
            density="compact"
            hide-details
            clearable
            class="flex-grow-1"
          />
          <v-btn
            icon
            size="small"
            color="error"
            variant="text"
            @click="removeEdge(edgeIndex)"
          >
            <v-icon>mdi-delete</v-icon>
          </v-btn>
        </div>
        <v-btn
          variant="text"
          color="primary"
          size="small"
          @click="addEdge"
        >
          <v-icon start>mdi-plus</v-icon>
          Добавить переход
        </v-btn>
      </template>
    </div>

    <div class="mt-4">
      <div class="text-subtitle-2 mb-1">Старт и провал</div>
      <div class="d-flex gap-2 flex-wrap">
        <v-autocomplete
          :model-value="inner.start_step_code ?? null"
          @update:model-value="patchSpec('start_step_code', $event ?? undefined)"
          :items="stepRefs"
          item-title="name"
          item-value="code"
          label="Начальный шаг"
          density="compact"
          hide-details
          clearable
          style="min-width: 220px;"
        />
        <v-select
          :model-value="inner.failure ?? null"
          @update:model-value="patchSpec('failure', $event ?? null)"
          :items="[
            { label: 'Нет', value: null },
            { label: 'Начать заново с первого шага', value: 'restart_from_first' },
            { label: 'Завершить действие', value: 'end_action' },
          ]"
          item-title="label"
          item-value="value"
          label="Провал шага"
          density="compact"
          hide-details
          clearable
          style="min-width: 280px;"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'
import type {
  ProcessSpec,
  ProcessTransition,
  ProcessStep,
  ResourceRef,
} from '@/modules/Roleplay/Rule/Interface/abilityTypes'
import DimensionalNumberInput from '@/modules/Core/Engine/Components/DimensionalNumberInput.vue'
import ClampedNumberField from '@/modules/Core/UI/Components/Input/ClampedNumberField.vue'

const props = defineProps<{
  modelValue: ProcessSpec | null
  resources: ResourceRef[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: ProcessSpec]
}>()

const transitionModes = [
  { label: 'Цепочка (соседние шаги)', value: 'chain' },
  { label: 'Свободно (любой шаг)', value: 'free' },
  { label: 'Произвольный граф', value: 'custom' },
]

const inner = ref<ProcessSpec>(defaultSpec())

function defaultSpec(): ProcessSpec {
  return {
    steps: [],
    start_step_code: undefined,
    transition: { mode: 'chain', max_shift: 1, direction: 'both' },
    failure: null,
  }
}

const stepRefs = computed(() =>
  inner.value.steps
    .filter(s => s.code)
    .map(s => ({ name: s.name || s.code, code: s.code }))
)

function stepCostIsDimensional(stepIndex: number, costIndex: number): boolean {
  const cost = inner.value.steps[stepIndex]?.costs[costIndex]
  if (!cost?.resource_code) return false
  return props.resources.find(r => r.code === cost.resource_code)?.isDimensional ?? false
}

function isMandatoryStepCost(stepIndex: number, costIndex: number): boolean {
  const costs = inner.value.steps[stepIndex]?.costs ?? []
  const odIndex = costs.findIndex(c => c.resource_code === 'action-points')
  return odIndex === costIndex
}

function patchStep(index: number, key: string, value: any) {
  const steps = inner.value.steps.map((s, i) => (i === index ? { ...s, [key]: value } : s))
  inner.value = { ...inner.value, steps }
}

function patchStepCost(stepIndex: number, costIndex: number, key: string, value: any) {
  const steps = inner.value.steps.map((s, i) => {
    if (i !== stepIndex) return s
    const costs = s.costs.map((c, j) => (j === costIndex ? { ...c, [key]: value } : c))
    return { ...s, costs }
  })
  inner.value = { ...inner.value, steps }
}

function addStepCost(stepIndex: number) {
  const steps = inner.value.steps.map((s, i) =>
    i === stepIndex ? { ...s, costs: [...s.costs, { resource_code: 'action-points', amount: 1 }] } : s
  )
  inner.value = { ...inner.value, steps }
}

function removeStepCost(stepIndex: number, costIndex: number) {
  if (isMandatoryStepCost(stepIndex, costIndex)) return
  const steps = inner.value.steps.map((s, i) => {
    if (i !== stepIndex) return s
    const costs = s.costs.filter((_, j) => j !== costIndex)
    return { ...s, costs }
  })
  inner.value = { ...inner.value, steps }
}

function addStep() {
  inner.value = {
    ...inner.value,
    steps: [
      ...inner.value.steps,
      { code: `step-${inner.value.steps.length + 1}`, name: '', description: '', costs: [{ resource_code: 'action-points', amount: 1 }] },
    ],
  }
}

function removeStep(index: number) {
  inner.value = { ...inner.value, steps: inner.value.steps.filter((_, i) => i !== index) }
}

function updateTransitionMode(mode: string) {
  let transition: ProcessTransition
  if (mode === 'chain') {
    transition = { mode: 'chain', max_shift: 1, direction: 'both' }
  } else if (mode === 'free') {
    transition = { mode: 'free' }
  } else {
    transition = { mode: 'custom', edges: [] }
  }
  inner.value = { ...inner.value, transition }
}

function patchTransition(key: string, value: any) {
  if (inner.value.transition.mode === 'chain') {
    inner.value = { ...inner.value, transition: { ...inner.value.transition, [key]: value } }
  }
}

function patchEdge(edgeIndex: number, key: string, value: any) {
  const t = inner.value.transition
  if (t.mode !== 'custom') return
  const edges = (t.edges ?? []).map((e, i) => (i === edgeIndex ? { ...e, [key]: value } : e))
  inner.value = { ...inner.value, transition: { mode: 'custom', edges } }
}

function addEdge() {
  const t = inner.value.transition
  if (t.mode !== 'custom') return
  const edges = [...(t.edges ?? []), { from: '', to: '' }]
  inner.value = { ...inner.value, transition: { mode: 'custom', edges } }
}

function removeEdge(edgeIndex: number) {
  const t = inner.value.transition
  if (t.mode !== 'custom') return
  const edges = (t.edges ?? []).filter((_, i) => i !== edgeIndex)
  inner.value = { ...inner.value, transition: { mode: 'custom', edges } }
}

function patchSpec(key: string, value: any) {
  inner.value = { ...inner.value, [key]: value }
}

watch(inner, (value) => {
  emit('update:modelValue', JSON.parse(JSON.stringify(value)))
}, { deep: true })

onMounted(() => {
  if (props.modelValue) {
    inner.value = normalize(JSON.parse(JSON.stringify(props.modelValue)))
  }
})

function normalize(raw: ProcessSpec): ProcessSpec {
  const transition: ProcessTransition = raw.transition ?? { mode: 'chain', max_shift: 1, direction: 'both' }
  if (transition.mode === 'chain' && transition.max_shift === undefined) {
    return { ...raw, transition: { ...transition, max_shift: 1, direction: transition.direction ?? 'both' } }
  }
  return { ...raw, steps: raw.steps ?? [], failure: raw.failure ?? null }
}
</script>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
