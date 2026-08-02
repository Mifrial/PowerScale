<template>
  <div>
    <div class="text-body-2 text-medium-emphasis mb-2">
      Заклинание — волшебное действие. Сложность сотворения, длительность и компоненты.
    </div>

    <DimensionalNumberInput
      :model-value="inner.difficulty ?? null"
      @update:model-value="patch('difficulty', $event)"
      label="Сложность сотворения"
    />

    <div class="mt-3">
      <div class="text-subtitle-2 mb-1">Продолжительность</div>
      <v-radio-group
        :model-value="inner.duration?.type ?? 'instant'"
        @update:model-value="(v) => updateDurationType(v ?? 'instant')"
        density="compact"
        hide-details
      >
        <v-radio label="Мгновенное" value="instant" />
        <v-radio label="Обновляемое" value="refreshable" />
        <v-radio label="Поддерживаемое" value="sustained" />
      </v-radio-group>

      <template v-if="inner.duration && inner.duration.type !== 'instant'">
        <div class="d-flex gap-2 mt-2 flex-wrap">
          <DimensionalNumberInput
            :model-value="inner.duration.difficulty ?? null"
            @update:model-value="patchDuration('difficulty', $event)"
            label="Сложность обновления/поддержания"
            style="min-width: 220px;"
          />
          <ClampedNumberField
            :model-value="typeof inner.duration.action_cost === 'number' ? inner.duration.action_cost : (inner.duration.action_cost?.base ?? 0)"
            @update:model-value="patchDuration('action_cost', $event)"
            label="ОД на обновление/поддержание"
            :min="0"
            density="compact"
            hide-details
            style="min-width: 160px;"
          />
        </div>
        <div class="mt-2">
          <v-checkbox
            :model-value="!!inner.duration.limit"
            @update:model-value="(v) => toggleDurationLimit(!!v)"
            label="Предел длительности"
            density="compact"
            hide-details
          />
          <div v-if="inner.duration.limit" class="d-flex gap-2 flex-wrap mt-1">
            <ClampedNumberField
              :model-value="typeof inner.duration.limit.value === 'number' ? inner.duration.limit.value : (inner.duration.limit.value?.base ?? 0)"
              @update:model-value="patchDurationLimit('value', $event)"
              label="Значение"
              :min="1"
              density="compact"
              hide-details
              style="min-width: 120px;"
            />
            <v-select
              :model-value="inner.duration.limit.unit"
              @update:model-value="patchDurationLimit('unit', $event)"
              :items="[
                { label: 'Ход', value: 'turn' },
                { label: 'Минута', value: 'minute' },
                { label: 'Час', value: 'hour' },
              ]"
              item-title="label"
              item-value="value"
              label="Единица"
              density="compact"
              hide-details
              style="min-width: 160px;"
            />
          </div>
        </div>
      </template>
    </div>

    <div class="mt-3">
      <div class="text-subtitle-2 mb-1">Компоненты</div>
      <div
        v-for="(component, index) in inner.components"
        :key="`comp-${index}`"
        class="pa-1 mb-2 rounded bg-accent"
      >
        <div class="bg-surface rounded pa-2">
          <div class="d-flex align-center mb-1">
            <v-select
              :model-value="component.type"
              @update:model-value="updateComponentType(index, $event)"
              :items="[
                { label: 'Вербальный', value: 'verbal' },
                { label: 'Соматический', value: 'somatic' },
                { label: 'Материальный', value: 'material' },
              ]"
              item-title="label"
              item-value="value"
              label="Тип компонента"
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
              @click="removeComponent(index)"
            >
              <v-icon>mdi-delete</v-icon>
            </v-btn>
          </div>
          <template v-if="component.type === 'verbal' || component.type === 'somatic'">
            <v-text-field
              :model-value="component.note ?? ''"
              @update:model-value="patchComponent(index, 'note', $event || undefined)"
              label="Приписка (например «крик» → «Вербальный (крик)»)"
              density="compact"
              hide-details
              class="mt-1"
            />
          </template>
          <template v-else>
            <v-autocomplete
              :model-value="materialItemCode(component)"
              @update:model-value="patchComponent(index, 'item_code', $event ?? undefined)"
              :items="items"
              item-title="name"
              item-value="code"
              label="Предмет (Item)"
              density="compact"
              hide-details
              clearable
              class="mt-1"
            />
            <v-text-field
              :model-value="materialDescription(component)"
              @update:model-value="patchComponent(index, 'description', $event || undefined)"
              label="Описание материала"
              density="compact"
              hide-details
              class="mt-1"
            />
          </template>
        </div>
      </div>
      <v-btn
        variant="text"
        color="primary"
        size="small"
        @click="addComponent"
      >
        <v-icon start>mdi-plus</v-icon>
        Добавить компонент
      </v-btn>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import type { SpellSpec } from '@/modules/Roleplay/Rule/Dto/Ability/SpellSpec'
