<script setup lang="ts">
import { ref, watch } from 'vue'
import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement'
import type { CharacteristicRef } from '@/modules/Roleplay/Rule/Dto/Ability/CharacteristicRef'
import type { ResourceRef } from '@/modules/Roleplay/Rule/Dto/Ability/ResourceRef'
import type { AbilityRef } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityRef'
import type { KeywordRef } from '@/modules/Roleplay/Rule/Dto/Ability/KeywordRef'
import RequirementNodeEditor from '@/modules/Roleplay/Rule/Component/Editors/RequirementNodeEditor.vue'

const props = defineProps<{
  modelValue: Requirement[]
  characteristics: CharacteristicRef[]
  resources: ResourceRef[]
  abilities: AbilityRef[]
  keywords: KeywordRef[]
  abilityKeywords: KeywordRef[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Requirement[]]
}>()

const list = ref<Requirement[]>(structuredClone(props.modelValue))

function updateItem(index: number, value: Requirement) {
  const next = list.value.map((r, i) => (i === index ? value : r))
  list.value = next
  emit('update:modelValue', structuredClone(next))
}

function removeItem(index: number) {
  const next = list.value.filter((_, i) => i !== index)
  list.value = next
  emit('update:modelValue', structuredClone(next))
}

function addItem() {
  const next = [...list.value, { type: 'has_keyword', keyword_code: '' } as Requirement]
  list.value = next
  emit('update:modelValue', structuredClone(next))
}

watch(() => props.modelValue, (value) => {
  if (JSON.stringify(value) !== JSON.stringify(list.value)) {
    list.value = structuredClone(value)
  }
}, { deep: true })
</script>

<template>
  <div>
    <div v-if="list.length === 0" class="text-body-2 text-medium-emphasis mb-2">
      Требований нет — способность доступна сразу.
    </div>
    <div class="text-caption text-medium-emphasis mb-1">
      Все требования списка должны выполниться (неявное «И»). Для явной логики используйте группы И/ИЛИ.
    </div>
    <div
      v-for="(req, index) in list"
      :key="index"
      class="mb-1"
    >
      <RequirementNodeEditor
        :model-value="req"
        @update:model-value="updateItem(index, $event)"
        :characteristics="characteristics"
        :resources="resources"
        :abilities="abilities"
        :keywords="keywords"
        :ability-keywords="abilityKeywords"
        removable
        @remove="removeItem(index)"
      />
    </div>
    <v-btn
      variant="text"
      color="primary"
      size="small"
      @click="addItem"
    >
      <v-icon start>mdi-plus</v-icon>
      Добавить требование
    </v-btn>
  </div>
</template>
