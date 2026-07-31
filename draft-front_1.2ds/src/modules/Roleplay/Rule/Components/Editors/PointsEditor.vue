<template>
  <RuleEditorBase
    :name="name"
    @update:name="(v) => emit('update:name', v)"
    :code="code"
    @update:code="(v) => emit('update:code', v)"
    :description="description"
    @update:description="(v) => emit('update:description', v)"
    :mechanic-id="mechanicId"
    @update:mechanic-id="(v) => emit('update:mechanicId', v)"
    :tag-ids="tagIds"
    @update:tag-ids="(v) => emit('update:tagIds', v)"
    :mechanic-options="mechanicOptions"
    :tag-options="tagOptions"
  >
    <template #spec>
      <v-card class="mt-4">
        <v-card-title>Очки</v-card-title>
        <v-card-text>
          <div class="text-body-2 text-medium-emphasis">
            Очки (ОС/ОЛ/ОР) — валюта создания персонажа, привязанная к этапу. Код — системное имя
            (например <code>os</code>, <code>ol</code>, <code>or</code>): способности ссылаются на него
            в своих зонах. Базовые значения очков не хранятся — бюджеты задаются игрой.
          </div>
        </v-card-text>
      </v-card>
    </template>
  </RuleEditorBase>
</template>

<script setup lang="ts">
import RuleEditorBase from './RuleEditorBase.vue'

defineProps<{
  name: string
  code: string
  description: string
  mechanicId: number | null
  tagIds: number[]
  mechanicOptions: { title: string; value: number }[]
  tagOptions: { title: string; value: number }[]
}>()

const emit = defineEmits<{
  'update:name': [value: string]
  'update:code': [value: string]
  'update:description': [value: string]
  'update:mechanicId': [value: number | null]
  'update:tagIds': [value: number[]]
}>()
</script>