import type { SpellDuration } from '@/modules/Roleplay/Rule/Dto/Ability/SpellDuration'
import type { SpellComponent } from '@/modules/Roleplay/Rule/Dto/Ability/SpellComponent'
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue'
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue'

const props = defineProps<{
  modelValue: SpellSpec | null
  items: { code: string; name: string }[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: SpellSpec]
}>()

const inner = ref<SpellSpec>(defaultSpec())

function defaultSpec(): SpellSpec {
  return {
    difficulty: { base: 3, size: 0 },
    duration: { type: 'instant' },
    components: [],
  }
}

function patch(key: string, value: unknown) {
  inner.value = { ...inner.value, [key]: value }
}

function updateDurationType(type: string) {
  let duration: SpellDuration
  if (type === 'instant') {
    duration = { type: 'instant' }
  } else {
    duration = { type: type as 'refreshable' | 'sustained', difficulty: { base: 3, size: 0 }, action_cost: 0 }
  }
  inner.value = { ...inner.value, duration }
}

function patchDuration(key: string, value: unknown) {
  const d = inner.value.duration
  if (d.type === 'instant') return
  inner.value = { ...inner.value, duration: { ...d, [key]: value } }
}

function toggleDurationLimit(checked: boolean) {
  const d = inner.value.duration
  if (d.type === 'instant') return
  const limit = checked
    ? { value: 1, unit: 'turn' as const }
    : undefined
  inner.value = { ...inner.value, duration: { ...d, limit } }
}

function patchDurationLimit(key: string, value: unknown) {
  const d = inner.value.duration
  if (d.type === 'instant' || !d.limit) return
  inner.value = { ...inner.value, duration: { ...d, limit: { ...d.limit, [key]: value } } }
}

function updateComponentType(index: number, type: string) {
  const components = inner.value.components.map((c, i) => {
    if (i !== index) return c
    if (type === 'material') return { type: 'material' as const, item_code: undefined, description: undefined }
    return { type: type as 'verbal' | 'somatic', note: undefined }
  })
  inner.value = { ...inner.value, components }
}

function patchComponent(index: number, key: string, value: unknown) {
  const components = inner.value.components.map((c, i) => (i === index ? { ...c, [key]: value } : c))
  inner.value = { ...inner.value, components }
}

function materialItemCode(component: SpellComponent): string | null {
  return component.type === 'material' ? component.item_code ?? null : null
}

function materialDescription(component: SpellComponent): string {
  return component.type === 'material' ? component.description ?? '' : ''
}

function addComponent() {
  inner.value = {
    ...inner.value,
    components: [...inner.value.components, { type: 'verbal', note: undefined }],
  }
}

function removeComponent(index: number) {
  inner.value = { ...inner.value, components: inner.value.components.filter((_, i) => i !== index) }
}

watch(inner, (value) => {
  emit('update:modelValue', structuredClone(value))
}, { deep: true })

onMounted(() => {
  if (props.modelValue) {
    inner.value = normalize(structuredClone(props.modelValue))
  }
})

function normalize(raw: SpellSpec): SpellSpec {
  return {
    difficulty: raw.difficulty ?? { base: 3, size: 0 },
    duration: raw.duration ?? { type: 'instant' },
    components: raw.components ?? [],
  }
}
</script>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
