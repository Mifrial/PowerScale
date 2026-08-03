<script setup lang="ts">
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue'

defineProps<{
  name: string
  code: string
  codeDisabled?: boolean
  description: string
  mechanicId: number | null
  keywordIds: number[]
  mechanicOptions: { title: string; value: number }[]
  keywordOptions: { title: string; value: number }[]
}>()

const emit = defineEmits<{
  'update:name': [value: string]
  'update:code': [value: string]
  'update:description': [value: string]
  'update:mechanicId': [value: number | null]
  'update:keywordIds': [value: number[]]
}>()
</script>

<template>
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
