<template>
  <div>
    <v-expansion-panels v-model="expandedPanels" multiple>
      <v-expansion-panel value="general">
        <v-expansion-panel-title>Общее</v-expansion-panel-title>
        <v-expansion-panel-text>
          <RuleEditorBase
            :name="name"
            @update:name="(v) => emit('update:name', v)"
            :code="code"
            @update:code="(v) => emit('update:code', v)"
            :code-disabled="codeDisabled"
            :description="description"
            @update:description="(v) => emit('update:description', v)"
            :mechanic-id="mechanicId"
            @update:mechanic-id="(v) => emit('update:mechanicId', v)"
            :keyword-ids="keywordIds"
            @update:keyword-ids="(v) => emit('update:keywordIds', v)"
            :mechanic-options="mechanicOptions"
            :keyword-options="keywordOptions"
          >
            <template #spec></template>
          </RuleEditorBase>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel value="resource">
        <v-expansion-panel-title>Ресурс</v-expansion-panel-title>
        <v-expansion-panel-text>
          <v-switch
            v-model="innerSpec.is_dimensional"
            label="Размерный ресурс"
            color="primary"
            hide-details
          />
          <div v-if="innerSpec.is_dimensional" class="mt-2">
            <DimensionalNumberInput
              v-model="dimensionalInitialValue"
              label="Начальное значение"
            />
          </div>
          <v-text-field
            v-else
            v-model.number="innerSpec.initial_value"
            label="Начальное значение"
            type="number"
            density="compact"
            class="mt-2"
          />
          <div class="text-body-2 text-medium-emphasis mt-2">
            Правило описывает определение ресурса. Текущее и максимальное значение хранятся на персонаже.
          </div>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import RuleEditorBase from './RuleEditorBase.vue'
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue'
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Value/DimensionalNumber'
import { resourceSpecService } from '@/modules/Roleplay/Rule/Service/Spec/ResourceSpecService'
import type { ResourceSpec } from '@/modules/Roleplay/Rule/Dto/ResourceSpec'
import type { RuleSpec } from '@/modules/Roleplay/Rule/Enum/RuleSpec'

const props = defineProps<{
  name: string
  code: string
  codeDisabled?: boolean
  description: string
  mechanicId: number | null
  keywordIds: number[]
  spec: RuleSpec | null
  mechanicOptions: { title: string; value: number }[]
  keywordOptions: { title: string; value: number }[]
}>()

const emit = defineEmits<{
  'update:name': [value: string]
  'update:code': [value: string]
  'update:description': [value: string]
  'update:mechanicId': [value: number | null]
  'update:keywordIds': [value: number[]]
  'update:spec': [value: ResourceSpec]
}>()

const expandedPanels = ref<string[]>(['general', 'resource'])

const innerSpec = ref<ResourceSpec>(resourceSpecService.createEmpty())

const dimensionalInitialValue = computed<DimensionalNumberValue | null>({
  get: () => {
    const v = innerSpec.value.initial_value
    return v && typeof v === 'object' ? v : null
  },
  set: (val) => { innerSpec.value.initial_value = val },
})

const specToEmit = computed<ResourceSpec>(() => {
  const result: ResourceSpec = {
    is_dimensional: innerSpec.value.is_dimensional,
    initial_value: innerSpec.value.initial_value,
  }
  return result
})

watch(specToEmit, (value) => {
  emit('update:spec', value)
}, { deep: true })

onMounted(() => {
  if (props.spec) {
    innerSpec.value = {
      is_dimensional: (props.spec as ResourceSpec).is_dimensional ?? true,
      initial_value: (props.spec as ResourceSpec).initial_value ?? null,
    }
  }
})
</script>
