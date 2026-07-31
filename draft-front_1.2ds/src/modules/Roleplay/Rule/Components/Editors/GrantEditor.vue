<template>
  <v-card variant="outlined" class="pa-2">
    <div class="d-flex gap-2 align-center mb-1">
      <v-select
        :model-value="inner.type"
        @update:model-value="updateType"
        :items="grantTypes"
        item-title="label"
        item-value="value"
        label="Дар"
        density="compact"
        hide-details
        style="flex: 1 1 auto;"
      />
      <v-btn
        icon
        size="small"
        color="error"
        variant="text"
        @click="emit('remove')"
      >
        <v-icon>mdi-delete</v-icon>
      </v-btn>
    </div>

    <div class="d-flex align-center mb-1">
      <v-checkbox
        :model-value="inner.permanent !== false"
        @update:model-value="(v) => patch('permanent', v ? true : false)"
        label="Постоянный"
        density="compact"
        hide-details
      />
      <div class="text-body-2 text-medium-emphasis">
        {{ inner.permanent !== false ? 'Действует на всех уровнях ≥ этого' : 'Только на этом уровне' }}
      </div>
    </div>

    <div class="grant-fields">
      <template v-if="inner.type === 'characteristic'">
        <v-autocomplete
          :model-value="inner.characteristic_code"
          @update:model-value="patch('characteristic_code', $event)"
          :items="characteristics"
          item-title="name"
          item-value="code"
          label="Характеристика"
          density="compact"
          hide-details
          clearable
        />
        <DimensionalNumberInput
          :model-value="inner.value"
          @update:model-value="(v) => patch('value', v)"
          label="Значение"
          mode="characteristic"
        />
      </template>

      <template v-else-if="inner.type === 'characteristic_modify'">
        <v-autocomplete
          :model-value="inner.characteristic_code"
          @update:model-value="patch('characteristic_code', $event)"
          :items="characteristics"
          item-title="name"
          item-value="code"
          label="Характеристика"
          density="compact"
          hide-details
          clearable
        />
        <FormulaInput
          :model-value="inner.amount"
          @update:model-value="patch('amount', $event)"
          :characteristics="characteristics"
          :abilities="abilities"
          :modes="['fixed', 'ability_level']"
        />
        <v-select
          :model-value="inner.source_id || null"
          @update:model-value="patch('source_id', Number($event) || 0)"
          :items="sources"
          item-title="name"
          item-value="id"
          label="Источник модификатора"
          density="compact"
          hide-details
          clearable
        />
      </template>

      <template v-else-if="inner.type === 'resource'">
        <v-autocomplete
          :model-value="inner.resource_code"
          @update:model-value="patch('resource_code', $event)"
          :items="resources"
          item-title="name"
          item-value="code"
          label="Ресурс"
          density="compact"
          hide-details
          clearable
        />
        <DimensionalNumberInput
          v-if="selectedResourceIsDimensional"
          :model-value="(inner.limit as any) ?? { base: 0, size: 0 }"
          @update:model-value="(v) => patch('limit', v)"
          label="Лимит"
        />
        <ClampedNumberField
          v-else
          :model-value="typeof inner.limit === 'number' ? inner.limit : (inner.limit?.base ?? 0)"
          @update:model-value="patch('limit', $event)"
          label="Лимит"
          :min="0"
          density="compact"
          hide-details
        />
      </template>

      <template v-else-if="inner.type === 'resource_limit_change'">
        <v-autocomplete
          :model-value="inner.resource_code"
          @update:model-value="patch('resource_code', $event)"
          :items="resources"
          item-title="name"
          item-value="code"
          label="Ресурс"
          density="compact"
          hide-details
          clearable
        />
        <FormulaInput
          :model-value="inner.amount"
          @update:model-value="patch('amount', $event)"
          :characteristics="characteristics"
          :abilities="abilities"
        />
        <v-select
          :model-value="inner.source_id || null"
          @update:model-value="patch('source_id', Number($event) || 0)"
          :items="sources"
          item-title="name"
          item-value="id"
          label="Источник модификатора"
          density="compact"
          hide-details
          clearable
        />
      </template>

      <template v-else-if="inner.type === 'ability'">
        <v-autocomplete
          :model-value="inner.ability_code"
          @update:model-value="patch('ability_code', $event)"
          :items="abilities"
          item-title="name"
          item-value="code"
          label="Способность"
          density="compact"
          hide-details
          clearable
        />
      </template>

      <template v-else-if="inner.type === 'tag'">
        <v-autocomplete
          :model-value="inner.tag_code"
          @update:model-value="patch('tag_code', $event)"
          :items="tags"
          item-title="name"
          item-value="code"
          label="Признак"
          density="compact"
          hide-details
          clearable
        />
        <v-switch
          :model-value="inner.remove"
          @update:model-value="patch('remove', $event)"
          label="Убрать"
          hide-details
          density="compact"
        />
      </template>

      <template v-else-if="inner.type === 'item'">
        <v-autocomplete
          :model-value="inner.item_code"
          @update:model-value="patch('item_code', $event)"
          :items="items"
          item-title="name"
          item-value="code"
          label="Предмет"
          density="compact"
          hide-details
          clearable
        />
      </template>
    </div>
  </v-card>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import FormulaInput from '../FormulaInput.vue'
