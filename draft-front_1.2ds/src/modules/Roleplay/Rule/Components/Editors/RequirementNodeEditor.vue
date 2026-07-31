<template>
  <div class="requirement-node">
    <div class="d-flex gap-2 align-start">
      <v-select
        :model-value="inner.type"
        @update:model-value="updateType"
        :items="requirementTypes"
        item-title="label"
        item-value="value"
        label="Условие"
        density="compact"
        hide-details
        style="min-width: 200px;"
      >
        <template #item="{ props: itemProps, item }">
          <v-list-item
            v-bind="itemProps"
            :title="item.raw.label"
            :subtitle="item.raw.description"
          />
        </template>
      </v-select>

      <template v-if="inner.type === 'has_ability'">
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
          class="flex-grow-1"
        />
        <ClampedNumberField
          :model-value="inner.min_level ?? 1"
          @update:model-value="patch('min_level', $event)"
          label="Ур. (мин)"
          :min="1"
          density="compact"
          hide-details
          style="min-width: 90px;"
        />
      </template>

      <template v-else-if="inner.type === 'has_ability_tag'">
        <v-autocomplete
          :model-value="inner.tag_code"
          @update:model-value="patch('tag_code', $event)"
          :items="abilityTags"
          item-title="name"
          item-value="code"
          label="Тег способностей"
          density="compact"
          hide-details
          clearable
          class="flex-grow-1"
        />
        <ClampedNumberField
          :model-value="inner.min_count"
          @update:model-value="patch('min_count', $event)"
          label="Кол-во"
          :min="1"
          density="compact"
          hide-details
          style="min-width: 90px;"
        />
      </template>

      <template v-else-if="inner.type === 'has_tag'">
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
          class="flex-grow-1"
        />
      </template>

      <template v-else-if="inner.type === 'characteristic_value'">
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
          class="flex-grow-1"
        />
        <DimensionalNumberInput
          :model-value="inner.min"
          @update:model-value="(v) => patch('min', v)"
          label="Мин. значение"
          mode="characteristic"
          style="flex: 1 1 auto;"
        />
      </template>

      <template v-else-if="inner.type === 'resource_limit'">
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
          class="flex-grow-1"
        />
        <DimensionalNumberInput
          v-if="selectedResourceIsDimensional"
          :model-value="(inner.min as any) ?? { base: 0, size: 0 }"
          @update:model-value="(v) => patch('min', v)"
          label="Лимит (мин)"
          style="flex: 1 1 auto;"
        />
        <ClampedNumberField
          v-else
          :model-value="typeof inner.min === 'number' ? inner.min : (inner.min?.base ?? 0)"
          @update:model-value="patch('min', $event)"
          label="Лимит (мин)"
          :min="0"
          density="compact"
          hide-details
          style="min-width: 110px;"
        />
      </template>

      <v-btn
        v-if="removable"
        icon
        size="x-small"
        color="error"
        variant="text"
        class="mt-1"
        @click="emit('remove')"
      >
        <v-icon>mdi-delete</v-icon>
      </v-btn>
    </div>

    <div v-if="inner.type === 'and' || inner.type === 'or'" class="ml-6 mt-2">
      <div
        v-for="(child, index) in inner.children"
        :key="index"
        class="mb-1"
      >
        <RequirementNodeEditor
          v-model="inner.children[index]"
          :characteristics="characteristics"
          :resources="resources"
          :abilities="abilities"
          :tags="tags"
          :ability-tags="abilityTags"
          removable
          @remove="removeChild(index)"
        />
      </div>
      <v-btn
        variant="text"
        color="primary"
        size="small"
        @click="addChild"
      >
        <v-icon start>mdi-plus</v-icon>
        Добавить условие
      </v-btn>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { Requirement } from '@/modules/Roleplay/Rule/Interface/abilityTypes'
import type { CharacteristicRef, ResourceRef, AbilityRef, TagRef } from '@/modules/Roleplay/Rule/Interface/abilityTypes'
import DimensionalNumberInput from '@/modules/Core/Engine/Components/DimensionalNumberInput.vue'
import ClampedNumberField from '@/modules/Core/UI/Components/Input/ClampedNumberField.vue'

const props = defineProps<{
  modelValue: Requirement
  characteristics: CharacteristicRef[]
  resources: ResourceRef[]
  abilities: AbilityRef[]
  tags: TagRef[]
  abilityTags: TagRef[]
  removable?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Requirement]
  'remove': []
}>()

const inner = ref<Requirement>(JSON.parse(JSON.stringify(props.modelValue)))

const requirementTypes = [
  { label: 'Есть способность', value: 'has_ability', description: 'Персонаж владеет способностью (не ниже уровня)' },
  { label: 'N способностей с тегом', value: 'has_ability_tag', description: 'Количество способностей с указанным тегом' },
  { label: 'Есть признак', value: 'has_tag', description: 'У персонажа есть признак (просто есть/нет)' },
  { label: 'Характеристика >= min', value: 'characteristic_value', description: 'Значение характеристики не ниже указанного' },
  { label: 'Ресурс / лимит', value: 'resource_limit', description: 'Ресурс есть или его лимит не ниже указанного' },
  { label: 'И', value: 'and', description: 'Все условия внутри должны выполниться' },
  { label: 'ИЛИ', value: 'or', description: 'Хотя бы одно условие внутри должно выполниться' },
]

const selectedResourceIsDimensional = computed(() => {
  const v = inner.value
  if (v.type !== 'resource_limit') return false
  return props.resources.find(r => r.code === v.resource_code)?.isDimensional ?? false
})

watch(() => props.resources, (resources) => {
  const v = inner.value
  if (v.type !== 'resource_limit' || !v.resource_code) return
  const res = resources.find(r => r.code === v.resource_code)
  const min = v.min
  if (res?.isDimensional && typeof min === 'number') {
    inner.value = { ...v, min: { base: min, size: 0 } } as Requirement
  } else if (!res?.isDimensional && min && typeof min === 'object' && !Array.isArray(min)) {
    inner.value = { ...v, min: min.base } as Requirement
  }
}, { deep: true })

function updateType(type: string) {
  if (type === 'has_ability') {
    inner.value = { type: 'has_ability', ability_code: '' }
  } else if (type === 'has_ability_tag') {
    inner.value = { type: 'has_ability_tag', tag_code: '', min_count: 1 }
  } else if (type === 'has_tag') {
    inner.value = { type: 'has_tag', tag_code: '' }
  } else if (type === 'characteristic_value') {
    inner.value = { type: 'characteristic_value', characteristic_code: '', min: { base: 3, size: 0 } }
  } else if (type === 'resource_limit') {
    inner.value = { type: 'resource_limit', resource_code: '' }
  } else if (type === 'and') {
    inner.value = { type: 'and', children: [defaultLeaf()] }
  } else {
    inner.value = { type: 'or', children: [defaultLeaf()] }
  }
}

function defaultLeaf(): Requirement {
  return { type: 'has_tag', tag_code: '' }
}

function patch(key: string, value: any) {
  inner.value = { ...inner.value, [key]: value } as Requirement
}

function addChild() {
  if (inner.value.type === 'and' || inner.value.type === 'or') {
    inner.value = { ...inner.value, children: [...inner.value.children, defaultLeaf()] }
  }
}

function removeChild(index: number) {
  if (inner.value.type === 'and' || inner.value.type === 'or') {
    const children = inner.value.children.filter((_, i) => i !== index)
    inner.value = { ...inner.value, children }
  }
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
</style>