import DimensionalNumberInput from '@/modules/Core/Engine/Components/DimensionalNumberInput.vue'
import ClampedNumberField from '@/modules/Core/UI/Components/Input/ClampedNumberField.vue'
import type { Grant, CharacteristicRef, ResourceRef, AbilityRef, TagRef, SourceRef } from '@/modules/Roleplay/Rule/Interface/abilityTypes'

const props = defineProps<{
  modelValue: Grant
  characteristics: CharacteristicRef[]
  resources: ResourceRef[]
  abilities: AbilityRef[]
  tags: TagRef[]
  items: { code: string; name: string }[]
  sources: SourceRef[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Grant]
  'remove': []
}>()

const inner = ref<Grant>(JSON.parse(JSON.stringify(props.modelValue)))

const grantTypes = [
  { label: 'Даёт характеристику', value: 'characteristic' },
  { label: 'Модификатор характеристики', value: 'characteristic_modify' },
  { label: 'Даёт ресурс', value: 'resource' },
  { label: 'Меняет лимит ресурса', value: 'resource_limit_change' },
  { label: 'Даёт способность', value: 'ability' },
  { label: 'Признак', value: 'tag' },
  { label: 'Даёт предмет', value: 'item' },
]

const selectedResourceIsDimensional = computed(() => {
  const v = inner.value
  if (v.type !== 'resource') return false
  return props.resources.find(r => r.code === v.resource_code)?.isDimensional ?? false
})

watch(() => props.resources, (resources) => {
  const v = inner.value
  if (v.type !== 'resource' || !v.resource_code) return
  const res = resources.find(r => r.code === v.resource_code)
  const limit = v.limit
  if (res?.isDimensional && typeof limit === 'number') {
    inner.value = { ...v, limit: { base: limit, size: 0 } } as Grant
  } else if (!res?.isDimensional && limit && typeof limit === 'object' && !Array.isArray(limit)) {
    inner.value = { ...v, limit: limit.base } as Grant
  }
}, { deep: true })

function updateType(type: string) {
  if (type === 'characteristic') {
    inner.value = { type: 'characteristic', characteristic_code: '', value: { base: 3, size: 0 } }
  } else if (type === 'characteristic_modify') {
    inner.value = { type: 'characteristic_modify', characteristic_code: '', amount: { type: 'fixed', value: 1 }, source_id: props.sources[0]?.id ?? 0 }
  } else if (type === 'resource') {
    inner.value = { type: 'resource', resource_code: '', limit: 0 }
  } else if (type === 'resource_limit_change') {
    inner.value = { type: 'resource_limit_change', resource_code: '', amount: { type: 'fixed', value: 1 }, source_id: props.sources[0]?.id ?? 0 }
  } else if (type === 'ability') {
    inner.value = { type: 'ability', ability_code: '' }
  } else if (type === 'tag') {
    inner.value = { type: 'tag', tag_code: '', remove: false }
  } else {
    inner.value = { type: 'item', item_code: '' }
  }
}

function patch(key: string, value: any) {
  inner.value = { ...inner.value, [key]: value } as Grant
}

watch(inner, (value) => {
  emit('update:modelValue', JSON.parse(JSON.stringify(value)))
}, { deep: true })

watch(() => props.modelValue, (value) => {
  if (JSON.stringify(value) !== JSON.stringify(inner.value)) {
    inner.value = JSON.parse(JSON.stringify(value))
  }
}, { deep: true })
</script>

<style scoped>
.gap-2 {
  gap: 8px;
}
.grant-fields {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
</style>
